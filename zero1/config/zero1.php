<?php

return [
    /*
    | Fuso horário do negócio. O banco e a aplicação trabalham em UTC (app.timezone);
    | toda exibição e todo cálculo de agenda usam este fuso.
    */
    'timezone' => env('BUSINESS_TIMEZONE', 'America/Sao_Paulo'),

    'currency' => 'BRL',

    /*
    | Quando true, a interface exibe um selo "Ambiente de demonstração".
    | O seeder de demonstração só roda fora de produção.
    */
    'demo_mode' => (bool) env('ZERO1_DEMO_MODE', false),

    'media' => [
        'disk' => env('MEDIA_DISK', 'public'),
        'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 6144),
        'widths' => [480, 960, 1600],
        'quality' => 82,
    ],

    'security' => [
        // Content-Security-Policy ativa por padrão fora do ambiente local.
        'csp' => env('SECURITY_CSP', true),
        'hsts' => env('SECURITY_HSTS', true),
    ],
];
