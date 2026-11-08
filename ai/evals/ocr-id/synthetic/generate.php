<?php

/**
 * Renders 6 synthetic Indonesian (BPOM "Informasi Nilai Gizi") label images with GD and writes
 * manifest.csv next to them. The truth is exact by construction: every value printed on a label
 * comes from the same spec array that becomes the manifest row.
 *
 * Run from the repository root:  php ai/evals/ocr-id/synthetic/generate.php [path/to/regular.ttf [path/to/bold.ttf]]
 * Font: Arial (C:\Windows\Fonts\arial.ttf and arialbd.ttf) by default; pass TTF paths to use another font.
 * Output (committed): images/syn-*.png and manifest.csv (same columns as ai/evals/ocr/manifest.csv).
 * Needs GD with FreeType. Plain script, not part of the application.
 */
const W = 900;
const ROW = 46;

$regular = $argv[1] ?? 'C:/Windows/Fonts/arial.ttf';
$bold = $argv[2] ?? 'C:/Windows/Fonts/arialbd.ttf';
if (! is_file($regular) || ! is_file($bold)) {
    fwrite(STDERR, "TTF font not found; pass regular and bold TTF paths as arguments.\n");
    exit(1);
}
$fontName = basename($regular).' / '.basename($bold);
$dir = __DIR__;
@mkdir($dir.'/images', 0775, true);

$akgNote = '*Persen AKG berdasarkan kebutuhan energi 2150 kkal. Kebutuhan energi Anda mungkin lebih tinggi atau lebih rendah.';

/**
 * Each spec: head lines, energy rows, nutrient rows [label, indent, [values...]], optional footnote, truth.
 * `cols` names the value columns (the last one may be the %AKG column).
 */
