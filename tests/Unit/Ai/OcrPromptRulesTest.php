<?php

/** The Indonesian-label rules found necessary by the live evaluation must stay in the OCR system prompt. */
it('keeps the energy, serving size and salt-versus-sodium rules in the OCR prompt', function () {
    $prompt = file_get_contents(dirname(__DIR__, 3).'/ai/prompts/ocr-system.txt');

    expect($prompt)->toContain('Energi dari Lemak bukan energi total')
        ->toContain('angka kkal yang tercetak (raw_unit=kcal), bukan kJ')
        ->toContain('Per 100 g adalah basis, bukan takaran saji; jika tidak ada baris Takaran Saji/Serving Size')
        ->toContain('Garam, Salt, Zout atau Sel tanpa kata Natrium/Sodium bukan natrium')
        ->toContain('Evidence memuat nama baris');
});
