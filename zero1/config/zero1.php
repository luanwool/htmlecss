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
        // true = uploads em <pasta pública>/storage (hospedagem sem symlink).
        'public_disk_in_public' => (bool) env('PUBLIC_DISK_IN_PUBLIC', false),
        'widths' => [480, 960, 1600],
        'quality' => 82,
    ],

    'security' => [
        // Content-Security-Policy ativa por padrão fora do ambiente local.
        'csp' => env('SECURITY_CSP', true),
        'hsts' => env('SECURITY_HSTS', true),
    ],

    'payments' => [
        /*
        | Driver do gateway. Nesta versão só existe "simulated" (nenhum dinheiro real circula).
        | A Fase seguinte adiciona "mercadopago" implementando App\Services\Payments\PaymentGatewayInterface.
        */
        'driver' => env('PAYMENT_GATEWAY', 'simulated'),
        // Bloqueio de segurança: o simulador não roda em produção, salvo liberação explícita (homologação).
        'allow_simulated_in_production' => (bool) env('ALLOW_SIMULATED_PAYMENTS', false),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', env('APP_KEY')),
        'simulated' => [
            'fee_percent' => 4.99,  // taxa fictícia só para exercitar bruto/líquido nos relatórios
            'card_renewal_outcome' => env('SIMULATED_CARD_RENEWAL', 'approved'), // approved | rejected
        ],
    ],
];
