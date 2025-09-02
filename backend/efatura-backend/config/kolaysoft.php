<?php

return [
    // Genel (kolayWS) servis URL'i
    'base_url' => env('KOLAYSOFT_BASE', 'https://service.smartdonusum.com/kolayWS'),
    // E-Arşiv özel servis URL'i
    'archive_base_url' => env('KOLAYSOFT_EARCHIVE_BASE', 'https://service.smartdonusum.com/EArchiveInvoiceService/EArchiveInvoiceWS'),
    // E-Fatura özel servis URL'i
    'einvoice_base_url' => env('KOLAYSOFT_EINVOICE_BASE', 'https://service.smartdonusum.com/InvoiceService/InvoiceWS'),
    // E-Fatura Sorgulama (Query) servis URL'i
    'query_base_url' => env('KOLAYSOFT_QUERY_BASE', 'https://service.smartdonusum.com/QueryInvoiceService/QueryDocumentWS'),
    // E-Fatura Yükleme (LoadInvoiceWS) servis URL'i
    'loading_base_url' => env('KOLAYSOFT_LOADING_BASE', 'https://service.smartdonusum.com/InvoiceLoadingService/LoadInvoiceWS'),
    // E-Makbuz (e-SMM) servis URL'i
    'ereceipt_base_url' => env('KOLAYSOFT_ERECEIPT_BASE', 'https://service.smartdonusum.com/EArchiveVoucherService/EArchiveVoucherWS'),
    // E-İrsaliye (e-Despatch) servis URL'i
    'edespatch_base_url' => env('KOLAYSOFT_EDESPATCH_BASE', 'https://service.smartdonusum.com/DespatchService/DespatchWS'),
    // E-İrsaliye Sorgu (QueryDespatchWS) servis URL'i
    'despatch_query_base_url' => env('KOLAYSOFT_EDESPATCH_QUERY_BASE', 'https://service.smartdonusum.com/QueryDespatchService/QueryDespatchWS'),
    'username' => env('KOLAYSOFT_USERNAME', ''),
    'password' => env('KOLAYSOFT_PASSWORD', ''),
    // Opsiyonel: E-Arşiv servisi için ayrı kimlik bilgileri
    'earchive_username' => env('KOLAYSOFT_EARCHIVE_USERNAME', null),
    'earchive_password' => env('KOLAYSOFT_EARCHIVE_PASSWORD', null),
    'timeout' => (int) env('KOLAYSOFT_TIMEOUT', 10),
    // Büyük içerikler (ZIP, XML) için okuma zaman aşımı (saniye)
    'read_timeout' => (int) env('KOLAYSOFT_READ_TIMEOUT', 60),
    // Booleans: string 'false' / 'true' değerlerini doğru parse et
    'mock' => filter_var(env('KOLAYSOFT_MOCK', false), FILTER_VALIDATE_BOOLEAN),
    'debug' => filter_var(env('KOLAYSOFT_DEBUG', true), FILTER_VALIDATE_BOOLEAN),
    'source_id' => env('KOLAYSOFT_SOURCE_ID'),
    // Gönderici etiket (GB/PK) — e-İrsaliye için sourceUrn bu etikettir
    'source_urn' => env('KOLAYSOFT_SOURCE_URN', ''),
    // E-Arşiv gönderimleri için özel kaynak etiket (opsiyonel)
    'source_urn_earchive' => env('KOLAYSOFT_SOURCE_URN_EARCHIVE', ''),
    // E-Arşiv gönderimini InvoiceWS.SendInvoice üzerinden zorla
    'force_invoicews_for_earchive' => filter_var(env('KOLAYSOFT_FORCE_INVOICEWS_FOR_EARCHIVE', false), FILTER_VALIDATE_BOOLEAN),
    // Test/varsayılan alıcı etiket (istenirse). Üretimde dinamik doldurulmalıdır
    'default_destination_urn' => env('KOLAYSOFT_DEFAULT_DESTINATION_URN', ''),

    // DocumentId formatı için 3 harfli önek (örn. ABC2019123456789)
    'document_id_prefix' => env('KOLAYSOFT_DOCUMENT_ID_PREFIX', 'ABC'),
    // Geliştirme aşamasında SSL doğrulamasını kapatma opsiyonu (production'da false bırakın)
    'allow_insecure_ssl' => filter_var(env('KOLAYSOFT_ALLOW_INSECURE_SSL', false), FILTER_VALIDATE_BOOLEAN),
];

