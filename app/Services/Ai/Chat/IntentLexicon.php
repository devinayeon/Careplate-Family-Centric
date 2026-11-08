<?php

namespace App\Services\Ai\Chat;

/**
 * Word lists behind IntentParser. All entries are folded (lowercase, no punctuation) like FoodSearchText::normalise().
 * Words that occur inside catalog names (dengan, tanpa, mentah, tahu, bagian ...) must never be stop words.
 */
final class IntentLexicon
{
    /** token => canonical column */
    public const NUTRIENTS = [
        'protein' => 'protein_g',
        'gula' => 'sugars_total_g', 'sugar' => 'sugars_total_g', 'sugars' => 'sugars_total_g',
        'kalori' => 'energy_kcal', 'energi' => 'energy_kcal', 'kkal' => 'energy_kcal', 'kcal' => 'energy_kcal',
        'calorie' => 'energy_kcal', 'calories' => 'energy_kcal', 'energy' => 'energy_kcal',
        'lemak' => 'fat_g', 'fat' => 'fat_g',
        'karbohidrat' => 'carbohydrate_g', 'karbo' => 'carbohydrate_g', 'carbs' => 'carbohydrate_g',
        'carb' => 'carbohydrate_g', 'carbohydrate' => 'carbohydrate_g', 'carbohydrates' => 'carbohydrate_g',
        'natrium' => 'sodium_mg', 'sodium' => 'sodium_mg',
    ];

    /** Contract order of nutrient columns. */
    public const ORDER = ['energy_kcal', 'fat_g', 'sugars_total_g', 'protein_g', 'carbohydrate_g', 'sodium_mg'];

    /** Salt is not sodium on this catalog. */
    public const SALT = ['garam', 'salt'];

    /** Tokens that are units of an already-extracted amount or of a nutrient. */
    public const UNITS = ['g', 'gr', 'gram', 'grams', 'mg', 'kg', 'ml', 'cc', 'liter', 'l'];

    public const STOP = [
        // question words
        'berapa', 'berapakah', 'apa', 'apakah', 'ada', 'adalah', 'itu', 'ini', 'yang', 'di', 'ke', 'dari', 'dalam',
        'pada', 'untuk', 'per', 'saya', 'aku', 'kamu', 'kami', 'kita', 'anda', 'dong', 'deh', 'sih', 'ya', 'yah',
        'kah', 'lah', 'nih', 'tuh', 'juga', 'saja', 'aja', 'kalau', 'kalo', 'jika', 'bila', 'apabila', 'tidak',
        'ngga', 'nggak', 'gak', 'bukan', 'belum', 'sudah', 'sedang', 'akan', 'bisa', 'bisakah', 'mau', 'ingin',
        'dan', 'atau', 'serta', 'vs', 'dibanding', 'dibandingkan', 'tersebut', 'nah', 'lalu', 'terus', 'kemudian',
        // ask for a number
        'tolong', 'mohon', 'hitung', 'hitungkan', 'kasih', 'beri', 'berikan', 'tampilkan', 'lihat', 'cek', 'cari',
        'carikan', 'kandungan', 'jumlah', 'kadar', 'banyak', 'seberapa', 'mengandung', 'terkandung', 'nilai',
        'gizi', 'nutrisi', 'total', 'sekitar', 'kira', 'sebesar', 'makanan', 'punya', 'memiliki',
        // education wording
        'fungsi', 'manfaat', 'peran', 'guna', 'kegunaan', 'pengertian', 'definisi', 'arti', 'kenapa', 'mengapa',
        'bagaimana', 'gimana', 'cara', 'tips', 'jenis', 'bahaya', 'efek', 'dampak', 'jelaskan', 'penjelasan',
        'perbedaan', 'bedanya', 'beda',
        // greetings
        'halo', 'hai', 'hi', 'hello', 'selamat', 'pagi', 'siang', 'sore', 'malam', 'terima', 'kasih', 'makasih',
        'thanks', 'ok', 'oke', 'baik',
        // english
        'how', 'much', 'many', 'is', 'are', 'the', 'a', 'an', 'of', 'in', 'on', 'for', 'to', 'does', 'do', 'did',
        'what', 'whats', 'tell', 'me', 'about', 'has', 'have', 'contain', 'contains', 'and', 'or', 'there', 'any',
        'i', 'my', 'please', 'can', 'you', 'calculate', 'show', 'give', 'content', 'amount', 'nutrition', 'facts',
        'value', 'explain', 'benefit', 'benefits', 'function', 'difference', 'why', 'with', 'label',
    ];

    /** Marks a request to explain rather than to look up numbers. */
    public const EDUCATION = '/\b(fungsi|manfaat|peran|guna|kegunaan|pengertian|definisi|arti|kenapa|mengapa|bagaimana|gimana|cara|tips|jenis|bahaya|efek|dampak|jelaskan|penjelasan|perbedaan|bedanya|beda|apa itu|explain|benefits?|function|difference|why|what is)\b/u';

    /** Marks a request for a quantity. */
    public const NUMBER_ASK = '/\b(berapa|berapakah|how much|how many|kandungan|kadar|jumlah|banyak|seberapa|mengandung|terkandung|content|amount)\b/u';

    /** Diagnosis, medication, dose and condition wording: answered by referral, never by numbers. */
    public const CLINICAL = '/\b(dosis|dose|dosage|insulin|metformin|obat|obatan|diagnosis|diagnosa|didiagnosis|didiagnosa|mendiagnosis|terapi|penyakit|diabetes|diabetesi|hipertensi|ginjal|stroke|kanker|hamil|menyusui|dokter|medis|klinis|penderita|pengidap|pasien|medication|medicine|diagnosed|diagnose|therapy|pregnant|disease|doctor|patient|gula darah|darah tinggi|asam urat)\b/u';

    /** A quantity in grams: 50 gram, 50g, 12,5 g. */
    public const GRAM = '/(?<![\d.,])(\d+(?:[.,]\d+)?)\s*(?:gram|grams|gr|g)\b/u';

    /** A quantity in volume. */
    public const VOLUME = '/(?<![\d.,])\d+(?:[.,]\d+)?\s*(?:ml|cc|liter|l)\b/u';

    private const UNIT_WORDS = 'mangkuk|mangkok|gelas|piring|sendok|porsi|butir|potong|buah|biji|iris|lembar|bungkus|cangkir|sachet|ekor|centong|genggam|cup|slice|piece|serving|bowl|glass|plate|spoon|tablespoon|teaspoon|sdm|sdt|kg|kilogram|kilo|ons';

    private const NUMBER_WORDS = 'satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh|setengah|separuh|one|two|three|half';

    /** A household measure: "semangkuk", "1 gelas", "dua potong", or "porsi" on its own. */
    public static function household(): string
    {
        $u = self::UNIT_WORDS;
        $n = self::NUMBER_WORDS;

        return '/\bse(?:'.$u.')\b|(?<![\p{L}\d.,])(?:\d+(?:[.,]\d+)?\s*|\d+\/\d+\s*|(?:'.$n.')\s+)(?:'.$u.')\b|\b(?:porsi|serving|portion)\b/u';
    }
}