$labels = [
    'syn-01-koma-akg' => [
        'split' => 'dev', 'cols' => ['Jumlah per Sajian', '%AKG*'],
        'head' => ['Takaran Saji 1 bungkus (25 g)', 'Jumlah Sajian per Kemasan 4'],
        'energy' => [['Energi Total', '120 kkal'], ['Energi dari Lemak', '45 kkal']],
        'rows' => [['Lemak Total', 0, ['5,5 g', '8%']], ['Lemak Jenuh', 1, ['2,5 g', '11%']], ['Protein', 0, ['1,5 g', '3%']],
            ['Karbohidrat Total', 0, ['17 g', '5%']], ['Serat Pangan', 1, ['0,5 g', '2%']], ['Gula', 1, ['8,5 g', '']],
            ['Garam (Natrium)', 0, ['120 mg', '8%']]],
        'footnote' => $akgNote,
        'truth' => ['basis' => 'per_serving', 'serving' => [25, 'g'], 'n' => [120, 5.5, 8.5, 1.5, 17, 120], 'lt' => '',
            'notes' => 'Decimal comma (5,5 / 1,5 / 8,5), Energi dari Lemak 45 kkal is a distractor (energy truth is 120 kkal Energi Total), Garam (Natrium) 120 mg, %AKG column with the 2150 kkal footnote, Takaran Saji 1 bungkus (25 g) with Jumlah Sajian per Kemasan 4.'],
    ],
    'syn-02-kj-kkal' => [
        'split' => 'holdout', 'cols' => ['Jumlah per Sajian'],
        'head' => ['Takaran Saji 200 ml', '2 Sajian per Kemasan'],
        'energy' => [['Energi Total', '356 kJ (85 kkal)']],
        'rows' => [['Lemak Total', 0, ['0 g']], ['Protein', 0, ['1 g']], ['Karbohidrat Total', 0, ['20 g']],
            ['Gula', 1, ['18 g']], ['Garam (Natrium)', 0, ['45 mg']]],
        'footnote' => null,
        'truth' => ['basis' => 'per_serving', 'serving' => [200, 'ml'], 'n' => [85, 0, 18, 1, 20, 45], 'lt' => '',
            'notes' => 'Energy printed as 356 kJ (85 kkal): truth is 85 kkal (the kJ figure converts to 85.09, the printed kkal is used as label unit). Serving in ml. Exact zero fat. No %AKG column.'],
    ],
    'syn-03-dua-kolom' => [
        'split' => 'holdout', 'cols' => ['Per Sajian (40 g)', 'Per 100 g'],
        'head' => ['Takaran Saji 40 g', '5 Sajian per Kemasan'],
        'energy' => [['Energi Total', '180 kkal', '450 kkal'], ['Energi dari Lemak', '60 kkal', '150 kkal']],
        'rows' => [['Lemak Total', 0, ['7 g', '17,5 g']], ['Protein', 0, ['4 g', '10 g']], ['Karbohidrat Total', 0, ['24 g', '60 g']],
            ['Gula', 1, ['6 g', '15 g']], ['Garam (Natrium)', 0, ['160 mg', '400 mg']]],
        'footnote' => null,
        'truth' => ['basis' => 'per_serving', 'serving' => [40, 'g'], 'n' => [180, 7, 6, 4, 24, 160], 'lt' => '',
            'notes' => 'Dual column: Per Sajian (40 g) first, Per 100 g second. Truth takes the per-serving column (first column, serving size stated); a per_100g reading with the right-hand numbers is also a faithful reading, so a mismatch here is a basis-choice policy finding, not an OCR error.'],
    ],
    'syn-04-kurang-dari' => [
        'split' => 'dev', 'cols' => ['Jumlah per Sajian', '%AKG*'],
        'head' => ['Takaran Saji 1 bungkus (30 g)', 'Jumlah Sajian per Kemasan 1'],
        'energy' => [['Energi Total', '95 kkal'], ['Energi dari Lemak', '5 kkal']],
        'rows' => [['Lemak Total', 0, ['0,5 g', '1%']], ['Protein', 0, ['0 g', '0%']], ['Karbohidrat Total', 0, ['22 g', '7%']],
            ['Gula', 1, ['< 1 g', '']], ['Garam (Natrium)', 0, ['35 mg', '2%']]],
        'footnote' => $akgNote,
        'truth' => ['basis' => 'per_serving', 'serving' => [30, 'g'], 'n' => [95, 0.5, 1, 0, 22, 35], 'lt' => 'sugars_total_g',
            'notes' => 'Gula "< 1 g": truth is 1 with qualifier less_than (less_than_fields=sugars_total_g). Exact printed zero for Protein 0 g must stay 0. Decimal comma in Lemak Total 0,5 g.'],
    ],
    'syn-05-garam-bukan-natrium' => [
        'split' => 'holdout', 'cols' => ['Per 100 g'],
        'head' => ['Jumlah Sajian per Kemasan 6'],
        'energy' => [['Energi Total', '410 kkal'], ['Energi dari Lemak', '150 kkal']],
        'rows' => [['Lemak Total', 0, ['16,7 g']], ['Protein', 0, ['6,2 g']], ['Karbohidrat Total', 0, ['58,4 g']],
            ['Gula', 1, ['21,3 g']], ['Garam', 0, ['0,3 g']]],
        'footnote' => null,
        'truth' => ['basis' => 'per_100g', 'serving' => [null, null], 'n' => [410, 16.7, 21.3, 6.2, 58.4, null], 'lt' => '',
            'notes' => 'Label prints "Garam 0,3 g" and has NO natrium row: sodium truth is null (salt is not sodium; 0,3 g salt must not become 120 mg or 300 mg sodium). Per 100 g, no serving size (null). Decimal commas throughout.'],
    ],
    'syn-06-takaran-rumah-tangga' => [
        'split' => 'holdout', 'cols' => ['Jumlah per Sajian', '%AKG*'],
        'head' => ['Takaran Saji 2 sendok makan', 'Jumlah Sajian per Kemasan 12'],
        'energy' => [['Energi Total', '190 kkal', 'Energi dari Lemak', '70 kkal']],
        'rows' => [['Lemak Total', 0, ['8 g', '12%']], ['Lemak Jenuh', 1, ['3 g', '14%']], ['Protein', 0, ['5 g', '8%']],
            ['Karbohidrat Total', 0, ['24 g', '7%']], ['Gula', 1, ['9,5 g', '']], ['Garam (Natrium)', 0, ['210 mg', '14%']]],
        'footnote' => $akgNote,
        'truth' => ['basis' => 'per_serving', 'serving' => [null, null], 'n' => [190, 8, 9.5, 5, 24, 210], 'lt' => '',
            'notes' => 'Household serving without grams ("2 sendok makan"): serving size truth is null, it must not become grams. Energi Total and Energi dari Lemak sit on one line (distractor next to the total).'],
    ],
];

$rows = [];
foreach ($labels as $name => $spec) {
    $file = $name.'.png';
    render($dir.'/images/'.$file, $spec, $regular, $bold);
    $t = $spec['truth'];
    $sha = hash_file('sha256', $dir.'/images/'.$file);
    $rows[] = [
        'SYN-'.substr($name, 4, 2), $file, $spec['split'], 'clear', 'extract', 'generated:ai/evals/ocr-id/synthetic/generate.php',
        'Generated by the Careplate project, no third-party content', 'Careplate AI team (generated)', $sha, $sha, '2026-11-09',
        "GD render ($fontName), PNG", $t['basis'], $t['serving'][0], $t['serving'][1],
        $t['n'][0], $t['n'][1], $t['n'][2], $t['n'][3], $t['n'][4], $t['n'][5], $t['lt'],
        $t['notes'], 'exact_by_construction', 'synthetic_id',
    ];
}

