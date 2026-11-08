<?php

namespace App\Services\Ai\Foods;

/**
 * Reads the six MVP nutrients from one Foundation record by modern `nutrient.id` (never the
 * legacy `nutrient.number`). Values are per 100 g and returned as decimal strings. Anything that
 * is missing, LOQ-flagged, negative, in the wrong unit or ambiguous becomes null (never 0) and
 * the reason is kept in the provenance. Exactly one energy method is chosen; methods are never summed.
 */
class FoundationNutrients
{
    private const FIELDS = [
        'protein_g' => [1003, 'g'],
        'fat_g' => [1004, 'g'],
        'carbohydrate_g' => [1005, 'g'],
        'sodium_mg' => [1093, 'mg'],
        'sugars_total_g' => [2000, 'g'],
    ];

    private const ENERGY = [
        [2048, 'Atwater specific factors'],
        [2047, 'Atwater general factors'],
        [1008, 'Legacy energy'],
    ];

    /**
     * @param  array<string, mixed>  $food
     * @return array{nutrients: array<string, string|null>, provenance: array<string, mixed>}
     */
    public function extract(array $food): array
    {
        $entries = array_values(array_filter((array) ($food['foodNutrients'] ?? []), 'is_array'));
        $nutrients = [];
        $provenance = [];

        $energy = ['value' => null, 'prov' => ['qualifier' => 'unknown', 'nutrient_id' => null, 'method' => null, 'reason' => 'no_energy_nutrient_usable']];
        $skipped = [];
        foreach (self::ENERGY as [$id, $method]) {
            $read = $this->read($entries, $id, 'kcal');
            if ($read['value'] !== null) {
                $energy = ['value' => $read['value'], 'prov' => ['qualifier' => 'exact', 'nutrient_id' => $id, 'method' => $method] + $read['prov']];
                if ($id === 1008) {
                    $energy['prov']['note'] = '1008 used as fallback; import-mapping.json lists it for SR Legacy records only';
                }
                break;
            }
            $skipped[] = ['nutrient_id' => $id, 'qualifier' => $read['prov']['qualifier'], 'reason' => $read['prov']['reason'] ?? null];
        }
        if ($skipped !== []) {
            $energy['prov']['not_used'] = $skipped;
        }
        $nutrients['energy_kcal'] = $energy['value'];

        foreach (self::FIELDS as $field => [$id, $unit]) {
            $read = $this->read($entries, $id, $unit);
            $nutrients[$field] = $read['value'];
            $provenance[$field] = $read['prov'];
        }
        $nutrients['energy_method'] = $energy['value'] === null ? null : $energy['prov']['method'];

        return ['nutrients' => $nutrients, 'provenance' => ['nutrients' => $provenance, 'energy' => $energy['prov']]];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array{value: string|null, prov: array<string, mixed>}
     */
    private function read(array $entries, int $id, string $unit): array
    {
        $matching = array_values(array_filter($entries, fn (array $e) => (int) ($e['nutrient']['id'] ?? 0) === $id));
        if ($matching === []) {
            return $this->result(null, $id, $unit, 'unknown', 'not_reported');
        }
        $results = array_map(fn (array $e) => $this->readEntry($e, $id, $unit), $matching);
        $distinct = array_unique(array_map(fn (array $r) => json_encode($r), $results));
        if (count($distinct) > 1) {
            return $this->result(null, $id, $unit, 'unknown', 'duplicate_unresolvable', ['requires_review' => true, 'entries' => count($matching)]);
        }

        return $results[0];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{value: string|null, prov: array<string, mixed>}
     */
    private function readEntry(array $entry, int $id, string $unit): array
    {
        $extra = ['observed_name' => (string) ($entry['nutrient']['name'] ?? '')];
        $observedUnit = (string) ($entry['nutrient']['unitName'] ?? '');
        if (strtolower(trim($observedUnit)) !== $unit) {
            return $this->result(null, $id, $unit, 'unknown', 'unit_mismatch', $extra + ['requires_review' => true, 'observed_unit' => $observedUnit]);
        }
        $amount = $entry['amount'] ?? null;
        $source = is_int($amount) || is_float($amount) ? $this->format((float) $amount) : null;
        if ($this->hasLoq($entry)) {
            return $this->result(null, $id, $unit, 'less_than', 'loq_reported', $extra + ['source_amount' => $source]);
        }
        if ($source === null || ! is_finite((float) $amount)) {
            return $this->result(null, $id, $unit, 'unknown', 'amount_missing', $extra);
        }
        if ((float) $amount < 0) {
            return $this->result(null, $id, $unit, 'unknown', 'negative_amount', $extra + ['requires_review' => true]);
        }
        if ((float) $amount === 0.0) {
            $extra['note'] = 'source reports 0 and carries no LOQ metadata; reviewer should confirm it is a true zero';
        }

        return $this->result($source, $id, $unit, 'exact', null, $extra);
    }

    /** @param  array<string, mixed>  $entry */
    private function hasLoq(array $entry): bool
    {
        foreach ([$entry, (array) ($entry['nutrient'] ?? [])] as $level) {
            foreach (array_keys($level) as $key) {
                if (preg_match('/loq|limit.?of.?quant/i', (string) $key) === 1) {
                    return true;
                }
            }
        }

        return is_string($entry['footnote'] ?? null)
            && preg_match('/\bLOQ\b|limit of quantif|below (the )?(limit|detection)/i', $entry['footnote']) === 1;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{value: string|null, prov: array<string, mixed>}
     */
    private function result(?string $value, int $id, string $unit, string $qualifier, ?string $reason, array $extra = []): array
    {
        $prov = ['nutrient_id' => $id, 'unit' => $unit, 'qualifier' => $qualifier];
        if ($reason !== null) {
            $prov['reason'] = $reason;
        }

        return ['value' => $value, 'prov' => $prov + $extra];
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
