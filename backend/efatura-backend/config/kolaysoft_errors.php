<?php

return [
    'codes' => [
        '000' => 'İşlem başarılı',
        '100' => 'Parametre hatası: Alanları ve formatı kontrol edin.',
        '200' => 'Yetki/Tanım hatası: Alias veya kullanıcı yetkilerini doğrulayın.',
        '300' => 'Servis geçici olarak kullanılamıyor. Lütfen tekrar deneyin.',
        '400' => 'UBL şema hatası: XML içeriğini doğrulayın.',
        '401' => 'Kimlik doğrulama hatası: kullanıcı adı/şifre.',
        '403' => 'Yetkisiz işlem: erişim izni reddedildi.',
        '404' => 'Kayıt bulunamadı.',
        '409' => 'Çakışma: Aynı belge daha önce işlenmiş olabilir.',
        '422' => 'Geçersiz veri: zorunlu alanlar eksik veya hatalı.',
        '500' => 'Servis hatası: Kolaysoft yanıt veremedi.',
        '503' => 'Servis kullanılamıyor: bakım veya yoğunluk.',
        // Yaygın özel durumlar
        '901' => 'Profil/alias hatası: E-Fatura için alıcı alias (URN) gerekli.',
        '902' => 'E-posta gerekli: E-Arşiv gönderiminde alıcı e-posta eksik.',
        '903' => 'Vergi numarası hatalı: VKN/TCKN kontrol edin.',
        '904' => 'Belge ID formatı geçersiz: Örn. ABC2025123456789',
        '905' => 'XML boş veya okunamadı.',
    ],
    // Metin tabanlı eşleme (explanation/cause içinde geçenler)
    'patterns' => [
        '/parametre/i' => 'Parametre hatası: Alanları ve formatı kontrol edin.',
        '/yetki|authorization|unauthorized/i' => 'Yetki/Tanım hatası: Alias veya kullanıcı yetkilerini doğrulayın.',
        '/urn|alias.*(bulunam|geçersiz)/i' => 'Profil/alias hatası: E-Fatura için alıcı alias (URN) gerekli.',
        '/e-?posta|email.*(gerekli|eksik)/i' => 'E-posta gerekli: E-Arşiv gönderiminde alıcı e-posta eksik.',
        '/vkn|tckn.*(geçersiz|hatalı)/i' => 'Vergi numarası hatalı: VKN/TCKN kontrol edin.',
        '/ubl|xsd|schema/i' => 'UBL şema hatası: XML içeriğini doğrulayın.',
        '/tekrar|duplicate|çakışma/i' => 'Çakışma: Aynı belge daha önce işlenmiş olabilir.',
        '/document\s*id|belge\s*id.*geçersiz/i' => 'Belge ID formatı geçersiz: Örn. ABC2025123456789',
        '/servis.*(yoğun|meşgul|bakım)/i' => 'Servis kullanılamıyor: bakım veya yoğunluk.',
        '/xml.*(boş|empty|parse)/i' => 'XML boş veya okunamadı.',
    ],
];


