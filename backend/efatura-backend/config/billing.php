<?php

return [
    'invoice_cost_try' => (float) env('BILLING_INVOICE_COST_TRY', 1.00),
    'voucher_cost_try' => (float) env('BILLING_VOUCHER_COST_TRY', 1.00),
    'despatch_cost_try' => (float) env('BILLING_DESPATCH_COST_TRY', 1.00),
    // Doküman başına düşülecek e‑Kontör miktarı
    'doc_cost_credits' => [
        'invoice' => (int) env('BILLING_DOC_CREDITS_INVOICE', 1),
        'voucher' => (int) env('BILLING_DOC_CREDITS_VOUCHER', 1),
        'despatch' => (int) env('BILLING_DOC_CREDITS_DESPATCH', 1),
    ],
    // Havuz sahibi organizasyon (provider pool): Tüm kontör bu org üzerinden düşer
    'owner_organization_id' => (int) env('BILLING_OWNER_ORG_ID', 6),
    // Müşteriye satılan paketler (kontör adedi ve fiyatı)
    'credit_packages' => [
        ['code' => 'CRD_100',  'name' => '100 e‑Kontör',   'credits' => 100,   'price_try' => 240,  'tag' => null],
        ['code' => 'CRD_200',  'name' => '200 e‑Kontör',   'credits' => 200,   'price_try' => 390,  'tag' => null],
        ['code' => 'CRD_500',  'name' => '500 e‑Kontör',   'credits' => 500,   'price_try' => 870, 'tag' => 'Avantaj paketi'],
        ['code' => 'CRD_1K',   'name' => '1000 e‑Kontör',  'credits' => 1000,  'price_try' => 1430, 'tag' => null],
        ['code' => 'CRD_2_5K', 'name' => '2500 e‑Kontör',  'credits' => 2500,  'price_try' => 3160, 'tag' => null],
        ['code' => 'CRD_5K',   'name' => '5000 e‑Kontör',  'credits' => 5000,  'price_try' => 5560, 'tag' => null],
    ],
];

