<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$allowInsecure = (bool) config('kolaysoft.allow_insecure_ssl', false);
$readTimeout   = (int) config('kolaysoft.read_timeout', 60);
$username      = (string) config('kolaysoft.username');
$password      = (string) config('kolaysoft.password');
$archiveBase   = (string) config('kolaysoft.archive_base_url');
$timeout       = (int) config('kolaysoft.timeout', 10);
$sourceId      = (string) config('kolaysoft.source_id');
$prefix        = strtoupper((string) (config('kolaysoft.document_id_prefix', 'ABC')));
$prefix        = preg_replace('/[^A-Z]/', '', $prefix) ?: 'ABC';
$prefix        = substr($prefix, 0, 3);
$uuid          = (string) Illuminate\Support\Str::uuid();
$docId         = $prefix . date('Y') . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
$issueDate     = date('Y-m-d');

$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="2.1">
  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
  <cbc:CustomizationID>TR1.2</cbc:CustomizationID>
  <cbc:ProfileID>EARSIVFATURA</cbc:ProfileID>
  <cbc:ID>{$docId}</cbc:ID>
  <cbc:CopyIndicator>false</cbc:CopyIndicator>
  <cbc:UUID>{$uuid}</cbc:UUID>
  <cbc:IssueDate>{$issueDate}</cbc:IssueDate>
  <cbc:InvoiceTypeCode>SATIS</cbc:InvoiceTypeCode>
  <cbc:DocumentCurrencyCode>TRY</cbc:DocumentCurrencyCode>
  <cbc:LineCountNumeric>1</cbc:LineCountNumeric>
  <cac:AccountingSupplierParty>
    <cac:Party>
      <cac:PartyIdentification><cbc:ID schemeID="VKN">{$sourceId}</cbc:ID></cac:PartyIdentification>
      <cac:PartyName><cbc:Name>Tedarikci</cbc:Name></cac:PartyName>
    </cac:Party>
  </cac:AccountingSupplierParty>
  <cac:AccountingCustomerParty>
    <cac:Party>
      <cac:PartyIdentification><cbc:ID schemeID="TCKN">11111111111</cbc:ID></cac:PartyIdentification>
      <cac:PartyName><cbc:Name>Musteri</cbc:Name></cac:PartyName>
    </cac:Party>
  </cac:AccountingCustomerParty>
  <cac:TaxTotal><cbc:TaxAmount currencyID="TRY">0.00</cbc:TaxAmount></cac:TaxTotal>
  <cac:LegalMonetaryTotal>
    <cbc:LineExtensionAmount currencyID="TRY">0.00</cbc:LineExtensionAmount>
    <cbc:TaxExclusiveAmount currencyID="TRY">0.00</cbc:TaxExclusiveAmount>
    <cbc:TaxInclusiveAmount currencyID="TRY">0.00</cbc:TaxInclusiveAmount>
    <cbc:PayableAmount currencyID="TRY">0.00</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
  <cac:InvoiceLine>
    <cbc:ID>1</cbc:ID>
    <cbc:InvoicedQuantity unitCode="C62">1.00</cbc:InvoicedQuantity>
    <cbc:LineExtensionAmount currencyID="TRY">0.00</cbc:LineExtensionAmount>
  </cac:InvoiceLine>
</Invoice>
XML;

$streamContext = stream_context_create([
  'http' => [
    'header' => [
      'Username: ' . $username,
      'Password: ' . $password,
      'Connection: close',
    ],
    'timeout' => $readTimeout,
    'protocol_version' => 1.1,
  ],
  'ssl' => [
    'verify_peer' => !$allowInsecure,
    'verify_peer_name' => !$allowInsecure,
    'allow_self_signed' => $allowInsecure,
  ],
]);

try {
  $client = new SoapClient($archiveBase . '?wsdl', [
    'trace' => true,
    'exceptions' => true,
    'connection_timeout' => $timeout,
    'cache_wsdl' => WSDL_CACHE_NONE,
    'stream_context' => $streamContext,
    'compression' => 0,
    'keep_alive' => false,
    'user_agent' => 'efatura-ai/manual-test'
  ]);

  $params = [
    'invoiceXMLList' => [[
      'documentUUID' => $uuid,
      'xmlContent'   => $xml,
      'documentId'   => $docId,
      'documentDate' => $issueDate,
    ]]
  ];

  $resp = $client->__soapCall('sendInvoice', [$params]);
  echo "RESPONSE:\n";
  echo json_encode($resp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
  echo "LAST_REQUEST:\n", ($client->__getLastRequest() ?? ''), "\n";
  echo "LAST_RESPONSE:\n", ($client->__getLastResponse() ?? ''), "\n";
} catch (Throwable $e) {
  echo "ERROR: ", $e->getMessage(), "\n";
}
