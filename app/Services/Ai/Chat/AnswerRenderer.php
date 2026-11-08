<?php

namespace App\Services\Ai\Chat;

use App\Models\Food;
use App\Services\Ai\Foods\FoodSearch;
use App\Services\Ai\Foods\FoodSource;
use App\Services\Ai\Scan\Decimal;

/**
 * Server-side templates for chat answers. Every number and citation is built here from catalog rows with BCMath;
 * nothing is taken from model text and none of these methods calls a model, so `database` mode survives a provider outage.
 *
 * Each method returns the ChatResponse fields owned by the renderer (ChatService adds schema_version, request_id,
 * session_id, turn_id, client_turn_id and sequence):
 *   array{mode: string, retrieval_status: string, answer: string, claims: list<array>, citations: list<array>,
 *         general_knowledge_notice: ?string, warnings: list<string>}
 */
class AnswerRenderer
{
    public const GENERAL_NOTICE = 'Penjelasan umum dari pengetahuan model, bukan hasil database makanan yang ditinjau sumber.';

    public const DEFAULT_AMOUNT_G = '100';

    /** column => [label in text, unit in contract, unit in text] */
    private const NUTRIENTS = [
        'energy_kcal' => ['energi', 'kcal', 'kkal'],
        'fat_g' => ['lemak', 'g', 'g'],
        'sugars_total_g' => ['gula total', 'g', 'g'],
        'protein_g' => ['protein', 'g', 'g'],
        'carbohydrate_g' => ['karbohidrat', 'g', 'g'],
        'sodium_mg' => ['natrium', 'mg', 'mg'],
    ];

    /** Canonical value times amount over 100, at working precision (never rounded). */
    public static function derive(string $canonical, string $amountG): string
    {
        return Decimal::div(Decimal::mul($canonical, $amountG), '100');
    }

    /**
     * Mode database, retrieval found: one food, the requested nutrients (all six when empty), one citation.
     * A null nutrient yields no claim but is stated as unknown; the citation is always present.
     *
     * @param  list<string>  $nutrients  Intent::$nutrients
     * @param  ?string  $amountG  decimal string, null = state the default 100 g
     * @param  list<string>  $notes  Intent::NOTE_* constants
     * @return array<string, mixed>
     */
    public function database(Food $food, array $nutrients, ?string $amountG, array $notes = []): array
    {
        $columns = array_values(array_filter(array_keys(self::NUTRIENTS), fn (string $c) => $nutrients === [] || in_array($c, $nutrients, true)));
        $amount = $amountG ?? self::DEFAULT_AMOUNT_G;
        $claims = [];
        $known = [];
        $unknown = [];
        foreach ($columns as $column) {
            [$label, $unit, $shown] = self::NUTRIENTS[$column];
            $canonical = $food->{$column};
            if ($canonical === null) {
                $unknown[] = $label;

                continue;
            }
            $canonical = (string) $canonical;
            $derived = self::derive($canonical, $amount);
            $claims[] = [
                'food_id' => (string) $food->getKey(), 'revision' => (int) $food->current_revision, 'nutrient' => $column,
                'canonical_value' => Decimal::toNumber($canonical, 4), 'unit' => $unit, 'canonical_basis' => '100g',
                'requested_amount' => Decimal::toNumber($amount, 2), 'derived_value' => Decimal::toNumber($derived, 6),
                'source_type' => 'database', 'citation_id' => 'food-1',
            ];
            $known[] = $this->shown($derived).' '.$shown.' '.$label;
        }

        $sentences = [$this->lead($food, $amountG, $known, $unknown)];
        if ($known !== [] && $unknown !== []) {
            $sentences[] = 'Kandungan '.$this->listed($unknown).' belum diketahui pada data ini, sehingga tidak ada angka yang ditampilkan.';
        }
        $sentences[] = 'Sumber: revisi '.(int) $food->current_revision.', basis 100 g, ditinjau sumber (source-reviewed).';

        $warnings = [];
        if ($amountG === null) {
            $warnings[] = 'Jumlah tidak disebutkan, sehingga dipakai basis data 100 g.';
        }
        if (in_array(Intent::NOTE_SALT_IS_NOT_SODIUM, $notes, true)) {
            $warnings[] = 'Data memuat natrium (mg), bukan garam (NaCl); keduanya tidak sama.';
        }

        return $this->response('database', 'found', implode(' ', $sentences), $claims, [$this->citation($food)], null, $warnings);
    }

    /**
     * Mode clarification: several verified foods fit; ask which one. Lists at most five names, no numbers.
     *
     * @param  list<Food>  $candidates
     * @return array<string, mixed>
     */
    public function ambiguous(array $candidates): array
    {
        $names = array_map(fn (Food $f) => $f->name, array_slice($candidates, 0, FoodSearch::MAX_CANDIDATES));
        $text = 'Ada beberapa data yang cocok: '.implode('; ', $names).'. Sebutkan nama yang Anda maksud, dan jumlahnya dalam gram bila perlu.';

        return $this->response('clarification', 'ambiguous', $text);
    }

