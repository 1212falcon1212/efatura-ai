<?php

namespace App\Services\UBL;

use App\Models\Invoice;
use App\Models\Customer;

class UblBuilder
{
    private static function esc(?string $v): string { return htmlspecialchars((string)$v ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8'); }

    public function buildInvoiceXML(Invoice $invoice, string $documentUUID, ?string $documentId = null): string
    {
        $customer = $invoice->customerRecord ?? new Customer((array)($invoice->customer ?? []));

        $meta = $invoice->metadata ?? [];
        $currency = $meta['currency'] ?? 'TRY';
        // ProfileID: e_arsiv -> EARSIVFATURA, e_fatura -> TEMELFATURA (varsayılan)
        $profile = strtoupper((string)($meta['scenario'] ?? 'TEMEL'));
        if (($invoice->type ?? null) === 'e_arsiv') { $profile = 'EARSIVFATURA'; }
        elseif ($profile === 'TEMEL') { $profile = 'TEMELFATURA'; }
        $typeCode = strtoupper((string)($meta['invoiceKind'] ?? 'SATIS'));
        $issueDate = optional($invoice->issue_date)->format('Y-m-d') ?: date('Y-m-d');
        $issueTime = (string) ($meta['issueTime'] ?? date('H:i:s'));
        // Saat formatını HH:mm:ss'e normalize et (ör. 15:20 -> 15:20:00)
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $issueTime)) {
            if (preg_match('/^\d{2}:\d{2}$/', $issueTime)) {
                $issueTime = $issueTime . ':00';
            } else {
                $ts = strtotime($issueTime);
                $issueTime = $ts ? date('H:i:s', $ts) : date('H:i:s');
            }
        }
        $id = 'INV-'.$invoice->id;

        $supplierName = data_get($invoice->customer, 'seller.name') ?? data_get($invoice->metadata, 'supplier.name') ?? 'Tedarikçi';
        $customerName = data_get($invoice->customer, 'name') ?? 'Müşteri';
        $supplierIdRaw = (string) (config('kolaysoft.source_id') ?? '');
        $supplierScheme = (strlen($supplierIdRaw) === 11 ? 'TCKN' : 'VKN');
        
        // Alıcı kimliği
        $customerIdRaw = (string) ($customer->tckn_vkn ?? '');
        $customerScheme = (strlen($customerIdRaw) === 11 ? 'TCKN' : (strlen($customerIdRaw) === 10 ? 'VKN' : 'VKN'));
        
        // e-Arşiv için alıcı kimliği zorunlu; yoksa 11111111111 (TCKN) ile defaultla
        if ((($invoice->type ?? null) === 'e_arsiv') && $customerIdRaw === '') {
            $customerIdRaw = '11111111111';
            $customerScheme = 'TCKN';
        }

        $items = is_array($invoice->items) ? $invoice->items : [];
        $subtotal = 0.0;
        $vatTotal = 0.0;
        $payableAmount = 0.0;

        foreach ($items as $it) {
            $quantity = (float)($it['quantity'] ?? 1);
            $unitPrice = (float)($it['unit_price']['amount'] ?? 0);
            $vatRate = (float)($it['vat_rate'] ?? 0);

            $lineTotal = $quantity * $unitPrice;
            $vatAmount = $lineTotal * ($vatRate / 100);

            $subtotal += $lineTotal;
            $vatTotal += $vatAmount;
        }
        $payableAmount = $subtotal + $vatTotal;


        // KDV oranlarına göre gruplanmış TaxSubtotal'lar oluşturulmalı
        $taxSubtotals = [];
        foreach ($items as $it) {
            $quantity = (float)($it['quantity'] ?? 1);
            $unitPrice = (float)($it['unit_price']['amount'] ?? 0);
            $vatRate = (float)($it['vat_rate'] ?? 0);
            $lineTotal = $quantity * $unitPrice;

            if (!isset($taxSubtotals[(string)$vatRate])) {
                $taxSubtotals[(string)$vatRate] = [
                    'taxable_amount' => 0.0,
                    'tax_amount' => 0.0,
                ];
            }
            $taxSubtotals[(string)$vatRate]['taxable_amount'] += $lineTotal;
            $taxSubtotals[(string)$vatRate]['tax_amount'] += $lineTotal * ($vatRate / 100);
        }

        // Tedarikçi (bizim firma) detayları - config'den veya .env'den alınabilir
        $supplier = config('kolaysoft.supplier_details', [
            'name' => 'ÇETİN TEKNOLOJİ LİMİTED ŞİRKETİ',
            'vkn' => '2451007100',
            'street' => 'ertuğrul',
            'city_subdivision' => 'Ataşehir',
            'city' => 'İstanbul',
            'postal_zone' => '34000',
            'country' => 'Türkiye',
            'tax_scheme' => 'KOZYATAĞI VERGİ DAİRESİ MÜDÜRLÜĞÜ',
            'phone' => '+905452054545',
            'email' => 'taner@lazimbana.com'
        ]);
        // Organizasyon ayarları ile tedarikçi (gönderici) bilgilerini override et
        $org = \App\Models\Organization::find($invoice->organization_id);
        $orgSettings = $org?->settings ?? [];
        if (!empty($orgSettings)) {
            $supplier['name'] = (string) ($orgSettings['company_title'] ?? $org->name ?? $supplier['name']);
            $orgVkn = (string) ($orgSettings['vkn'] ?? '');
            $orgTckn = (string) ($orgSettings['tckn'] ?? '');
            $supplier['vkn'] = $orgVkn !== '' ? $orgVkn : ($orgTckn !== '' ? $orgTckn : $supplier['vkn']);
            $supplier['street'] = (string) ($orgSettings['address'] ?? $supplier['street']);
            $supplier['city_subdivision'] = (string) ($orgSettings['district'] ?? $supplier['city_subdivision']);
            $supplier['city'] = (string) ($orgSettings['city'] ?? $supplier['city']);
            $supplier['tax_scheme'] = (string) ($orgSettings['tax_office'] ?? $supplier['tax_scheme']);
            $supplier['phone'] = (string) ($orgSettings['phone'] ?? $supplier['phone']);
        }
        $supplierName = (string) $supplier['name'];
        $supplierIdRaw = (string) $supplier['vkn'];
        $supplierScheme = (strlen($supplierIdRaw) === 11 ? 'TCKN' : 'VKN');
        $supplierEmail = (string) ($orgSettings['company_email'] ?? $supplier['email'] ?? '');
        $supplierPostal = (string) ($orgSettings['postal_code'] ?? $supplier['postal_zone'] ?? '');
        $supplierCountry = (string) ($orgSettings['country'] ?? $supplier['country'] ?? 'Türkiye');


        // Alıcı (müşteri) detayları
        $customerFullName = trim(($customer->name ?? '') . ' ' . ($customer->surname ?? ''));
        $customerStreet = (string) ($customer->street_address ?? ''); // street_address olarak güncellendi
        $customerCity = (string) ($customer->city ?? '');
        $customerDistrict = (string) ($customer->district ?? '');
        $customerEmail = (string) ($customer->email ?? '');

        // Sipariş referansı
        $orderNo = (string) ($meta['orderNumber'] ?? ($invoice->id ? (string)$invoice->id : ''));
        $orderDate = (string) ($meta['orderDate'] ?? $issueDate);

        // Kargo bilgileri (Delivery)
        $cargo = is_array($meta['cargo'] ?? null) ? $meta['cargo'] : null;
        $cargoCompany = (string) ($cargo['company'] ?? '');
        $cargoVkn = (string) ($cargo['vkn'] ?? '');
        $cargoDate = (string) ($cargo['date'] ?? $issueDate);

        // Ödeme/internet satış ekleri
        $internetSaleBlock = null;
        $inet = is_array($meta['internetSale'] ?? null) ? $meta['internetSale'] : [];
        if (!empty($inet['webAddress'])) {
            $internetSaleBlock = $inet;
            $internetSaleBlock['paymentType'] = $inet['paymentType'] ?? 'KREDIKARTI/BANKAKARTI';
            $internetSaleBlock['platform'] = $inet['platform'] ?? '';
            $internetSaleBlock['date'] = $inet['date'] ?? $issueDate;
        }


        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"';
        $xml[] = ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"';
        $xml[] = ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"';
        $xml[] = ' xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"';
        $xml[] = ' xmlns:ds="http://www.w3.org/2000/09/xmldsig#"';
        $xml[] = ' xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"';
        $xml[] = ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';

        // UBL Extensions with a placeholder for the signature element
        $xml[] = '  <ext:UBLExtensions>';
        $xml[] = '    <ext:UBLExtension>';
        $xml[] = '      <ext:ExtensionContent>';
        $xml[] = '        <ds:Signature>';
        $xml[] = '          <ds:SignedInfo>';
        $xml[] = '            <ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>';
        $xml[] = '            <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>';
        $xml[] = '            <ds:Reference URI="">';
        $xml[] = '              <ds:Transforms>';
        $xml[] = '                <ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>';
        $xml[] = '              </ds:Transforms>';
        $xml[] = '              <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>';
        $xml[] = '              <ds:DigestValue/>';
        $xml[] = '            </ds:Reference>';
        $xml[] = '          </ds:SignedInfo>';
        $xml[] = '          <ds:SignatureValue/>';
        $xml[] = '          <ds:KeyInfo>';
        $xml[] = '            <ds:KeyValue>';
        $xml[] = '              <ds:RSAKeyValue>';
        $xml[] = '                <ds:Modulus/>';
        $xml[] = '                <ds:Exponent/>';
        $xml[] = '              </ds:RSAKeyValue>';
        $xml[] = '            </ds:KeyValue>';
        $xml[] = '            <ds:X509Data>';
        $xml[] = '                <ds:X509Certificate/>';
        $xml[] = '            </ds:X509Data>';
        $xml[] = '          </ds:KeyInfo>';
        $xml[] = '          <ds:Object>';
        $xml[] = '            <xades:QualifyingProperties Target="#Signature_'.self::esc($documentUUID).'">';
        $xml[] = '              <xades:SignedProperties Id="SignedProperties_'.self::esc($documentUUID).'">';
        $xml[] = '                <xades:SignedSignatureProperties>';
        $xml[] = '                  <xades:SigningTime>'.(new \DateTime())->format('c').'</xades:SigningTime>';
        $xml[] = '                  <xades:SigningCertificate>';
        $xml[] = '                    <xades:Cert>';
        $xml[] = '                      <xades:CertDigest>';
        $xml[] = '                        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>';
        $xml[] = '                        <ds:DigestValue/>';
        $xml[] = '                      </xades:CertDigest>';
        $xml[] = '                      <xades:IssuerSerial>';
        $xml[] = '                        <ds:X509IssuerName/>';
        $xml[] = '                        <ds:X509SerialNumber>0</ds:X509SerialNumber>';
        $xml[] = '                      </xades:IssuerSerial>';
        $xml[] = '                    </xades:Cert>';
        $xml[] = '                  </xades:SigningCertificate>';
        $xml[] = '                </xades:SignedSignatureProperties>';
        $xml[] = '              </xades:SignedProperties>';
        $xml[] = '            </xades:QualifyingProperties>';
        $xml[] = '          </ds:Object>';
        $xml[] = '        </ds:Signature>';
        $xml[] = '      </ext:ExtensionContent>';
        $xml[] = '    </ext:UBLExtension>';
        $xml[] = '  </ext:UBLExtensions>';

        $xml[] = '  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>';
        $xml[] = '  <cbc:CustomizationID>TR1.2</cbc:CustomizationID>';
        $xml[] = '  <cbc:ProfileID>'.self::esc($profile).'</cbc:ProfileID>';
        $xml[] = '  <cbc:ID>'.self::esc($documentId ?: $id).'</cbc:ID>';
        $xml[] = '  <cbc:CopyIndicator>false</cbc:CopyIndicator>';
        $xml[] = '  <cbc:UUID>'.self::esc($documentUUID).'</cbc:UUID>';
        $xml[] = '  <cbc:IssueDate>'.self::esc($issueDate).'</cbc:IssueDate>';
        $xml[] = '  <cbc:IssueTime>'.self::esc($issueTime).'</cbc:IssueTime>';
        $xml[] = '  <cbc:InvoiceTypeCode>'.self::esc($typeCode).'</cbc:InvoiceTypeCode>';
        $xml[] = '  <cbc:Note>İrsaliye yerine geçer.</cbc:Note>';
        $xml[] = '  <cbc:DocumentCurrencyCode>'.self::esc($currency).'</cbc:DocumentCurrencyCode>';
        $xml[] = '  <cbc:LineCountNumeric>'.self::esc((string) count($items)).'</cbc:LineCountNumeric>';

        // Sipariş referansı
        if ($orderNo !== '') {
            $xml[] = '  <cac:OrderReference>';
            $xml[] = '    <cbc:ID>'.self::esc($orderNo).'</cbc:ID>';
            if ($orderDate !== '') { $xml[] = '    <cbc:IssueDate>'.self::esc($orderDate).'</cbc:IssueDate>'; }
            $xml[] = '  </cac:OrderReference>';
        }

        // AdditionalDocumentReference (XSLT - entegratör ekleyebilir, biz boş bırakabiliriz)
        // ...

        // e-Arşiv: Gönderim şekli ve internet satış ekleri
        if (($invoice->type ?? null) === 'e_arsiv') {
            // Gönderim şekli: ELEKTRONIK
            $xml[] = '  <cac:AdditionalDocumentReference>';
            $xml[] = '    <cbc:ID>'.(string) \Illuminate\Support\Str::uuid().'</cbc:ID>';
            $xml[] = '    <cbc:IssueDate>'.self::esc($issueDate).'</cbc:IssueDate>';
            $xml[] = '    <cbc:DocumentTypeCode>GONDERIM_SEKLI</cbc:DocumentTypeCode>';
            $xml[] = '    <cbc:DocumentType>ELEKTRONIK</cbc:DocumentType>';
            $xml[] = '  </cac:AdditionalDocumentReference>';

            // İnternet Satış Bilgileri (metadata'dan okunacak) - Örnek XML'e göre güncellendi
            $internetSale = $meta['internetSale'] ?? null;
            if ($internetSale) {
                $webAddress = $internetSale['webAddress'] ?? '';
                $paymentPlatform = $internetSale['paymentPlatform'] ?? '';
                // Ödeme türü için GİB'in belirlediği standart kodlar kullanılır. Arayüzden gelen değere göre eşleştirme yapılabilir.
                // Örnek: KREDIKARTI/BANKAKARTI, EFT/HAVALE, KAPIDAODEME, ODEMEARACISI, DIGER
                $paymentType = $internetSale['paymentType'] ?? 'DIGER';
                $paymentDate = $internetSale['paymentDate'] ?? '';

                $xml[] = '  <cac:AdditionalDocumentReference>';
                $xml[] = '    <cbc:ID>INTERNET_SATIS</cbc:ID>';
                $xml[] = '    <cbc:IssueDate>'.self::esc($issueDate).'</cbc:IssueDate>';
    	        $xml[] = '    <cbc:DocumentTypeCode>ODEME_SEKLI</cbc:DocumentTypeCode>';
                $xml[] = '    <cbc:DocumentType>'.self::esc($paymentType).'</cbc:DocumentType>';
                $xml[] = '    <cac:IssuerParty>';
                // cbc elemanlar CAC gruplarından önce gelmeli
                $xml[] = '      <cbc:WebsiteURI>'.self::esc($webAddress).'</cbc:WebsiteURI>';
                $xml[] = '      <cac:PartyIdentification><cbc:ID/></cac:PartyIdentification>';
                $xml[] = '      <cac:PartyName><cbc:Name/></cac:PartyName>';
                $xml[] = '      <cac:PostalAddress>';
                $xml[] = '        <cbc:CitySubdivisionName/>';
                $xml[] = '        <cbc:CityName/>';
                $xml[] = '        <cac:Country><cbc:Name/></cac:Country>';
                $xml[] = '      </cac:PostalAddress>';
                $xml[] = '    </cac:IssuerParty>';
                $xml[] = '  </cac:AdditionalDocumentReference>';
            }
        }

        // Signature - Tedarikçi ve Müşteri'den önce gelmeli
        $xml[] = '  <cac:Signature>';
        $xml[] = '    <cbc:ID schemeID="VKN_TCKN">'.self::esc($supplierIdRaw).'</cbc:ID>';
        $xml[] = '    <cac:SignatoryParty>';
        $xml[] = '      <cac:PartyIdentification><cbc:ID schemeID="'.self::esc($supplierScheme).'">'.self::esc($supplierIdRaw).'</cbc:ID></cac:PartyIdentification>';
        $xml[] = '      <cac:PostalAddress>';
        $xml[] = '        <cbc:CitySubdivisionName>'.self::esc($supplier['city_subdivision']).'</cbc:CitySubdivisionName>';
        $xml[] = '        <cbc:CityName>'.self::esc($supplier['city']).'</cbc:CityName>';
        $xml[] = '        <cac:Country><cbc:Name>'.self::esc($supplier['country']).'</cbc:Name></cac:Country>';
        $xml[] = '      </cac:PostalAddress>';
        $xml[] = '    </cac:SignatoryParty>';
        $xml[] = '    <cac:DigitalSignatureAttachment><cac:ExternalReference><cbc:URI>#Signature_'.self::esc($documentUUID).'</cbc:URI></cac:ExternalReference></cac:DigitalSignatureAttachment>';
        $xml[] = '  </cac:Signature>';

        // Supplier party - Gönderici (Biz)
        $xml[] = '  <cac:AccountingSupplierParty>';
        $xml[] = '    <cac:Party>';
        $xml[] = '      <cac:PartyIdentification><cbc:ID schemeID="'.self::esc($supplierScheme).'">'.self::esc($supplierIdRaw).'</cbc:ID></cac:PartyIdentification>';
        $xml[] = '      <cac:PartyName><cbc:Name>'.self::esc($supplierName).'</cbc:Name></cac:PartyName>';
        $xml[] = '      <cac:PostalAddress>';
        $xml[] = '        <cbc:StreetName>'.self::esc($supplier['street']).'</cbc:StreetName>';
        $xml[] = '        <cbc:CitySubdivisionName>'.self::esc($supplier['city_subdivision']).'</cbc:CitySubdivisionName>';
        $xml[] = '        <cbc:CityName>'.self::esc($supplier['city']).'</cbc:CityName>';
        $xml[] = '        <cbc:PostalZone>'.self::esc($supplierPostal).'</cbc:PostalZone>';
        $xml[] = '        <cac:Country><cbc:Name>'.self::esc($supplierCountry).'</cbc:Name></cac:Country>';
        $xml[] = '      </cac:PostalAddress>';
        $xml[] = '      <cac:PartyTaxScheme><cac:TaxScheme><cbc:Name>'.self::esc($supplier['tax_scheme']).'</cbc:Name></cac:TaxScheme></cac:PartyTaxScheme>';
        $xml[] = '      <cac:Contact>';
        $xml[] = '        <cbc:Telephone>'.self::esc($supplier['phone']).'</cbc:Telephone>';
        $xml[] = '        <cbc:ElectronicMail>'.self::esc($supplierEmail).'</cbc:ElectronicMail>';
        $xml[] = '      </cac:Contact>';
        $xml[] = '    </cac:Party>';
        $xml[] = '  </cac:AccountingSupplierParty>';

        // Customer party - Alıcı (Müşteri)
        $xml[] = '  <cac:AccountingCustomerParty>';
        $xml[] = '    <cac:Party>';
        $xml[] = '      <cac:PartyIdentification><cbc:ID schemeID="'.self::esc($customerScheme).'">'.self::esc($customerIdRaw).'</cbc:ID></cac:PartyIdentification>';

        if ($customerScheme === 'VKN') {
            if (!empty($customerFullName)) {
                $xml[] = '      <cac:PartyName>';
                $xml[] = '        <cbc:Name>'.self::esc($customerFullName).'</cbc:Name>';
                $xml[] = '      </cac:PartyName>';
            }
        }

        $xml[] = '      <cac:PostalAddress>';
        $xml[] = '        <cbc:StreetName>'.self::esc($customerStreet).'</cbc:StreetName>';
        $xml[] = '        <cbc:CitySubdivisionName>'.self::esc($customerDistrict).'</cbc:CitySubdivisionName>';
        $xml[] = '        <cbc:CityName>'.self::esc($customerCity).'</cbc:CityName>';
        $xml[] = '        <cac:Country><cbc:Name>Türkiye</cbc:Name></cac:Country>';
        $xml[] = '      </cac:PostalAddress>';
        $xml[] = '      <cac:PartyTaxScheme><cac:TaxScheme><cbc:Name>'.self::esc($customer->tax_office ?? '').'</cbc:Name></cac:TaxScheme></cac:PartyTaxScheme>';
        $xml[] = '      <cac:Contact>';
        $xml[] = '        <cbc:ElectronicMail>'.self::esc($customerEmail).'</cbc:ElectronicMail>';
        $xml[] = '      </cac:Contact>';

        if ($customerScheme === 'TCKN') {
            // TCKN için <Person> zorunludur ve diğer elemanlardan sonra gelmelidir.
            // Ad/soyad boşsa, GİB'in önerdiği gibi "AD SOYAD" yer tutucularını kullan.
            $firstName = $customer->name ?? '';
            $familyName = $customer->surname ?? '';

            if (trim($firstName) === '' && trim($familyName) === '') {
                $firstName = 'AD';
                $familyName = 'SOYAD';
            }

            $xml[] = '      <cac:Person>';
            $xml[] = '        <cbc:FirstName>'.self::esc($firstName).'</cbc:FirstName>';
            $xml[] = '        <cbc:FamilyName>'.self::esc($familyName).'</cbc:FamilyName>';
            $xml[] = '      </cac:Person>';
        }

        $xml[] = '    </cac:Party>';
        $xml[] = '  </cac:AccountingCustomerParty>';

        // Kargo/Delivery block
        $delivery = $invoice->metadata['cargo'] ?? null;
        if ($delivery) {
            $carrierParty = $delivery['carrierParty'] ?? ($delivery['company'] ? ['name' => $delivery['company'], 'vkn' => $delivery['vkn']] : null);
            $deliveryDate = $delivery['actualDeliveryDate'] ?? $delivery['date'] ?? null;

            if ($carrierParty || $deliveryDate) {
                $xml[] = '  <cac:Delivery>';
                if ($deliveryDate) {
                    $xml[] = '    <cbc:ActualDeliveryDate>'.self::esc($deliveryDate).'</cbc:ActualDeliveryDate>';
                }
                if ($carrierParty) {
                    $carrierName = $carrierParty['name'] ?? null;
                    $carrierVkn = $carrierParty['vkn'] ?? null;

                    if ($carrierName || $carrierVkn) {
                        $xml[] = '    <cac:CarrierParty>';
                        if ($carrierVkn) {
                            $xml[] = '      <cac:PartyIdentification><cbc:ID schemeID="VKN">'.self::esc($carrierVkn).'</cbc:ID></cac:PartyIdentification>';
                        }
                        if ($carrierName) {
                            $xml[] = '      <cac:PartyName><cbc:Name>'.self::esc($carrierName).'</cbc:Name></cac:PartyName>';
                        }
                        // Örnek XML'e uyum için boş PostalAddress ekleniyor
                        $xml[] = '      <cac:PostalAddress>';
                        $xml[] = '        <cbc:CitySubdivisionName/>';
                        $xml[] = '        <cbc:CityName/>';
                        $xml[] = '        <cac:Country><cbc:Name/></cac:Country>';
                        $xml[] = '      </cac:PostalAddress>';
                        $xml[] = '    </cac:CarrierParty>';
                    }
                }
                $xml[] = '  </cac:Delivery>';
            }
        }

        // AllowanceCharge (İskonto/Arttırım) - Şimdilik sıfır
        $xml[] = '  <cac:AllowanceCharge>';
        $xml[] = '    <cbc:ChargeIndicator>false</cbc:ChargeIndicator>';
        $xml[] = '    <cbc:Amount currencyID="'.self::esc($currency).'">0</cbc:Amount>';
        $xml[] = '  </cac:AllowanceCharge>';

        $xml[] = '  <cac:TaxTotal>';
        $xml[] = '    <cbc:TaxAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($vatTotal, 2, '.', '')).'</cbc:TaxAmount>';

        foreach ($taxSubtotals as $rate => $amounts) {
            $xml[] = '    <cac:TaxSubtotal>';
            $xml[] = '      <cbc:TaxableAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($amounts['taxable_amount'], 2, '.', '')).'</cbc:TaxableAmount>';
            $xml[] = '      <cbc:TaxAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($amounts['tax_amount'], 2, '.', '')).'</cbc:TaxAmount>';
            $xml[] = '      <cbc:Percent>'.self::esc($rate).'</cbc:Percent>';
            $xml[] = '      <cac:TaxCategory>';
            $xml[] = '        <cac:TaxScheme>';
            $xml[] = '          <cbc:Name>GERÇEK USULDE KATMA DEĞER VERGİSİ</cbc:Name>';
            $xml[] = '          <cbc:TaxTypeCode>0015</cbc:TaxTypeCode>';
            $xml[] = '        </cac:TaxScheme>';
            $xml[] = '      </cac:TaxCategory>';
            $xml[] = '    </cac:TaxSubtotal>';
        }

        $xml[] = '  </cac:TaxTotal>';

        $xml[] = '  <cac:LegalMonetaryTotal>';
        $xml[] = '    <cbc:LineExtensionAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($subtotal, 2, '.', '')).'</cbc:LineExtensionAmount>';
        $xml[] = '    <cbc:TaxExclusiveAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($subtotal, 2, '.', '')).'</cbc:TaxExclusiveAmount>';
        $xml[] = '    <cbc:TaxInclusiveAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($payableAmount, 2, '.', '')).'</cbc:TaxInclusiveAmount>';
        $xml[] = '    <cbc:AllowanceTotalAmount currencyID="TRY">0.00</cbc:AllowanceTotalAmount>';
        $xml[] = '    <cbc:PayableAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($payableAmount, 2, '.', '')).'</cbc:PayableAmount>';
        $xml[] = '  </cac:LegalMonetaryTotal>';

        $lineCounter = 0;
        foreach ($items as $it) {
            $lineCounter++;
            $name = (string)($it['name'] ?? 'Satır');
            $quantity = (float)($it['quantity'] ?? 1);
            $unit = (string)($it['unit'] ?? 'C62');
            // Birim kod haritalama (Adet->C62, Kg->KGM, Lt->LTR, Metre->MTR)
            $unitUpper = mb_strtoupper($unit);
            $unitCode = match ($unitUpper) {
                'ADET' => 'C62', 'C62' => 'C62',
                'KG', 'KGM' => 'KGM',
                'LT', 'LTR' => 'LTR',
                'METRE', 'M' => 'MTR', 'MTR' => 'MTR',
                default => 'C62',
            };
            $unitPrice = (float)($it['unit_price']['amount'] ?? 0);
            $vatRate = (float)($it['vat_rate'] ?? 0);
            $lineTotal = $quantity * $unitPrice;
            $vatAmount = $lineTotal * ($vatRate / 100);

            $xml[] = '  <cac:InvoiceLine>';
            $xml[] = '    <cbc:ID>'.self::esc($lineCounter).'</cbc:ID>';
            $xml[] = '    <cbc:InvoicedQuantity unitCode="'.self::esc($unitCode).'">'.self::esc(number_format($quantity, 2, '.', '')).'</cbc:InvoicedQuantity>';
            $xml[] = '    <cbc:LineExtensionAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($lineTotal, 2, '.', '')).'</cbc:LineExtensionAmount>';
            $xml[] = '    <cac:TaxTotal>';
            $xml[] = '      <cbc:TaxAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($vatAmount, 2, '.', '')).'</cbc:TaxAmount>';
            $xml[] = '      <cac:TaxSubtotal>';
            $xml[] = '        <cbc:TaxableAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($lineTotal, 2, '.', '')).'</cbc:TaxableAmount>';
            $xml[] = '        <cbc:TaxAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($vatAmount, 2, '.', '')).'</cbc:TaxAmount>';
            $xml[] = '        <cbc:Percent>'.self::esc((string)$vatRate).'</cbc:Percent>';
            $xml[] = '        <cac:TaxCategory>';
            $xml[] = '          <cac:TaxScheme>';
            $xml[] = '            <cbc:Name>GERÇEK USULDE KATMA DEĞER VERGİSİ</cbc:Name>';
            $xml[] = '            <cbc:TaxTypeCode>0015</cbc:TaxTypeCode>';
            $xml[] = '          </cac:TaxScheme>';
            $xml[] = '        </cac:TaxCategory>';
            $xml[] = '      </cac:TaxSubtotal>';
            $xml[] = '    </cac:TaxTotal>';
            $xml[] = '    <cac:Item>';
            $xml[] = '      <cbc:Name>'.self::esc($name).'</cbc:Name>';
            if (!empty($it['sku'])) {
                $xml[] = '      <cac:SellersItemIdentification>';
                $xml[] = '        <cbc:ID>'.self::esc($it['sku']).'</cbc:ID>';
                $xml[] = '      </cac:SellersItemIdentification>';
            }
            $xml[] = '    </cac:Item>';
            $xml[] = '    <cac:Price>';
            $xml[] = '      <cbc:PriceAmount currencyID="'.self::esc($currency).'">'.self::esc(number_format($unitPrice, 2, '.', '')).'</cbc:PriceAmount>';
            $xml[] = '    </cac:Price>';
            $xml[] = '  </cac:InvoiceLine>';
        }

        $xml[] = '</Invoice>';
        return implode("\n", $xml);
    }
}


