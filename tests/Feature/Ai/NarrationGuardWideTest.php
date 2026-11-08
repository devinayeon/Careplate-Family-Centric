<?php

use App\Services\Ai\Chat\NarrationGuard;

/** Fix round 1: wider number words and units, generic citations, zero-width characters, no-intl fallback. */
test('English and Indonesian number words before a nutrition unit are blocked', function (string $text) {
    expect((new NarrationGuard)->safe($text))->toBeFalse();
})->with([
    'fifty grams' => 'Eat fifty grams of rice.',
    'twenty percent' => 'About twenty percent of the day.',
    'eleven kcal' => 'It has eleven kcal.',
    'twenty-five g' => 'Take twenty-five g daily.',
    'one hundred and' => 'one hundred grams',
    'two thousand kcal' => 'two thousand kcal per day',
    'half kilo' => 'half a kilo of fruit is fine; a half kilo',
    'sebelas' => 'sebelas gram gula',
    'dua belas' => 'dua belas gram serat',
    'dua puluh lima' => 'dua puluh lima gram protein',
    'seratus lima puluh' => 'seratus lima puluh kkal',
    'seribu' => 'seribu kalori sehari',
    'dua ribu' => 'dua ribu kkal',
    'setengah' => 'setengah gram garam',
    'seperempat' => 'seperempat kg beras',
    'tiga ratus' => 'tiga ratus mg natrium',
    'digits kilokalori' => '200 kilokalori',
    'setengah kilo' => 'setengah kilo beras',
    'puluhan gram' => 'puluhan gram gula',
    'ratusan kalori' => 'ratusan kalori',
    'belasan mg' => 'belasan mg natrium',
    'ons' => '2 ons daging',
    'cc' => '250 cc susu',
    'cal' => '300 cal',
    'oz' => '8 oz juice',
    'lb' => '1 lb of rice',
]);

test('generic citations and unlisted sources are blocked', function (string $text) {
    expect((new NarrationGuard)->safe($text))->toBeFalse();
})->with([
    'NIH' => 'Menurut NIH, serat penting.',
    'CDC' => 'Data CDC menyebutkan hal ini.',
    'EFSA' => 'Pedoman EFSA berlaku.',
    'NHS' => 'Saran NHS.',
    'AHA' => 'Panduan AHA.',
    'Harvard' => 'Riset Harvard menunjukkan hal ini.',
    'menurut capitalised' => 'Menurut Profesor Budi, serat penting.',
    'et al' => 'Serat penting (Smith et al., 2019).',
    'et al bare' => 'Serat penting, kata Smith et al.',
    'author year' => 'Serat penting (Rahman, 2021).',
    'jurnal year' => 'Sebuah jurnal tahun 2019 menyatakan hal ini.',
    'penelitian year after' => 'Penelitian pada 2020 menyatakan hal ini.',
    'year before study' => 'Pada 2018 sebuah studi menyatakan hal ini.',
    'studi org' => 'Studi oleh Universitas Indonesia menyatakan hal ini.',
    'jurnal org' => 'Jurnal The Lancet memuatnya.',
    'penelitian org' => 'Penelitian terbaru Stanford menunjukkan hal ini.',
    'sumber data' => 'Sumber data: laporan tahunan.',
    'sumber informasi' => 'Sumber informasi: artikel gizi.',
    'sumber bare' => 'Sumber: artikel gizi.',
    'referensi' => 'Referensi: buku gizi.',
    'doi' => 'doi:10.1000/xyz',
]);

test('ordinary Indonesian education text still passes', function (string $text) {
    expect((new NarrationGuard)->safe($text))->toBeTrue();
})->with([
    'protein' => 'Protein membantu pertumbuhan otot dan penting untuk anak.',
    'serat' => 'Serat membantu pencernaan, jadi perbanyak sayur dan buah setiap hari.',
    'sumber idiom' => 'Sumber protein nabati: tahu, tempe, dan kacang-kacangan.',
    'sumber kalsium' => 'Sumber kalsium yang baik: susu dan ikan teri.',
    'menurut lower' => 'Menurut para ahli gizi, variasi makanan itu penting.',
    'menurut anda' => 'Menurut Anda, makanan apa yang paling sering dimakan?',
    'penelitian generic' => 'Penelitian tentang gizi terus berkembang. Protein tetap penting.',
    'studi kasus' => 'Studi kasus membantu belajar. Baca label dengan teliti.',
    'dua kali' => 'Makan dua kali atau tiga kali sehari dengan porsi seimbang.',
    'satu gelas' => 'Minum satu gelas air setelah makan.',
    'kilo word' => 'Kilometer bukan satuan gizi; satuan gizi ada di label.',
    'half word' => 'Halfway tidak relevan di sini.',
    'ons word' => 'Konsumsi sayur yang cukup itu baik.',
    'year alone' => 'Panduan ini diperbarui sehingga edukasi tetap relevan.',
]);

test('zero-width, soft-hyphen and exotic-space characters cannot hide a number', function (string $text) {
    expect((new NarrationGuard)->safe($text))->toBeFalse()->and((new NarrationGuard(false))->safe($text))->toBeFalse();
})->with([
    'zero width space' => "Ada 10\u{200B}g protein.",
    'zero width joiner' => "Ada 10\u{200D} g protein.",
    'soft hyphen' => "Ada 10\u{00AD}g protein.",
    'bom' => "Ada 10\u{FEFF}mg natrium.",
    'split inside unit' => "Ada 10 k\u{200B}cal.",
    'nbsp' => "Ada 10\u{00A0}g protein.",
    'ideographic space' => "Ada 10\u{3000}g protein.",
    'url split' => "Lihat ht\u{200B}tps://x.test",
]);

test('without ext-intl the fallback fold still catches fullwidth digits, letters and percent', function (string $text) {
    expect((new NarrationGuard(false))->safe($text))->toBeFalse();
})->with([
    'fullwidth digits' => '５０ gram gula',
    'fullwidth unit' => '50 ｇ protein',
    'fullwidth all' => '５０ ｋｃａｌ',
    'fullwidth percent' => '２０％ kebutuhan',
    'fullwidth url' => 'ｈｔｔｐｓ://x.test',
]);

test('the fallback path accepts plain education the same way', function () {
    $guard = new NarrationGuard(false);

    expect($guard->safe('Protein membantu pertumbuhan otot dan penting untuk anak.'))->toBeTrue()
        ->and($guard->safe('Data USDA ditinjau sumber.', 'USDA FoodData Central, ID 1'))->toBeTrue();
});

test('invalid UTF-8 fails closed', function () {
    expect((new NarrationGuard)->safe("Halo \xFF\xFE dunia"))->toBeFalse();
});
