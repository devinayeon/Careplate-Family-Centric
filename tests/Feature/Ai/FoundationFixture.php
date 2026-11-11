<?php

/*
 * SYNTHETIC test data in the USDA Foundation Foods JSON shape. The fdcIds (9xxxxx) and every
 * number here are invented for tests only; none of this is USDA data.
 */

/** @return array<string, mixed> */
function synFood(int $fdcId, string $description, array $nutrients): array
{
    $entries = [];
    foreach ($nutrients as $key => $spec) {
        $entries[] = synNutrient((int) $key, $spec);
    }

    return [
        'fdcId' => $fdcId, 'description' => $description, 'dataType' => 'Foundation',
        'publicationDate' => '4/1/2019', 'foodNutrients' => $entries,
    ];
}

/**
 * @param  array<string, mixed>|float|int  $spec  amount, or [amount, unit, extra keys...]
 * @return array<string, mixed>
 */
function synNutrient(int $id, array|float|int $spec): array
{
    $units = [1003 => 'g', 1004 => 'g', 1005 => 'g', 1093 => 'mg', 2000 => 'g', 2047 => 'kcal', 2048 => 'kcal', 1008 => 'kcal'];
    $spec = is_array($spec) ? $spec : ['amount' => $spec];
    $entry = [
        'type' => 'FoodNutrient',
        'nutrient' => ['id' => $id, 'number' => (string) ($id - 800), 'name' => "synthetic {$id}", 'unitName' => $spec['unit'] ?? ($units[$id] ?? 'g')],
    ];
    if (isset($spec['number'])) {
        $entry['nutrient']['number'] = $spec['number'];
    }
    unset($spec['unit'], $spec['number']);

    return array_merge($entry, $spec);
}

/** @return array<int, array<string, mixed>> */
function synFoods(): array
{
    return [
        synFood(900001, 'Rice, white, long grain, unenriched, raw', [
            1003 => 7.5, 1004 => 1.25, 1005 => 80.5, 1093 => 0.5, 2047 => 359, 2048 => 370, 1008 => 365,
        ]),
        synFood(900002, 'Rice, white, long grain, unenriched, cooked', [
            1003 => 2.5, 1004 => 0.25, 1005 => 28.5, 1093 => 1.5, 2047 => 130,
        ]),
        // Only legacy energy; sodium flagged LOQ; protein only under another id whose legacy number is 1003.
        synFood(900003, 'Broccoli, raw', [
            1004 => 0.5, 1005 => 6.5, 1008 => 31, 1093 => ['amount' => 0, 'loq' => 2.0],
            9999 => ['amount' => 99, 'number' => '1003'],
        ]),
        synFood(900004, 'Apples, fuji, with skin, raw', [1003 => 0.5, 1004 => 0.25, 1005 => 15.5, 1093 => 1, 2000 => 13.5, 2048 => 58]),
        synFood(900005, 'Apples, gala, with skin, raw', [1003 => 0.5, 1004 => 0.25, 1005 => 14.5, 1093 => 1, 2048 => 57]),
        synFood(900006, 'Bananas, overripe, raw', [1003 => 1, 1004 => 0.25, 1005 => 23, 2048 => 90]),
        synFood(900007, 'Bananas, ripe and slightly ripe, raw', [1003 => 1, 1004 => 0.25, 1005 => 22, 2048 => 88]),
        synFood(900008, 'Sweet potatoes, orange flesh, without skin, raw', [1003 => 1.5, 1004 => 0.25, 1005 => 17, 1093 => 0, 2048 => 77]),
        synFood(900009, 'Potatoes, russet, without skin, raw', [1003 => 2, 1004 => 0.25, 1005 => 17.5, 1093 => 2.5, 2048 => 81]),
        // Negative amount and a wrong unit.
        synFood(900010, 'Carrots, mature, raw', [
            1003 => -1, 1004 => ['amount' => 0.5, 'unit' => 'mg'], 1005 => 10, 2048 => 45,
        ]),
    ];
}

/** @return list<array<string, string>> */
function synCuration(): array
{
    $row = fn (string $id, int $priority, string $name, string $query, string $state) => [
        'candidate_id' => $id, 'priority' => (string) $priority, 'display_name_id' => $name,
        'search_query_en' => $query, 'required_state' => $state,
    ];

    return [
        $row('FOOD-001', 1, 'Nasi putih matang tanpa tambahan', 'rice white cooked without added fat', 'cooked plain'),
        $row('FOOD-002', 2, 'Beras putih mentah', 'rice white raw', 'raw dry'),
        $row('FOOD-003', 3, 'Pisang tanpa kulit', 'bananas raw', 'raw edible portion without peel'),
        $row('FOOD-004', 4, 'Apel dengan kulit', 'apples raw with skin', 'raw edible portion without core'),
        $row('FOOD-009', 9, 'Kentang mentah', 'potatoes raw', 'raw edible portion as exact source description'),
        $row('FOOD-012', 12, 'Ubi jalar mentah', 'sweet potato raw', 'raw edible portion'),
        $row('FOOD-016', 16, 'Brokoli mentah', 'broccoli raw', 'raw edible portion'),
        $row('FOOD-019', 19, 'Tahu firm varian sumber', 'tofu firm', 'exact coagulant required'),
        $row('FOOD-005', 5, 'Wortel mentah', 'carrots raw', 'raw edible portion'),
    ];
}

/** @return array<string, mixed> */
function synSnapshot(): array
{
    return [
        'provider' => 'USDA_FDC', 'version' => '9999-01-01', 'sha256' => str_repeat('ab', 32),
        'url' => 'https://example.test/synthetic.zip', 'retrieved_at' => '2026-11-08T00:00:00+00:00',
    ];
}
