<?php

return [
    'policy' => [
        'id' => env('NUTRITION_POLICY_ID', 'careplate-ggl-screening-draft-v1'),
        'status' => env('NUTRITION_POLICY_STATUS', 'provisional'),
        'scope' => 'general_adult_educational_screening',
    ],

    // Educational daily comparisons, not label %AKG or personal requirements.
    'daily_reference' => [
        'sugars_total_g' => '50',
        'sodium_mg' => '2000',
        'fat_g' => '67',
    ],

    // The internal 20% rule is provisional, not a regulatory "high" threshold.
    // Only an approved policy may publish flags; unknown data must stay unknown.
    'screening' => [
        'threshold_percent' => '20',
        'comparison' => 'gte',
    ],

    'reference_source' => 'https://www.kemkes.go.id/id/cegah-meningkatnya-diabetes-jangan-berlebihan-konsumsi-gula-garam-lemak',
];