$header = 'id,file,split,condition,expected_outcome,source_url,license,author_attribution,sha256,original_sha256,downloaded_at,processing,label_basis,serving_size_value,serving_size_unit,energy_kcal,fat_g,sugars_total_g,protein_g,carbohydrate_g,sodium_mg,less_than_fields,notes,truth_status,kind';
$out = fopen($dir.'/manifest.csv', 'wb');
fwrite($out, $header."\n");
foreach ($rows as $row) {
    fputcsv($out, array_map(fn ($v) => $v ?? '', $row), ',', '"', '');
}
fclose($out);
echo 'Wrote '.count($rows)." labels and manifest.csv\n";

/** @param array<string, mixed> $spec */
function render(string $path, array $spec, string $regular, string $bold): void
{
    $img = imagecreatetruecolor(W, 1500);
    $white = imagecolorallocate($img, 255, 255, 255);
    $ink = imagecolorallocate($img, 20, 20, 20);
    imagefilledrectangle($img, 0, 0, W, 1500, $white);

    $cols = count($spec['cols']);
    $xs = $cols === 1 ? [W - 90] : ($cols === 2 ? [W - 250, W - 90] : [W - 330, W - 190, W - 60]);
    $y = 40;
    imagesetthickness($img, 3);
    $top = 20;

    text($img, 30, 40, $y += 52, 'INFORMASI NILAI GIZI', $bold, $ink);
    foreach ($spec['head'] as $line) {
        text($img, 21, 40, $y += 38, $line, $regular, $ink);
    }
    rule($img, $y += 18, $ink);
    $y += 8;
    $perColumns = str_starts_with($spec['cols'][0], 'Per ');
    text($img, 20, 40, $y += 36, $perColumns ? 'JUMLAH' : 'JUMLAH PER SAJIAN', $bold, $ink);
    if ($perColumns) {
        foreach ($spec['cols'] as $i => $c) {
            right($img, 18, $xs[$i], $y, $c, $bold, $ink);
        }
    }
    foreach ($spec['energy'] as $e) {
        $y += ROW;
        if (count($e) === 4) {
            text($img, 22, 40, $y, $e[0].' '.$e[1], $bold, $ink);
            text($img, 22, 420, $y, $e[2].' '.$e[3], $regular, $ink);
        } else {
            text($img, 22, $e[0] === 'Energi Total' ? 40 : 70, $y, $e[0], $e[0] === 'Energi Total' ? $bold : $regular, $ink);
            foreach (array_slice($e, 1) as $i => $v) {
                right($img, 22, $xs[$i], $y, $v, $e[0] === 'Energi Total' ? $bold : $regular, $ink);
            }
        }
    }
    rule($img, $y += 22, $ink);
    $y += 8;
    if ($cols === 2 && $spec['cols'][1] === '%AKG*') {
        right($img, 18, $xs[1], $y += 30, '%AKG*', $bold, $ink);
    }
    foreach ($spec['rows'] as [$label, $indent, $values]) {
        $y += ROW;
        text($img, 22, 40 + $indent * 34, $y, $label, $indent ? $regular : $bold, $ink);
        foreach ($values as $i => $v) {
            right($img, 22, $xs[$i], $y, $v, $regular, $ink);
        }
    }
    rule($img, $y += 24, $ink);
    if ($spec['footnote'] !== null) {
        foreach (explode("\n", wordwrap($spec['footnote'], 78)) as $line) {
            text($img, 15, 40, $y += 28, $line, $regular, $ink);
        }
        $y += 6;
    }
    $y += 24;
    imagerectangle($img, 20, $top, W - 20, $y, $ink);
    $out = imagecrop($img, ['x' => 0, 'y' => 0, 'width' => W, 'height' => $y + 20]);
    imagepng($out, $path, 6);
}

function text(GdImage $img, int $size, int $x, int $y, string $s, string $font, int $color): void
{
    imagettftext($img, $size * 0.75, 0, $x, $y, $color, $font, $s);
}

function right(GdImage $img, int $size, int $x, int $y, string $s, string $font, int $color): void
{
    $box = imagettfbbox($size * 0.75, 0, $font, $s);
    imagettftext($img, $size * 0.75, 0, $x - ($box[2] - $box[0]), $y, $color, $font, $s);
}

function rule(GdImage $img, int $y, int $color): void
{
    imageline($img, 20, $y, W - 20, $y, $color);
}