    /**
     * Mode clarification for an Intent::REASON_* case. `$food` (optional) is named in the question.
     *
     * @return array<string, mixed>
     */
    public function clarification(string $reason, ?Food $food = null): array
    {
        $text = match ($reason) {
            Intent::REASON_HOUSEHOLD => 'Ukuran rumah tangga seperti mangkuk, gelas, atau porsi belum dapat dipetakan ke gram. Sebutkan jumlahnya dalam gram, misalnya 100 g.',
            Intent::REASON_VOLUME => 'Jumlah dalam mL atau liter belum dapat diubah ke gram tanpa data kepadatan. Sebutkan jumlahnya dalam gram.',
            Intent::REASON_SALT => 'Data yang tersedia memuat natrium (mg), bukan garam (NaCl). Apakah yang Anda maksud kandungan natrium?',
            Intent::REASON_MULTIPLE_AMOUNTS => 'Pesan menyebut lebih dari satu jumlah gram. Sebutkan satu jumlah saja agar angkanya jelas.',
            Intent::REASON_TOO_LONG => 'Pertanyaan terlalu panjang atau menyebut terlalu banyak hal. Tanyakan satu makanan dan satu jumlah gram dalam setiap pertanyaan.',
            default => 'Pertanyaan belum cukup jelas. Sebutkan satu makanan, zat gizi, dan jumlahnya dalam gram.',
        };

        return $this->response('clarification', 'ambiguous', $food === null ? $text : 'Untuk '.$food->name.': '.$text);
    }

    /**
     * Product or food not in the verified catalog: say so, invent nothing, suggest a label scan.
     * Mode general, so the notice is set; ChatService may append model education (without product numbers).
     *
     * @return array<string, mixed>
     */
    public function noMatch(): array
    {
        $text = 'Makanan atau produk itu belum ada dalam database makanan yang ditinjau sumber, sehingga saya belum dapat memastikan angka gizinya. '
            .'Anda dapat memindai label kemasan untuk membaca angkanya. Secara umum, perhatikan takaran saji saat membandingkan label.';

        return $this->response('general', 'no_match', $text, [], [], self::GENERAL_NOTICE);
    }

    /**
     * Diagnosis, dose or personal clinical diet: a specific hand-off, no clinical instruction, no numbers.
     *
     * @return array<string, mixed>
     */
    public function referral(): array
    {
        $text = 'Pertanyaan tentang diagnosis, dosis obat atau insulin, atau pengaturan makan untuk kondisi medis tertentu perlu dijawab oleh dokter atau tenaga kesehatan yang menangani Anda. '
            .'Saya hanya dapat memberi edukasi gizi umum, bukan saran klinis. Label gizi membantu membandingkan produk, tetapi tidak menggantikan anjuran tenaga kesehatan.';

        return $this->response('referral', 'not_needed', $text, [], [], self::GENERAL_NOTICE);
    }

    /**
     * @param  list<string>  $known
     * @param  list<string>  $unknown
     */
    private function lead(Food $food, ?string $amountG, array $known, array $unknown): string
    {
        if ($known === []) {
            return 'Pada data '.$food->name.', kandungan '.$this->listed($unknown).' belum diketahui, sehingga tidak ada angka yang ditampilkan.';
        }
        $amount = $amountG === null
            ? 'untuk 100 g (basis data, karena jumlah tidak disebutkan) terdapat'
            : $this->shown($amountG).' g mengandung';

        return 'Pada data '.$food->name.', '.$amount.' '.$this->listed($known).'.';
    }

    /** Display rounding only: two decimals, comma, "kurang dari 0,01" for a tiny non-zero value. */
    private function shown(string $value): string
    {
        if (Decimal::cmp($value, '0') > 0 && Decimal::round($value, 2) === '0') {
            return 'kurang dari 0,01';
        }

        return Decimal::display($value);
    }

    /** @param  list<string>  $items */
    private function listed(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? (string) $last : implode(', ', $items).' dan '.$last;
    }

    /** @return array<string, mixed> */
    private function citation(Food $food): array
    {
        return [
            'id' => 'food-1', 'food_id' => (string) $food->getKey(), 'revision' => (int) $food->current_revision,
            'food_name' => $food->name, 'basis' => '100g', 'source_reference' => FoodSource::reference($food),
            'verified_at' => FoodSource::verifiedAt($food),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $claims
     * @param  list<array<string, mixed>>  $citations
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function response(string $mode, string $status, string $answer, array $claims = [], array $citations = [], ?string $notice = null, array $warnings = []): array
    {
        return [
            'mode' => $mode, 'retrieval_status' => $status, 'answer' => $answer, 'claims' => $claims,
            'citations' => $citations, 'general_knowledge_notice' => $notice, 'warnings' => $warnings,
        ];
    }
}
