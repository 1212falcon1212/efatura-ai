<?php

namespace App\Services\Kolaysoft;

use SoapClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class KolaysoftClient
{
    private ?SoapClient $client = null;
    private ?SoapClient $archiveClient = null;
    private ?SoapClient $einvoiceClient = null;
    private ?SoapClient $queryClient = null;
    private ?SoapClient $loadingClient = null;
    private ?SoapClient $eReceiptClient = null;
    private ?SoapClient $eDespatchClient = null;
    private ?SoapClient $despatchQueryClient = null;
    private string $username;
    private string $password;
    private bool $mock;
    private string $wsdl;

    public function __construct()
    {
        $base = (string) config('kolaysoft.base_url');
        $this->username = (string) config('kolaysoft.username');
        $this->password = (string) config('kolaysoft.password');
        // Production varsayilan: mock=false (aksi halde SOAP client olusmaz)
        $this->mock = (bool) config('kolaysoft.mock', false);
        $this->wsdl = $base.'?wsdl';

        // Mock modda değilsek SOAP istemcilerini lazy init yapacağız (call sırasında)
        if (config('kolaysoft.debug')) {
            logger()->info('KolaysoftClient init', [
                'mock' => $this->mock,
                'archive_base' => (string) config('kolaysoft.archive_base_url'),
                'einvoice_base' => (string) config('kolaysoft.einvoice_base_url'),
                'query_base' => (string) config('kolaysoft.query_base_url'),
            ]);
        }
        return;
    }

    private function initializeSoapClients(): void
    {
        // Mock modda hiç client oluşturma
        if ($this->mock) { return; }
        $allowInsecure = (bool) config('kolaysoft.allow_insecure_ssl', false);
        $readTimeout = (int) config('kolaysoft.read_timeout', 60);
        // Bazı ortamlar/ext-soap sürümleri WSDL cache + sıkıştırma ile stabil değil; güvenli varsayılanlar
        @ini_set('soap.wsdl_cache_enabled', '0');
        @ini_set('soap.wsdl_cache_ttl', '0');
        $streamContext = stream_context_create([
            'http' => [
                'header' => [
                    'Username: '.$this->username,
                    'Password: '.$this->password,
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
            $this->client = $this->client ?: new SoapClient($this->wsdl, [
                'trace' => true,
                'exceptions' => true,
                'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                'cache_wsdl' => WSDL_CACHE_NONE,
                'stream_context' => $streamContext,
                'compression' => 0,
                'keep_alive' => false,
                'user_agent' => 'efatura-ai/1.0',
            ]);
            $archiveBase = (string) config('kolaysoft.archive_base_url');
            if (!empty($archiveBase)) {
                $this->archiveClient = $this->archiveClient ?: new SoapClient($archiveBase.'?wsdl', [
                    'trace' => true,
                    'exceptions' => true,
                    'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'stream_context' => $streamContext,
                    'compression' => 0,
                    'keep_alive' => false,
                    'user_agent' => 'efatura-ai/1.0',
                ]);
            }
            $einvoiceBase = (string) config('kolaysoft.einvoice_base_url');
            if (!empty($einvoiceBase)) {
                $this->einvoiceClient = $this->einvoiceClient ?: new SoapClient($einvoiceBase.'?wsdl', [
                    'trace' => true,
                    'exceptions' => true,
                    'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'stream_context' => $streamContext,
                    'compression' => 0,
                    'keep_alive' => false,
                    'user_agent' => 'efatura-ai/1.0',
                ]);
            }
            $queryBase = (string) config('kolaysoft.query_base_url');
            if (!empty($queryBase)) {
                $this->queryClient = $this->queryClient ?: new SoapClient($queryBase.'?wsdl', [
                    'trace' => true,
                    'exceptions' => true,
                    'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'stream_context' => $streamContext,
                    'compression' => 0,
                    'keep_alive' => false,
                    'user_agent' => 'efatura-ai/1.0',
                ]);
            }
            $loadingBase = (string) config('kolaysoft.loading_base_url');
            if (!empty($loadingBase)) {
                $this->loadingClient = $this->loadingClient ?: new SoapClient($loadingBase.'?wsdl', [
                    'trace' => true,
                    'exceptions' => true,
                    'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'stream_context' => $streamContext,
                    'compression' => 0,
                    'keep_alive' => false,
                    'user_agent' => 'efatura-ai/1.0',
                ]);
            }
            $eReceiptBase = (string) config('kolaysoft.ereceipt_base_url');
            if (!empty($eReceiptBase)) {
                $this->eReceiptClient = $this->eReceiptClient ?: new SoapClient($eReceiptBase.'?wsdl', [
                    'trace' => true,
                    'exceptions' => true,
                    'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'stream_context' => $streamContext,
                    'compression' => 0,
                    'keep_alive' => false,
                    'user_agent' => 'efatura-ai/1.0',
                ]);
            }
            $eDespatchBase = (string) config('kolaysoft.edespatch_base_url');
            if (!empty($eDespatchBase)) {
                $this->eDespatchClient = $this->eDespatchClient ?: new SoapClient($eDespatchBase.'?wsdl', [
                    'trace' => true,
                    'exceptions' => true,
                    'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'stream_context' => $streamContext,
                    'compression' => 0,
                    'keep_alive' => false,
                    'user_agent' => 'efatura-ai/1.0',
                ]);
            }
            $despatchQueryBase = (string) config('kolaysoft.despatch_query_base_url');
            if (!empty($despatchQueryBase)) {
                $this->despatchQueryClient = $this->despatchQueryClient ?: new SoapClient($despatchQueryBase.'?wsdl', [
                    'trace' => true,
                    'exceptions' => true,
                    'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'stream_context' => $streamContext,
                    'compression' => 0,
                    'keep_alive' => false,
                    'user_agent' => 'efatura-ai/1.0',
                ]);
            }
        } catch (Throwable $e) {
            if (config('kolaysoft.debug')) {
                logger()->error('Kolaysoft initializeSoapClients fault', [
                    'error' => $e->getMessage(),
                ]);
            }
            // Mock moda düşmeyelim; uygun client'lar call() içinde lazy init ile denenecek
        }
    }

    private function mapMethod(string $method): string
    {
        $map = [
            // E-İrsaliye Sorgu (QueryDespatchWS) – dahili isimler
            'despatchQueryUsers' => 'QueryUsers',
            'getLastDepatchIdAndDate' => 'GetLastDepatchIdAndDate',
            'despatchQueryOutboxDocument' => 'QueryOutboxDocument',
            'despatchQueryOutboxDocumentWithDocumentDate' => 'QueryOutboxDocumentWithDocumentDate',
            'despatchQueryOutboxDocumentWithReceivedDate' => 'QueryOutboxDocumentWithReceivedDate',
            'despatchQueryOutboxDocumentWithLocalId' => 'QueryOutboxDocumentWithLocalId',
            'despatchQueryOutboxDocumentWithGUIDList' => 'QueryOutboxDocumentWithGUIDList',
            'despatchQueryInboxDocument' => 'QueryInboxDocument',
            'despatchQueryInboxDocumentWithDocumentDate' => 'QueryInboxDocumentWithDocumentDate',
            'despatchQueryInboxDocumentWithReceivedDate' => 'QueryInboxDocumentWithReceivedDate',
            'despatchQueryInboxDocumentWithGUIDList' => 'QueryInboxDocumentWithGUIDList',
            'despatchSetTakenFromEntegrator' => 'SetTakenFromEntegrator',
            'despatchQueryEnvelope' => 'QueryEnvelope',
            // E-İrsaliye (e-Despatch)
            'sendDespatch' => 'SendDespatch',
            'sendReceiptAdvice' => 'SendReceiptAdvice',
            'controlDespatchXML' => 'ControlDespatchXML',
            'controlReceiptAdviceXML' => 'ControlReceiptAdviceXML',
            'updateDespatchXML' => 'UpdateDespatchXML',
            'getCustomerGBList' => 'GetCustomerGBList',
            // E-Makbuz (e-SMM)
            'sendSMM' => 'SendSMM',
            'sendMM' => 'SendMM',
            'updateSMM' => 'UpdateSMM',
            'updateMM' => 'UpdateMM',
            'cancelSMM' => 'CancelSMM',
            'cancelMM' => 'CancelMM',
            'getLastSMMIdAndDate' => 'GetLastSMMIdAndDate',
            'getLastMMIdAndDate' => 'GetLastMMIdAndDate',
            'queryVouchers' => 'QueryVouchers',
            'setSmmEmailSent' => 'SetSmmEmailSent',
            'setMmEmailSent' => 'SetMmEmailSent',
            'setSmmDocumentFlag' => 'SetSmmDocumentFlag',
            'setMmDocumentFlag' => 'SetMmDocumentFlag',
            'controlXmlSmm' => 'ControlXmlSmm',
            'controlXmlMm' => 'ControlXmlMm',
            'queryVouchersWithLocalId' => 'QueryVouchersWithLocalId',
            'queryVouchersWithDocumentDate' => 'QueryVouchersWithDocumentDate',
            'queryVouchersWithReceivedDate' => 'QueryVouchersWithReceivedDate',
            'queryVouchersWithGUIDList' => 'QueryVouchersWithGUIDList',
            // QueryInvoiceWS ekleri
            'getUserGBList' => 'GetUserGBList',
            'getUserPKList' => 'GetUserPKList',
            'sendInvoice' => 'SendInvoice',
            'sendApplicationResponse' => 'SendApplicationResponse',
            'cancelInvoice' => 'CancelInvoice',
            'getCustomerCreditCount' => 'GetCustomerCreditCount',
            'customerCredit' => 'CustomerCredit',
            'updateInvoice' => 'UpdateInvoice',
            'setDocumentFlag' => 'SetDocumentFlag',
            'getLastInvoiceIdAndDate' => 'GetLastInvoiceIdAndDate',
            'getOutboxInvoiceStatusWithLogs' => 'GetOutboxInvoiceStatusWithLogs',
            'queryInvoice' => 'QueryInvoice',
            'setEmailSent' => 'SetEmailSent',
            'getCustomerCreditSpace' => 'GetCustomerCreditSpace',
            'controlInvoiceXML' => 'ControlInvoiceXML',
            'controlApplicationResponseXML' => 'ControlApplicationResponseXML',
            'queryUsers' => 'QueryUsers',
            'getCustomerPKList' => 'GetCustomerPKList',
            'queryOutboxDocument' => 'QueryOutboxDocument',
            'queryOutboxDocumentWithDocumentDate' => 'QueryOutboxDocumentWithDocumentDate',
            'queryInboxDocumentWithDocumentDate' => 'QueryInboxDocumentWithDocumentDate',
            'queryInboxDocumentWithReceivedDate' => 'QueryInboxDocumentWithReceivedDate',
            'queryInboxDocumentsWithGUIDList' => 'QueryInboxDocumentsWithGUIDList',
            'setTakenFromEntegrator' => 'SetTakenFromEntegrator',
            'queryAppResponseOfOutboxDocument' => 'QueryAppResponseOfOutboxDocument',
            'queryAppResponseOfInboxDocument' => 'QueryAppResponseOfInboxDocument',
            'queryEnvelope' => 'QueryEnvelope',
            'queryArchivedOutboxDocument' => 'QueryArchivedOutboxDocument',
            'queryArchivedInboxDocument' => 'QueryArchivedInboxDocument',
            'queryInvoiceWithLocalId' => 'QueryInvoiceWithLocalId',
            'queryInvoiceWithDocumentDate' => 'QueryInvoiceWithDocumentDate',
            'queryInvoiceWithReceivedDate' => 'QueryInvoiceWithReceivedDate',
            'queryInvoicesWithGUIDList' => 'QueryInvoicesWithGUIDList',
            'isEFaturaUser' => 'IsEFaturaUser',
            'getCreditActionsforCustomer' => 'GetCreditActionsforCustomer',
            'getEAInvoiceStatusWithLogs' => 'GetEAInvoiceStatusWithLogs',
            'queryArchivedInvoice' => 'QueryArchivedInvoice',
            'queryArchivedInvoiceWithDocumentDate' => 'QueryArchivedInvoiceWithDocumentDate',
            'loadOutboxInvoice' => 'LoadOutboxInvoice',
            'loadInboxInvoice' => 'LoadInboxInvoice',
            'queryLoadedOutboxDocument' => 'QueryOutboxDocument',
            'queryLoadedInboxDocument' => 'QueryInboxDocument',
        ];
        return $map[$method] ?? $method;
    }

    private function call(string $method, array $params): array
    {
        $soapMethod = $this->mapMethod($method);
        if ($this->mock) {
            return [
                'success' => true,
                'method' => $soapMethod,
                'params' => $params,
                'reference' => 'MOCK-'.uniqid(),
            ];
        }

        try {
            // Bazı metodlar E-Arşiv WS'te, diğerleri genel kolayWS'te
            $archiveMethods = [
                'GetEAInvoiceStatusWithLogs', 'SendInvoice',
                'CancelInvoice', 'QueryInvoice', 'QueryInvoiceWithLocalId',
                'QueryInvoiceWithDocumentDate', 'QueryInvoiceWithReceivedDate',
                'QueryInvoicesWithGUIDList', 'QueryArchivedInvoice',
                'QueryArchivedInvoiceWithDocumentDate',
            ];
            $einvoiceMethods = [
                'SendInvoice', 'SendApplicationResponse', 'CancelInvoice', 'UpdateInvoice', 'ControlInvoiceXML', 'ControlApplicationResponseXML', 'GetCustomerPKList', 'GetOutboxInvoiceStatusWithLogs', 'SetDocumentFlag',
                'GetCustomerCreditCount', 'GetCustomerCreditSpace',
            ];
            $queryMethods = [
                'QueryUsers', 'GetLastInvoiceIdAndDate', 'QueryOutboxDocument', 'QueryOutboxDocumentWithDocumentDate', 'QueryInvoice', 'QueryInvoiceWithLocalId',
                'QueryInvoiceWithDocumentDate', 'QueryInvoiceWithReceivedDate', 'QueryInvoicesWithGUIDList',
                'QueryInboxDocumentWithDocumentDate', 'QueryInboxDocumentWithReceivedDate', 'QueryInboxDocumentsWithGUIDList', 'SetTakenFromEntegrator',
                'QueryAppResponseOfOutboxDocument', 'QueryAppResponseOfInboxDocument', 'QueryEnvelope', 'QueryArchivedOutboxDocument', 'QueryArchivedInboxDocument',
            ];
            $despatchQueryMethods = [
                'QueryUsers', 'GetLastDepatchIdAndDate', 'QueryOutboxDocument', 'QueryOutboxDocumentWithDocumentDate', 'QueryOutboxDocumentWithReceivedDate',
                'QueryOutboxDocumentWithLocalId', 'QueryOutboxDocumentWithGUIDList', 'QueryInboxDocument', 'QueryInboxDocumentWithDocumentDate',
                'QueryInboxDocumentWithReceivedDate', 'QueryInboxDocumentWithGUIDList', 'SetTakenFromEntegrator', 'QueryEnvelope',
            ];
            $loadingMethods = [
                'LoadOutboxInvoice', 'LoadInboxInvoice', 'QueryOutboxDocument', 'QueryInboxDocument',
            ];
            $eReceiptMethods = [
                'SendSMM', 'SendMM', 'UpdateSMM', 'UpdateMM', 'CancelSMM', 'CancelMM', 'GetLastSMMIdAndDate', 'GetLastMMIdAndDate',
                'QueryVouchers', 'SetSmmEmailSent', 'SetMmEmailSent', 'SetSmmDocumentFlag', 'SetMmDocumentFlag', 'ControlXmlSmm', 'ControlXmlMm',
                'QueryVouchersWithLocalId', 'QueryVouchersWithDocumentDate', 'QueryVouchersWithReceivedDate', 'QueryVouchersWithGUIDList',
            ];
            $eDespatchMethods = [
                'SendDespatch', 'SendReceiptAdvice', 'ControlDespatchXML', 'ControlReceiptAdviceXML', 'UpdateDespatchXML', 'GetCustomerGBList', 'GetCustomerPKList',
            ];
            $client = $this->client;
            // SendInvoice özel yönlendirme: e_arsiv ise arşiv servisini tercih et
            $preferArchive = false;
            if ($soapMethod === 'SendInvoice') {
                $docList = $params['inputDocumentList'] ?? null;
                if (is_array($docList) && isset($docList[0]) && is_array($docList[0])) {
                    $doc0 = $docList[0];
                    $type = $doc0['type'] ?? null;
                    $profile = strtoupper((string) ($doc0['profileID'] ?? ''));
                    // Ek tespit: xmlContent'te EARSIVFATURA geçiyorsa e-Arşiv kabul et
                    $xmlContent = (string) ($doc0['xmlContent'] ?? '');
                    $xmlIndicatesArchive = stripos($xmlContent, 'EARSIVFATURA') !== false;
                    if ($type === 'e_arsiv' || $profile === 'EARSIVFATURA' || $xmlIndicatesArchive) {
                        $preferArchive = !((bool) config('kolaysoft.force_invoicews_for_earchive', true));
                        // Arşiv tercih ediliyorsa ve arşiv client hazır değilse initialize et; yine yoksa fallback'i tetiklemek için client'ı null'a çek
                        if ($preferArchive && !$this->archiveClient && !$this->mock) {
                            $this->initializeSoapClients();
                            if (!$this->archiveClient) { $client = null; }
                        }
                    }
                }
            }
            if ($preferArchive && $this->archiveClient) {
                $client = $this->archiveClient;
                // E-Arşiv: sendInvoice (lowercase) ve parametre adı invoiceXMLList
                if ($soapMethod === 'SendInvoice') {
                    $docList = $params['inputDocumentList'] ?? null;
                    if (is_array($docList) && isset($docList[0]) && is_array($docList[0])) {
                        $d = $docList[0];
                        // E-Arşiv şemasına uygun sadeleştirme
                        // Dokümana göre sadece aşağıdaki alanlar gönderilmeli
                        $allowed = ['documentUUID','xmlContent','documentId','documentDate','note'];
                        $clean = [];
                        foreach ($allowed as $k) { if (isset($d[$k])) { $clean[$k] = $d[$k]; } }
                        $params = ['invoiceXMLList' => [$clean]];
                    }
                    $soapMethod = 'sendInvoice';
                }
            } elseif ($soapMethod === 'ControlInvoiceXML' && $this->einvoiceClient) {
                // UBL kontrolü her zaman InvoiceWS üzerinden
                $client = $this->einvoiceClient;
            } elseif (in_array($soapMethod, $einvoiceMethods, true) && $this->einvoiceClient) {
                $client = $this->einvoiceClient;
            } elseif (in_array($soapMethod, $archiveMethods, true) && $this->archiveClient) {
                $client = $this->archiveClient;
            } elseif (in_array($soapMethod, $queryMethods, true) && $this->queryClient) {
                $client = $this->queryClient;
            } elseif (in_array($soapMethod, $loadingMethods, true) && $this->loadingClient) {
                $client = $this->loadingClient;
            } elseif (in_array($soapMethod, $eReceiptMethods, true) && $this->eReceiptClient) {
                $client = $this->eReceiptClient;
            } elseif (in_array($soapMethod, $eDespatchMethods, true) && $this->eDespatchClient) {
                $client = $this->eDespatchClient;
            } elseif (in_array($soapMethod, $despatchQueryMethods, true) && $this->despatchQueryClient) {
                $client = $this->despatchQueryClient;
            }
            // Lazy init: seçilen client yoksa ve mock da değilsek, init etmeyi dene
            if (!$client && !$this->mock) {
                $this->initializeSoapClients();
                // Initialize sonrası: e-Arşiv sendInvoice ise ARŞİV servisine ZORLA yönlendir
                if ($soapMethod === 'SendInvoice') {
                    $docList = $params['inputDocumentList'] ?? null;
                    if (is_array($docList) && isset($docList[0]) && is_array($docList[0])) {
                        $doc0 = $docList[0];
                        $type = $doc0['type'] ?? null;
                        $profile = strtoupper((string) ($doc0['profileID'] ?? ''));
                        $xmlContent = (string) ($doc0['xmlContent'] ?? '');
                        $xmlIndicatesArchive = stripos($xmlContent, 'EARSIVFATURA') !== false;
                        if (($type === 'e_arsiv' || $profile === 'EARSIVFATURA' || $xmlIndicatesArchive) && $this->archiveClient) {
                            // E-Arşiv: parametreyi invoiceXMLList'e çevir ve method adını küçült
                            $d = $doc0;
                            $allowed = ['documentUUID','xmlContent','documentId','documentDate','note'];
                            $clean = [];
                            foreach ($allowed as $k) { if (isset($d[$k])) { $clean[$k] = $d[$k]; } }
                            $params = ['invoiceXMLList' => [$clean]];
                            $soapMethod = 'sendInvoice';
                            $client = $this->archiveClient;
                        }
                    }
                }
                // UBL kontrol: her zaman InvoiceWS kullan
                if ($soapMethod === 'ControlInvoiceXML' && $this->einvoiceClient) {
                    $client = $this->einvoiceClient;
                }
                // Eğer hâlâ client seçilmediyse sınıflara göre seç
                if (!$client) {
                    if (in_array($soapMethod, $archiveMethods, true) && $this->archiveClient) {
                        $client = $this->archiveClient;
                    } elseif (in_array($soapMethod, $einvoiceMethods, true) && $this->einvoiceClient) {
                        $client = $this->einvoiceClient;
                    } elseif (in_array($soapMethod, $queryMethods, true) && $this->queryClient) {
                        $client = $this->queryClient;
                    } elseif (in_array($soapMethod, $loadingMethods, true) && $this->loadingClient) {
                        $client = $this->loadingClient;
                    } elseif (in_array($soapMethod, $eReceiptMethods, true) && $this->eReceiptClient) {
                        $client = $this->eReceiptClient;
                    } elseif (in_array($soapMethod, $eDespatchMethods, true) && $this->eDespatchClient) {
                        $client = $this->eDespatchClient;
                    } elseif (in_array($soapMethod, $despatchQueryMethods, true) && $this->despatchQueryClient) {
                        $client = $this->despatchQueryClient;
                    } else {
                        $client = $this->client; // genel fallback
                    }
                }
            }
            // Canlıya zorla: client yoksa ve mock=false ise, metod ailesine göre uygun WS'yi doğrudan oluşturup dene
            if (!$client && !$this->mock) {
                $allowInsecure = (bool) config('kolaysoft.allow_insecure_ssl', false);
                $readTimeout = (int) config('kolaysoft.read_timeout', 60);
                @ini_set('soap.wsdl_cache_enabled', '0');
                @ini_set('soap.wsdl_cache_ttl', '0');
                $streamContext = stream_context_create([
                    'http' => [
                        'header' => [
                            'Username: '.$this->username,
                            'Password: '.$this->password,
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
                $wsdlToUse = null;
                if (in_array($soapMethod, $eDespatchMethods, true)) {
                    $wsdlToUse = (string) config('kolaysoft.edespatch_base_url');
                } elseif (in_array($soapMethod, $despatchQueryMethods, true)) {
                    $wsdlToUse = (string) config('kolaysoft.despatch_query_base_url');
                } elseif (in_array($soapMethod, $archiveMethods, true)) {
                    // E-Arşiv WS doğrudan
                    $wsdlToUse = (string) config('kolaysoft.archive_base_url');
                } elseif (in_array($soapMethod, $einvoiceMethods, true)) {
                    $wsdlToUse = (string) config('kolaysoft.einvoice_base_url');
                } elseif (in_array($soapMethod, $queryMethods, true)) {
                    $wsdlToUse = (string) config('kolaysoft.query_base_url');
                }
                if (!empty($wsdlToUse)) {
                    try {
                        $client = new SoapClient($wsdlToUse.'?wsdl', [
                            'trace' => true,
                            'exceptions' => true,
                            'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                            'cache_wsdl' => WSDL_CACHE_NONE,
                            'stream_context' => $streamContext,
                            'compression' => 0,
                            'keep_alive' => false,
                            'user_agent' => 'efatura-ai/1.0',
                        ]);
                    } catch (Throwable $e) {
                        // init başarısızsa client null kalır
                    }
                }
            }
            // Placeholder denemesi: InvoiceWS.SendInvoice şemasında source/destination zorunluluğu için '?' doldur
            if ($client && $soapMethod === 'SendInvoice' && $client !== $this->archiveClient) {
                if (isset($params['inputDocumentList']) && is_array($params['inputDocumentList']) && isset($params['inputDocumentList'][0]) && is_array($params['inputDocumentList'][0])) {
                    if (!isset($params['inputDocumentList'][0]['sourceUrn']) || $params['inputDocumentList'][0]['sourceUrn'] === '') {
                        $params['inputDocumentList'][0]['sourceUrn'] = '?';
                    }
                    if (!isset($params['inputDocumentList'][0]['destinationUrn']) || $params['inputDocumentList'][0]['destinationUrn'] === '') {
                        $params['inputDocumentList'][0]['destinationUrn'] = '?';
                    }
                }
            }
            // Son emniyet: ControlInvoiceXML için client atanmamışsa, doğrudan InvoiceWS ile SoapClient oluştur
            if ($soapMethod === 'ControlInvoiceXML' && !$client) {
                try {
                    $allowInsecure = (bool) config('kolaysoft.allow_insecure_ssl', false);
                    $readTimeout = (int) config('kolaysoft.read_timeout', 60);
                    @ini_set('soap.wsdl_cache_enabled', '0');
                    @ini_set('soap.wsdl_cache_ttl', '0');
                    $streamContext = stream_context_create([
                        'http' => [
                            'header' => [
                                'Username: '.$this->username,
                                'Password: '.$this->password,
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
                    $einvoiceBase = (string) config('kolaysoft.einvoice_base_url');
                    if (!empty($einvoiceBase)) {
                        $client = new SoapClient($einvoiceBase.'?wsdl', [
                            'trace' => true,
                            'exceptions' => true,
                            'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                            'cache_wsdl' => WSDL_CACHE_NONE,
                            'stream_context' => $streamContext,
                            'compression' => 0,
                            'keep_alive' => false,
                            'user_agent' => 'efatura-ai/1.0',
                        ]);
                    }
                } catch (Throwable $e) {
                    // ignore, aşağıda No SOAP client dönerse loglarda görünür
                }
            }
            // Son emniyet 2: E-Arşiv sendInvoice için client yoksa arşiv WS client oluştur
            if (($soapMethod === 'sendInvoice' || $soapMethod === 'SendInvoice') && !$client && isset($params['invoiceXMLList'])) {
                try {
                    $allowInsecure = (bool) config('kolaysoft.allow_insecure_ssl', false);
                    $readTimeout = (int) config('kolaysoft.read_timeout', 60);
                    @ini_set('soap.wsdl_cache_enabled', '0');
                    @ini_set('soap.wsdl_cache_ttl', '0');
                    $streamContext = stream_context_create([
                        'http' => [
                            'header' => [
                                'Username: '.$this->username,
                                'Password: '.$this->password,
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
                    $archiveBase = (string) config('kolaysoft.archive_base_url');
                    if (!empty($archiveBase)) {
                        $client = new SoapClient($archiveBase.'?wsdl', [
                            'trace' => true,
                            'exceptions' => true,
                            'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                            'cache_wsdl' => WSDL_CACHE_NONE,
                            'stream_context' => $streamContext,
                            'compression' => 0,
                            'keep_alive' => false,
                            'user_agent' => 'efatura-ai/1.0',
                        ]);
                    }
                } catch (Throwable $e) { /* no-op */ }
            }
            $response = $client ? $client->__soapCall($soapMethod, [$params]) : ['return' => ['code' => '503', 'explanation' => 'No SOAP client']];
            $arr = json_decode(json_encode($response), true);
            // EntResponse, Users ve diğer yardımcı veri kümelerini (opsiyonel) response içine ekleyelim
            if (config('kolaysoft.debug')) {
                $ent = $this->collectEntResponses($arr);
                if (!empty($ent)) { $arr['_entResponses'] = $ent; }
                $users = $this->collectResponseUsers($arr);
                if (!empty($users)) { $arr['_users'] = $users; }
                $tax = $this->collectTaxInfo($arr);
                if (!empty($tax)) { $arr['_taxInfo'] = $tax; }
                $docInfo = $this->collectDocumentInfo($arr);
                if (!empty($docInfo)) { $arr['_documentInfo'] = $docInfo; }
                $credit = $this->collectCreditActions($arr);
                if (!empty($credit)) { $arr['_creditActions'] = $credit; }
                $respDocs = $this->collectResponseDocuments($arr);
                if (!empty($respDocs)) { $arr['_documents'] = $respDocs; }
            }
            if (config('kolaysoft.debug')) {
                logger()->info("Kolaysoft {$soapMethod} response", [
                    'response' => $arr,
                    'last_request' => is_object($client) && method_exists($client, '__getLastRequest') ? $client->__getLastRequest() : null,
                    'last_response' => is_object($client) && method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : null,
                ]);
            }
            return $arr;
        } catch (Throwable $e) {
            if (config('kolaysoft.debug')) {
                logger()->error("Kolaysoft {$soapMethod} fault", [
                    'last_request' => is_object($client) && method_exists($client,'__getLastRequest') ? $client->__getLastRequest() : null,
                    'last_response' => is_object($client) && method_exists($client,'__getLastResponse') ? $client->__getLastResponse() : null,
                    'error' => $e->getMessage(),
                ]);
            }
            // Uyumluluk fallback'leri: bazı ortamlar QueryInvoiceWith* metodlarını desteklemeyebilir
            $msg = strtolower($e->getMessage() ?? '');
            if (str_contains($msg, 'not a valid method')) {
                if ($soapMethod === 'QueryInvoiceWithDocumentDate') {
                    $date = $params['documentDate'] ?? null;
                    if ($date) {
                        return $this->call('queryOutboxDocumentWithDocumentDate', [
                            'startDate' => $date,
                            'endDate' => $date,
                            'documentType' => '1', // Fatura
                            'queried' => 'ALL',
                            'withXML' => 'NONE',
                        ]);
                    }
                } elseif ($soapMethod === 'QueryInvoiceWithReceivedDate') {
                    $date = $params['receivedDate'] ?? null;
                    if ($date) {
                        return $this->call('queryOutboxDocumentWithReceivedDate', [
                            'startDate' => $date,
                            'endDate' => $date,
                            'documentType' => '1',
                            'queried' => 'ALL',
                            'withXML' => 'NONE',
                        ]);
                    }
                }
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function pickFirstOrNull(array $source, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                return $source[$key];
            }
        }
        return null;
    }

    private function normalizeInputDocument(array $doc): array
    {
        $normalized = [];
        // Tip bilgisini koru (e_arsiv | e_fatura)
        if (isset($doc['type'])) {
            $normalized['type'] = $doc['type'];
        }
        $uuid = $this->pickFirstOrNull($doc, ['documentUUID','documentUuid','document_uuid','uuid','ettn','document_uuid']);
        if ($uuid !== null) {
            $normalized['documentUUID'] = $uuid;
        }
        $xml = $this->pickFirstOrNull($doc, ['xmlContent','xml','content','invoiceXML']);
        if ($xml !== null) {
            // Hem xmlContent hem xml anahtarını dolduruyoruz (farklı WS şemalarına uyum için)
            $normalized['xmlContent'] = $xml;
            $normalized['xml'] = $xml;
        } else {
            // Bazı SOAP operasyonları xmlContent alanını her hâlükârda bekliyor
            $normalized['xmlContent'] = '';
        }
        $sourceUrn = $this->pickFirstOrNull($doc, ['sourceUrn','source','source_urn','senderUrn']);
        if ($sourceUrn === null) {
            $fallback = '';
            $docType = $this->pickFirstOrNull($doc, ['type','documentType']);
            if ($docType === 'e_arsiv') {
                $fallback = (string) (config('kolaysoft.source_urn_earchive') ?? '');
            }
            if ($fallback === '') {
                $fallback = (string) (config('kolaysoft.source_urn') ?? '');
            }
            if ($fallback !== '') { $sourceUrn = $fallback; }
        }
        // Kolaysoft bazen boş da olsa alanı bekliyor; boşsa varsayılan 'urn:default:sender' koy
        if ($sourceUrn === null || $sourceUrn === '') { $sourceUrn = 'urn:default:sender'; }
        $normalized['sourceUrn'] = $sourceUrn;
        $docType = $this->pickFirstOrNull($doc, ['type','documentType']);
        // E-Makbuz özel alan: hedef e-posta bilgisi
        $destinationEmail = $this->pickFirstOrNull($doc, ['destinationEmail','email','receiverEmail','toEmail']);
        if ($destinationEmail !== null) { $normalized['destinationEmail'] = $destinationEmail; }
        // InvoiceWS.SendInvoice kullanacaksak (config ile zorlandıysa) destinationUrn alanını da ekle (e-Arşiv için)
        if ($docType === 'e_arsiv' && (bool) config('kolaysoft.force_invoicews_for_earchive', true)) {
            $dest = $this->pickFirstOrNull($doc, ['destinationUrn','destination_urn']);
            if (($dest === null || $dest === '') && isset($doc['customer']) && is_array($doc['customer'])) {
                $dest = $this->pickFirstOrNull($doc['customer'], ['email','mail']);
            }
            if (($dest === null || $dest === '') && $destinationEmail) { $dest = $destinationEmail; }
            if (!empty($dest)) { $normalized['destinationUrn'] = (string) $dest; }
        }
        // E-Fatura: destinationUrn zorunlu (müşteri alias/URN)
        if ($docType === 'e_fatura') {
            $destUrn = $this->pickFirstOrNull($doc, ['destinationUrn','destination_urn']);
            if (($destUrn === null || $destUrn === '') && isset($doc['customer']) && is_array($doc['customer'])) {
                $destUrn = $this->pickFirstOrNull($doc['customer'], ['urn','alias']);
            }
            if (!empty($destUrn)) {
                $normalized['destinationUrn'] = (string) $destUrn;
            }
        }
        // E-Arşiv: Doküman gereği sendInvoice yükünde destinationUrn gönderilmeyecek
        $localId = $this->pickFirstOrNull($doc, ['localId','local_id','localID']);
        if ($localId !== null) { $normalized['localId'] = $localId; }
        $documentDate = $this->pickFirstOrNull($doc, ['documentDate','issueDate','issue_date']);
        if ($documentDate === null || $documentDate === '') { $documentDate = date('Y-m-d'); }
        $normalized['documentDate'] = (string) $documentDate;

        // DocumentId: Kolaysoft beklenen format ABC2019123456789 (3 harf + yıl(4) + 9 hane)
        $documentId = $this->pickFirstOrNull($doc, ['documentId','document_id','id']);
        $prefix = strtoupper((string) (config('kolaysoft.document_id_prefix') ?? 'ABC'));
        $prefix = preg_replace('/[^A-Z]/', '', $prefix) ?: 'ABC';
        $prefix = substr($prefix, 0, 3);
        $isValidDocId = function ($v): bool {
            return is_string($v) && preg_match('/^[A-Z]{3}[0-9]{13}$/', $v) === 1;
        };
        if (!$isValidDocId($documentId)) {
            $documentId = $prefix . date('Y') . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        }
        $normalized['documentId'] = (string) $documentId;

        // VKN/TCKN bilgileri (bazı servisler zorunlu tutuyor)
        $senderVkn = (string) (config('kolaysoft.source_id') ?? '');
        if ($senderVkn !== '') {
            $normalized['vknTckn'] = $senderVkn;
            $normalized['senderVknTckn'] = $senderVkn;
        }
        if (isset($doc['customer']) && is_array($doc['customer'])) {
            $rc = $doc['customer'];
            $recv = $this->pickFirstOrNull($rc, ['vkn','tckn','vkn_tckn','taxId']);
            if ($recv !== null && $recv !== '') {
                $normalized['receiverVknTckn'] = $recv;
            }
        }
        // Metadata → Kolaysoft alanları (profil, para birimi, referanslar vb.)
        if (isset($doc['metadata']) && is_array($doc['metadata'])) {
            $meta = $doc['metadata'];
            $profile = $this->pickFirstOrNull($meta, ['scenario','profile','profileID']);
            if ($profile) { $normalized['profileID'] = strtoupper((string) $profile); }
            // E-Fatura için varsayılan profil TEMELFATURA
            if (empty($normalized['profileID']) && $docType === 'e_fatura') {
                $normalized['profileID'] = 'TEMELFATURA';
            }
            $invoiceType = $this->pickFirstOrNull($meta, ['invoiceKind','invoiceTypeCode','invoiceType']);
            if ($invoiceType) { $normalized['invoiceTypeCode'] = strtoupper((string) $invoiceType); }
            $documentCurrency = $this->pickFirstOrNull($meta, ['currency','documentCurrencyCode']);
            if ($documentCurrency) { $normalized['documentCurrencyCode'] = strtoupper((string) $documentCurrency); }
            $issueTime = $this->pickFirstOrNull($meta, ['issueTime','documentTime']);
            if ($issueTime) { $normalized['documentTime'] = (string) $issueTime; }
            $xslt = $this->pickFirstOrNull($meta, ['xsltTemplate','xslt']);
            if ($xslt) { $normalized['xslt'] = (string) $xslt; }

            // Eğer metadata.destinationUrn verilmişse ve tip e-Arşiv ise override et
            $destOverride = $this->pickFirstOrNull($meta, ['destinationUrn']);
            if ($docType === 'e_arsiv' && $destOverride !== null && $destOverride !== '') {
                $normalized['destinationUrn'] = (string) $destOverride;
            }

            // Tekil sipariş/irsaliye referansları
            $orderNo = $this->pickFirstOrNull($meta, ['orderNumber']);
            $orderDate = $this->pickFirstOrNull($meta, ['orderDate']);
            if ($orderNo) { $normalized['orderReference'] = ['id' => $orderNo, 'issueDate' => $orderDate]; }
            $despatchNo = $this->pickFirstOrNull($meta, ['despatchNo']);
            $despatchDate = $this->pickFirstOrNull($meta, ['despatchDate']);
            if ($despatchNo) { $normalized['despatchDocumentReference'] = ['id' => $despatchNo, 'issueDate' => $despatchDate]; }

            // Çoklu referanslar
            if (!empty($meta['orders']) && is_array($meta['orders'])) {
                $normalized['orderReferenceList'] = [];
                foreach ($meta['orders'] as $o) {
                    if (!is_array($o)) { continue; }
                    $normalized['orderReferenceList'][] = [
                        'id' => $this->pickFirstOrNull($o, ['no','id']),
                        'issueDate' => $this->pickFirstOrNull($o, ['date','issueDate']),
                    ];
                }
            }
            if (!empty($meta['despatches']) && is_array($meta['despatches'])) {
                $normalized['despatchDocumentReferenceList'] = [];
                foreach ($meta['despatches'] as $d) {
                    if (!is_array($d)) { continue; }
                    $normalized['despatchDocumentReferenceList'][] = [
                        'id' => $this->pickFirstOrNull($d, ['no','id']),
                        'issueDate' => $this->pickFirstOrNull($d, ['date','issueDate']),
                    ];
                }
            }
            if (!empty($meta['payments']) && is_array($meta['payments'])) {
                $normalized['paymentMeansList'] = [];
                foreach ($meta['payments'] as $p) {
                    if (!is_array($p)) { continue; }
                    $normalized['paymentMeansList'][] = [
                        'paymentMeansCode' => $this->pickFirstOrNull($p, ['method','code']),
                        'paymentChannelCode' => $this->pickFirstOrNull($p, ['channel','paymentChannel']),
                        'payeeFinancialAccount' => [ 'id' => $this->pickFirstOrNull($p, ['iban','account','ibanNo']) ],
                        'note' => $this->pickFirstOrNull($p, ['note','description']),
                    ];
                }
            }
            $note = $this->pickFirstOrNull($meta, ['note','notes','description']);
            if ($note) { $normalized['note'] = (string) $note; }
        }

        return $normalized;
    }

    private function normalizeInputDocumentList(array $inputDocumentList): array
    {
        $result = [];
        foreach ($inputDocumentList as $doc) {
            if (!is_array($doc)) { continue; }
            $result[] = $this->normalizeInputDocument($doc);
        }
        return $result;
    }

    // E-Makbuz: SendSMM
    public function sendSMM(array $voucherXMLList): array
    {
        // E-Makbuz için parametre adı voucherXMLList, InputDocument listesi beklenir
        return $this->call('sendSMM', [
            'voucherXMLList' => $this->normalizeInputDocumentList($voucherXMLList),
        ]);
    }

    // E-Makbuz: SendMM (E-Müstahsil)
    public function sendMM(array $voucherXMLList): array
    {
        return $this->call('sendMM', [
            'voucherXMLList' => $this->normalizeInputDocumentList($voucherXMLList),
        ]);
    }

    // E-Makbuz: UpdateSMM
    public function updateSMM(array $voucherXMLList): array
    {
        return $this->call('updateSMM', [
            'voucherXMLList' => $this->normalizeInputDocumentList($voucherXMLList),
        ]);
    }

    // E-Makbuz: UpdateMM
    public function updateMM(array $voucherXMLList): array
    {
        return $this->call('updateMM', [
            'voucherXMLList' => $this->normalizeInputDocumentList($voucherXMLList),
        ]);
    }

    // E-Makbuz: CancelSMM
    public function cancelSMM(string $voucherUuid, string $cancelReason, string $cancelDate): array
    {
        return $this->call('cancelSMM', [
            'voucherUuid' => $voucherUuid,
            'cancelReason' => $cancelReason,
            'cancelDate' => $cancelDate,
        ]);
    }

    // E-Makbuz: CancelMM
    public function cancelMM(string $voucherUuid, string $cancelReason, string $cancelDate): array
    {
        return $this->call('cancelMM', [
            'voucherUuid' => $voucherUuid,
            'cancelReason' => $cancelReason,
            'cancelDate' => $cancelDate,
        ]);
    }

    private function normalizeEntResponseItem(array $item): array
    {
        return [
            'documentUUID' => $this->pickFirstOrNull($item, ['documentUUID','documentUuid','document_uuid','uuid','ettn']),
            'code' => $this->pickFirstOrNull($item, ['code','statusCode','resultCode']),
            'explanation' => $this->pickFirstOrNull($item, ['explanation','message','stateExplanation']),
            'cause' => $this->pickFirstOrNull($item, ['cause','error','errorMessage']),
        ];
    }

    private function collectEntResponses(array $payload): array
    {
        $results = [];
        $stack = [$payload];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (!is_array($current)) {
                continue;
            }
            $isAssoc = array_keys($current) !== range(0, count($current) - 1);
            $hasEntKeys = $isAssoc && (
                array_key_exists('code', $current) ||
                array_key_exists('explanation', $current) ||
                array_key_exists('cause', $current) ||
                array_key_exists('documentUUID', $current) ||
                array_key_exists('documentUuid', $current) ||
                array_key_exists('document_uuid', $current)
            );
            if ($hasEntKeys) {
                $results[] = $this->normalizeEntResponseItem($current);
            }
            foreach ($current as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }
        return $results;
    }

    private function normalizeResponseUser(array $item): array
    {
        return [
            'vkn_tckn' => $this->pickFirstOrNull($item, ['vkn_tckn','tcknVkn','identifier','vknTckn']),
            'unvan_ad' => $this->pickFirstOrNull($item, ['unvan_ad','name','unvanAd','title']),
            'etiket' => $this->pickFirstOrNull($item, ['etiket','label','urn','pk','postaKutusu','posta_kutusu']),
            'tip' => $this->pickFirstOrNull($item, ['tip','type']),
            'ilkKayitZamani' => $this->pickFirstOrNull($item, ['ilkKayitZamani','ilkKayitZamaniASCII','firstRegistrationTime','firstRegisteredAt']),
            'etiketKayitZamani' => $this->pickFirstOrNull($item, ['etiketKayitZamanı','etiketKayitZamani','labelRegistrationTime','labelRegisteredAt']),
        ];
    }

    private function collectResponseUsers(array $payload): array
    {
        $users = [];
        $stack = [$payload];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (!is_array($current)) { continue; }
            $isAssoc = array_keys($current) !== range(0, count($current) - 1);
            if ($isAssoc) {
                // Doğrudan user listesi tutan anahtarlar
                foreach (['users','userList','responseUsers','ResponseUserList'] as $listKey) {
                    if (isset($current[$listKey]) && is_array($current[$listKey])) {
                        foreach ($current[$listKey] as $u) {
                            if (is_array($u)) { $users[] = $this->normalizeResponseUser($u); }
                        }
                    }
                }
                // Tekil kullanıcı benzeri obje
                $hasUserKeys = (
                    array_key_exists('vkn_tckn', $current) ||
                    array_key_exists('unvan_ad', $current) ||
                    array_key_exists('etiket', $current) ||
                    array_key_exists('tip', $current)
                );
                if ($hasUserKeys) {
                    $users[] = $this->normalizeResponseUser($current);
                }
            }
            foreach ($current as $value) {
                if (is_array($value)) { $stack[] = $value; }
            }
        }
        return $users;
    }

    private function normalizeFlagSetter(array $flagSetter): array
    {
        $direction = $this->pickFirstOrNull($flagSetter, ['document_direction','documentDirection','direction']);
        $flagName = $this->pickFirstOrNull($flagSetter, ['flag_name','flagName','name']);
        $flagValue = $this->pickFirstOrNull($flagSetter, ['flag_value','flagValue','value']);
        $docUuid = $this->pickFirstOrNull($flagSetter, ['document_uuid','documentUUID','documentUuid','uuid','ettn']);

        $normalized = [];
        if ($direction !== null) { $normalized['document_direction'] = strtoupper((string) $direction); }
        if ($flagName !== null) { $normalized['flag_name'] = strtoupper((string) $flagName); }
        if ($flagValue !== null) {
            $normalized['flag_value'] = in_array((string) $flagValue, ['1','true','TRUE','on','ON'], true) ? 1 : (int) $flagValue;
        }
        if ($docUuid !== null) { $normalized['document_uuid'] = $docUuid; }
        return $normalized;
    }

    private function normalizeTaxInfo(array $item): array
    {
        return [
            'taxTypeCode' => $this->pickFirstOrNull($item, ['taxTypeCode','taxtTypeCode','vergiKodu']),
            'taxTypeName' => $this->pickFirstOrNull($item, ['taxTypeName','taxtTypeName','vergiAdi']),
            'taxAmount' => $this->pickFirstOrNull($item, ['taxAmount','vergiTutari']),
            'taxPercentage' => $this->pickFirstOrNull($item, ['taxPercentage','vergiYuzdesi','taxRate']),
        ];
    }

    private function collectTaxInfo(array $payload): array
    {
        $items = [];
        $stack = [$payload];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (!is_array($current)) { continue; }
            // Yaygın anahtarlar
            foreach (['taxInfo','taxes','taxList','TaxInfo','vergiBilgisi'] as $key) {
                if (isset($current[$key])) {
                    $candidate = $current[$key];
                    if (is_array($candidate) && array_keys($candidate) === range(0, count($candidate) - 1)) {
                        foreach ($candidate as $c) { if (is_array($c)) { $items[] = $this->normalizeTaxInfo($c); } }
                    } elseif (is_array($candidate)) {
                        $items[] = $this->normalizeTaxInfo($candidate);
                    }
                }
            }
            foreach ($current as $value) { if (is_array($value)) { $stack[] = $value; } }
        }
        return $items;
    }

    private function normalizeDocumentInfo(array $item): array
    {
        return [
            'documentDate' => $this->pickFirstOrNull($item, ['documentDate','issueDate','document_date','issue_date']),
            'documentNo' => $this->pickFirstOrNull($item, ['documentNo','documentId','document_id','id']),
        ];
    }

    private function normalizeResponseDocument(array $item): array
    {
        $boolize = function ($v): int {
            if (is_bool($v)) { return $v ? 1 : 0; }
            $s = strtoupper((string) $v);
            return in_array($s, ['1','TRUE','YES','ON'], true) ? 1 : 0;
        };
        return [
            'document_uuid' => $this->pickFirstOrNull($item, ['document_uuid','documentUUID','uuid','ettn']),
            'document_id' => $this->pickFirstOrNull($item, ['document_id','documentId','id']),
            'envelope_uuid' => $this->pickFirstOrNull($item, ['envelope_uuid','envelopeUUID','envelopeUuid']),
            'document_profile' => $this->pickFirstOrNull($item, ['document_profile','profile','scenario','documentProfile']),
            'system_creation_time' => $this->pickFirstOrNull($item, ['system_creation_time','createdAt','systemCreationTime']),
            'document_issue_date' => $this->pickFirstOrNull($item, ['document_issue_date','issueDate','documentDate']),
            'source_id' => $this->pickFirstOrNull($item, ['source_id','sourceId']),
            'source_urn' => $this->pickFirstOrNull($item, ['source_urn','sourceUrn']),
            'source_title' => $this->pickFirstOrNull($item, ['source_title','sourceTitle']),
            'destination_id' => $this->pickFirstOrNull($item, ['destination_id','destinationId']),
            'destination_urn' => $this->pickFirstOrNull($item, ['destination_urn','destinationUrn']),
            'state_code' => $this->pickFirstOrNull($item, ['state_code','stateCode','statusCode']),
            'state_explanation' => $this->pickFirstOrNull($item, ['state_explanation','stateExplanation','statusMessage']),
            'content_type' => $this->pickFirstOrNull($item, ['content_type','contentType']),
            'document_content' => $this->pickFirstOrNull($item, ['document_content','content','documentContent']),
            'currency_code' => $this->pickFirstOrNull($item, ['currency_code','currencyCode']),
            'cause' => $this->pickFirstOrNull($item, ['cause','error','errorMessage']),
            'invoice_total' => $this->pickFirstOrNull($item, ['invoice_total','invoiceTotal','amount']),
            'is_read' => $boolize($this->pickFirstOrNull($item, ['is_read','read'])) ,
            'is_archieved' => $boolize($this->pickFirstOrNull($item, ['is_archieved','is_archived','archived'])),
            'is_accounted' => $boolize($this->pickFirstOrNull($item, ['is_accounted','accounted'])),
            'is_transferred' => $boolize($this->pickFirstOrNull($item, ['is_transferred','transferred'])),
            'is_printed' => $boolize($this->pickFirstOrNull($item, ['is_printed','printed'])),
            'local_id' => $this->pickFirstOrNull($item, ['local_id','localId']),
            'document_type_code' => $this->pickFirstOrNull($item, ['document_type_code','documentType','typeCode']),
            'notes' => $this->pickFirstOrNull($item, ['notes']),
            'despatchInfo' => $this->pickFirstOrNull($item, ['despatchInfo']),
            'orderInfo' => $this->pickFirstOrNull($item, ['orderInfo']),
            'taxInfo' => $this->pickFirstOrNull($item, ['taxInfo']),
            'taxInclusiveAmount' => $this->pickFirstOrNull($item, ['taxInclusiveAmount']),
            'taxExlusiveAmount' => $this->pickFirstOrNull($item, ['taxExlusiveAmount','taxExclusiveAmount']),
            'allowanceTotalAmount' => $this->pickFirstOrNull($item, ['allowanceTotalAmount']),
            'taxAmount0015' => $this->pickFirstOrNull($item, ['taxAmount0015']),
            'lineExtensionAmount' => $this->pickFirstOrNull($item, ['lineExtensionAmount']),
            'suplierPersonName' => $this->pickFirstOrNull($item, ['suplierPersonName','supplierPersonName']),
            'supplierPersonMiddleName' => $this->pickFirstOrNull($item, ['supplierPersonMiddleName']),
            'supplierPersonFamilyName' => $this->pickFirstOrNull($item, ['supplierPersonFamilyName']),
            'customerPersonName' => $this->pickFirstOrNull($item, ['customerPersonName']),
            'customerPersonMiddleName' => $this->pickFirstOrNull($item, ['customerPersonMiddleName']),
            'customerPersonFamilyName' => $this->pickFirstOrNull($item, ['customerPersonFamilyName']),
            'response_document_uuid' => $this->pickFirstOrNull($item, ['response_document_uuid','responseDocumentUUID']),
            'reference_document_uuid' => $this->pickFirstOrNull($item, ['reference_document_uuid','referenceDocumentUUID']),
            'response_code' => $this->pickFirstOrNull($item, ['response_code','responseCode']),
            'response_validation_state' => $this->pickFirstOrNull($item, ['response_validation_state','responseValidationState']),
            'response_received_date' => $this->pickFirstOrNull($item, ['response_received_date','responseReceivedDate']),
            'gtb_reference_no' => $this->pickFirstOrNull($item, ['gtb_reference_no','gtbReferenceNo']),
            'gtb_gcb_tescil_no' => $this->pickFirstOrNull($item, ['gtb_gcb_tescil_no','gtbGcbTescilNo']),
            'gtb_fiili_ihracat_tarihi' => $this->pickFirstOrNull($item, ['gtb_fiili_ihracat_tarihi','gtbFiiliIhracatTarihi']),
        ];
    }

    private function collectResponseDocuments(array $payload): array
    {
        $docs = [];
        $stack = [$payload];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (!is_array($current)) { continue; }
            foreach (['documents','documentList','responseDocuments'] as $key) {
                if (isset($current[$key]) && is_array($current[$key])) {
                    $candidate = $current[$key];
                    if (array_keys($candidate) === range(0, count($candidate) - 1)) {
                        foreach ($candidate as $c) { if (is_array($c)) { $docs[] = $this->normalizeResponseDocument($c); } }
                    } else {
                        $docs[] = $this->normalizeResponseDocument($candidate);
                    }
                }
            }
            foreach ($current as $value) { if (is_array($value)) { $stack[] = $value; } }
        }
        return $docs;
    }

    private function collectDocumentInfo(array $payload): array
    {
        $items = [];
        $stack = [$payload];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (!is_array($current)) { continue; }
            foreach (['documentInfo','DocumentInfo'] as $key) {
                if (isset($current[$key]) && is_array($current[$key])) {
                    $candidate = $current[$key];
                    if (array_keys($candidate) === range(0, count($candidate) - 1)) {
                        foreach ($candidate as $c) { if (is_array($c)) { $items[] = $this->normalizeDocumentInfo($c); } }
                    } else {
                        $items[] = $this->normalizeDocumentInfo($candidate);
                    }
                }
            }
            foreach ($current as $value) { if (is_array($value)) { $stack[] = $value; } }
        }
        return $items;
    }

    private function normalizeCreditAction(array $item): array
    {
        $actionTypeRaw = $this->pickFirstOrNull($item, ['action_type','actionType']);
        $actionTypeCanonical = $this->normalizeCreditActionType($actionTypeRaw);
        return [
            'purchaseDate' => $this->pickFirstOrNull($item, ['purchaseDate','purchase_date','date']),
            'purchase_count' => (int) ($this->pickFirstOrNull($item, ['purchase_count','purchaseCount','loadedCount']) ?? 0),
            'consideredCount' => (int) ($this->pickFirstOrNull($item, ['consideredCount','considered_count','remainingAfterExpiry']) ?? 0),
            'credit_unit' => $this->pickFirstOrNull($item, ['credit_unit','creditUnit']),
            'credit_pool_id' => $this->pickFirstOrNull($item, ['credit_pool_id','creditPoolId']),
            'buyer_VKN_TCKN' => $this->pickFirstOrNull($item, ['buyer_VKN_TCKN','buyerVKN_TCKN','buyerVknTckn','buyerIdentifier']),
            'action_type' => $actionTypeCanonical ?? $actionTypeRaw,
        ];
    }

    private function normalizeCreditActionType(null|string $value): ?string
    {
        if ($value === null || $value === '') { return null; }
        $v = strtoupper(trim((string) $value));
        $v = strtr($v, [
            'Ç' => 'C', 'Ğ' => 'G', 'İ' => 'I', 'I' => 'I', 'Ö' => 'O', 'Ş' => 'S', 'Ü' => 'U',
            'Â' => 'A', 'Û' => 'U', 'Ê' => 'E', 'Ô' => 'O',
        ]);
        $v = str_replace(['-', ' '], '_', $v);
        $allowed = ['BASLAMA','SATINALMA','DEVIR_GIRIS','HEDIYE','DEVIR_CIKIS','TRANSFER'];
        if (in_array($v, $allowed, true)) { return $v; }
        // Bazı olası varyasyonlar
        $aliases = [
            'DEVIRGIRIS' => 'DEVIR_GIRIS',
            'DEVIR_GIRIS' => 'DEVIR_GIRIS',
            'DEVIR_GİRİS' => 'DEVIR_GIRIS',
            'DEVR_GIRIS' => 'DEVIR_GIRIS',
            'DEVIRCIKIS' => 'DEVIR_CIKIS',
            'DEVIR_CIKİS' => 'DEVIR_CIKIS',
            'SATIN_ALMA' => 'SATINALMA',
        ];
        if (isset($aliases[$v])) { return $aliases[$v]; }
        return $v;
    }

    private function collectCreditActions(array $payload): array
    {
        $items = [];
        $stack = [$payload];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (!is_array($current)) { continue; }
            foreach (['creditActions','CreditActionList','creditActionList'] as $key) {
                if (isset($current[$key]) && is_array($current[$key])) {
                    $candidate = $current[$key];
                    if (array_keys($candidate) === range(0, count($candidate) - 1)) {
                        foreach ($candidate as $c) { if (is_array($c)) { $items[] = $this->normalizeCreditAction($c); } }
                    } else {
                        $items[] = $this->normalizeCreditAction($candidate);
                    }
                }
            }
            foreach ($current as $value) { if (is_array($value)) { $stack[] = $value; } }
        }
        return $items;
    }

    public function sendInvoice(array $inputDocument): array
    {
        // E-Arşiv özel: alias'sız akış (invoiceXMLList + EArchiveInvoiceWS) yalnızca config izin verirse
        $type = $inputDocument['type'] ?? null;
        $allowArchiveDirect = !((bool) config('kolaysoft.force_invoicews_for_earchive', true));
        if ($type === 'e_arsiv' && $allowArchiveDirect) {
            $documentUUID = $inputDocument['documentUUID'] ?? $inputDocument['documentUuid'] ?? $inputDocument['uuid'] ?? null;
            if ($documentUUID === null) {
                $documentUUID = (string) (method_exists(\Illuminate\Support\Str::class, 'uuid') ? \Illuminate\Support\Str::uuid() : uniqid());
            }
            $xml = $inputDocument['xmlContent'] ?? $inputDocument['xml'] ?? '';
            $documentId = $inputDocument['documentId'] ?? $inputDocument['document_id'] ?? null;
            if ($documentId === null) {
                $prefix = strtoupper((string) (config('kolaysoft.document_id_prefix') ?? 'ABC'));
                $prefix = preg_replace('/[^A-Z]/', '', $prefix) ?: 'ABC';
                $prefix = substr($prefix, 0, 3);
                $documentId = $prefix . date('Y') . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
            }
            $documentDate = $inputDocument['documentDate'] ?? date('Y-m-d');
            // e-Arşiv: hedef e-posta ve (varsa) kaynak etiket
            $destinationEmail = $inputDocument['destinationEmail'] ?? $inputDocument['email'] ?? null;
            if (!$destinationEmail && isset($inputDocument['customer']) && is_array($inputDocument['customer'])) {
                $destinationEmail = $inputDocument['customer']['email'] ?? null;
            }

            // Arşiv WS client hazır değilse oluştur
            $client = $this->archiveClient;
            if (!$client && !$this->mock) {
                $allowInsecure = (bool) config('kolaysoft.allow_insecure_ssl', false);
                $readTimeout = (int) config('kolaysoft.read_timeout', 60);
                @ini_set('soap.wsdl_cache_enabled', '0');
                @ini_set('soap.wsdl_cache_ttl', '0');
                $streamContext = stream_context_create([
                    'http' => [
                        'header' => [
                            'Username: '.$this->username,
                            'Password: '.$this->password,
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
                $archiveBase = (string) config('kolaysoft.archive_base_url');
                // WSDL address location http olabilir; http/https dene
                $bases = [];
                if (!empty($archiveBase)) { $bases[] = $archiveBase; }
                if (str_starts_with($archiveBase, 'https://')) { $bases[] = 'http://'.substr($archiveBase, 8); }
                foreach ($bases as $b) {
                    try {
                        $client = new \SoapClient($b.'?wsdl', [
                            'trace' => true,
                            'exceptions' => true,
                            'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                            'cache_wsdl' => WSDL_CACHE_NONE,
                            'stream_context' => $streamContext,
                            'compression' => 0,
                            'keep_alive' => false,
                            'user_agent' => 'efatura-ai/1.0',
                        ]);
                        // Başarılıysa, tekrar kullanmak için sınıf seviyesine ata
                        $this->archiveClient = $client;
                        break;
                    } catch (\Throwable $e) {
                        $client = null;
                        continue;
                    }
                }
            }
            if ($client) {
                try {
                    $doc = [
                        'documentUUID' => (string) $documentUUID,
                        'xmlContent' => (string) $xml,
                        'documentId' => (string) $documentId,
                        'documentDate' => (string) $documentDate,
                        // Döküman ve hata loguna göre, URN alanları boş olsa bile bekleniyor.
                        'sourceUrn' => '',
                        'destinationUrn' => '',
                    ];
                    if (!empty($destinationEmail)) {
                        // Bazı WSDL sürümlerinde alan adı 'destinationEmail', bazılarında 'email' olarak tanımlı olabiliyor.
                        // Uyum için her ikisini de gönderiyoruz.
                        $doc['destinationEmail'] = (string) $destinationEmail;
                        $doc['email'] = (string) $destinationEmail;
                    }

                    // SOAP istemcisi karmaşık tipler için dizi yerine nesne bekler.
                    // Önceki "object has no 'xxx' property" hatalarını çözmek için stdClass'a çevirelim.
                    $docObject = (object) $doc;
                    
                    // Loglardan en bilgilendirici hatayı veren SoapParam yöntemini kullanalım
                    $params = new \SoapParam([$docObject], 'inputDocumentList');

                    if (config('kolaysoft.debug')) {
                        logger()->info('Kolaysoft EArchive sendInvoice request', [
                            'summary' => [
                                'documentUUID' => (string) $documentUUID,
                                'documentId' => (string) $documentId,
                            ],
                            'param_keys' => array_keys($doc),
                        ]);
                    }

                    $response = $client->__soapCall('sendInvoice', [$params]);
                    $arr = json_decode(json_encode($response), true);

                    if (config('kolaysoft.debug')) {
                        logger()->info('Kolaysoft EArchive sendInvoice response', [
                            'response' => $arr,
                            'last_request' => method_exists($client,'__getLastRequest') ? $client->__getLastRequest() : null,
                            'last_response' => method_exists($client,'__getLastResponse') ? $client->__getLastResponse() : null,
                        ]);
                    }
                    return $arr;
                } catch (\Throwable $e) {
                    // Genel koruma bloğu
                    if (config('kolaysoft.debug')) {
                        logger()->error('Kolaysoft EArchive sendInvoice fault', [
                            'error' => $e->getMessage(),
                            'last_request' => method_exists($client,'__getLastRequest') ? $client->__getLastRequest() : null,
                            'last_response' => method_exists($client,'__getLastResponse') ? $client->__getLastResponse() : null,
                        ]);
                    }
                    return ['return' => ['code' => '500', 'explanation' => 'EArchive sendInvoice fault', 'cause' => $e->getMessage()]];
                }
            }
            // Arşiv client oluşturulamadı
            if (config('kolaysoft.debug')) {
                logger()->warning('No SOAP client (archive). Aborting EArchive send');
            }
            return ['return' => ['code' => '503', 'explanation' => 'No SOAP client (archive)']];
        }
        // E-Fatura vb. için genel yol
        $normalized = $this->normalizeInputDocument($inputDocument);
        return $this->call('sendInvoice', ['inputDocumentList' => [$normalized]]);
    }

    public function sendApplicationResponse(array $inputDocument): array
    {
        // Uygulama yanıtları için de inputDocumentList beklenir
        $normalized = $this->normalizeInputDocument($inputDocument);
        return $this->call('sendApplicationResponse', ['inputDocumentList' => [$normalized]]);
    }

    public function cancelInvoice(string $invoiceUUID, ?string $cancelReason = null, ?string $cancelDate = null): array
    {
        // Dokümana göre minimum zorunlu alan invoiceUUID; uyumluluk için farklı adlandırmaları da gönderiyoruz
        $params = [
            'invoiceUUID' => $invoiceUUID,
            'invoiceUuid' => $invoiceUUID,
        ];
        if ($cancelReason !== null) { $params['cancelReason'] = $cancelReason; }
        if ($cancelDate !== null) { $params['cancelDate'] = $cancelDate; }
        return $this->call('cancelInvoice', $params);
    }

    public function getCustomerCreditCount(?string $vknTckn = null): array
    {
        $vkn = $vknTckn ?: (string) (config('kolaysoft.source_id') ?? '');
        $params = [];
        if (!empty($vkn)) {
            // Dokümanda vkn_tckn; uyumluluk için alternatif alanı da gönderiyoruz
            $params['vkn_tckn'] = $vkn;
            $params['vknTckn'] = $vkn;
        }
        // Özellikle InvoiceWS üzerinden doğrudan çağır (genel init'e bağımlılığı azalt)
        try {
            $allowInsecure = (bool) config('kolaysoft.allow_insecure_ssl', false);
            $readTimeout = (int) config('kolaysoft.read_timeout', 60);
            @ini_set('soap.wsdl_cache_enabled', '0');
            @ini_set('soap.wsdl_cache_ttl', '0');
            $streamContext = stream_context_create([
                'http' => [
                    'header' => [
                        'Username: '.$this->username,
                        'Password: '.$this->password,
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
            $einvoiceBase = (string) config('kolaysoft.einvoice_base_url');
            if (!empty($einvoiceBase)) {
                $sc = new SoapClient($einvoiceBase.'?wsdl', [
                    'trace' => true,
                    'exceptions' => true,
                    'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'stream_context' => $streamContext,
                    'compression' => 0,
                    'keep_alive' => false,
                    'user_agent' => 'efatura-ai/1.0',
                ]);
                $response = $sc->__soapCall('GetCustomerCreditCount', [$params]);
                $arr = json_decode(json_encode($response), true);
                return $arr;
            }
        } catch (Throwable $e) {
            if (config('kolaysoft.debug')) {
                logger()->error('Kolaysoft GetCustomerCreditCount direct fault', ['error' => $e->getMessage()]);
            }
            // Altyapı hatasında genel yoldan dene
        }
        return $this->call('getCustomerCreditCount', $params);
    }

    public function customerCredit(string $creditType, string $vknTckn): array
    {
        // Bazı sürümler farklı alan adları bekleyebilir; hepsini gönderelim
        $params = [
            'creditType' => $creditType,
            'creditTypeString' => $creditType,
            'documentType' => $creditType, // KONTOR vb.
            'vknTckn' => $vknTckn,
            'vkn_tckn' => $vknTckn,
        ];
        // InvoiceWS'e doğrudan çağrı
        try {
            $allowInsecure = (bool) config('kolaysoft.allow_insecure_ssl', false);
            $readTimeout = (int) config('kolaysoft.read_timeout', 60);
            @ini_set('soap.wsdl_cache_enabled', '0');
            @ini_set('soap.wsdl_cache_ttl', '0');
            $streamContext = stream_context_create([
                'http' => [
                    'header' => [
                        'Username: '.$this->username,
                        'Password: '.$this->password,
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
            $einvoiceBase = (string) config('kolaysoft.einvoice_base_url');
            if (!empty($einvoiceBase)) {
                $sc = new SoapClient($einvoiceBase.'?wsdl', [
                    'trace' => true,
                    'exceptions' => true,
                    'connection_timeout' => (int) config('kolaysoft.timeout', 10),
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'stream_context' => $streamContext,
                    'compression' => 0,
                    'keep_alive' => false,
                    'user_agent' => 'efatura-ai/1.0',
                ]);
                $response = $sc->__soapCall('CustomerCredit', [$params]);
                $arr = json_decode(json_encode($response), true);
                return $arr;
            }
        } catch (Throwable $e) {
            if (config('kolaysoft.debug')) {
                logger()->error('Kolaysoft CustomerCredit direct fault', ['error' => $e->getMessage()]);
            }
            // CustomerCredit metodu bulunmuyorsa GetCustomerCreditCount'a graceful fallback yap
            if (str_contains(strtolower($e->getMessage()), 'not a valid method')) {
                return $this->getCustomerCreditCount($vknTckn);
            }
        }
        return $this->call('customerCredit', $params);
    }

    // Ek metodlar (dokümana göre)
    public function updateInvoice(array $inputDocumentList): array
    {
        return $this->call('updateInvoice', ['inputDocumentList' => $this->normalizeInputDocumentList($inputDocumentList)]);
    }

    public function getLastInvoiceIdAndDate(?string $sourceId = null, ?array $documentIdPrefix = null): array
    {
        $sid = $sourceId ?: (string) (config('kolaysoft.source_id') ?? '');
        $params = [];
        if (!empty($sid)) { $params['source_id'] = $sid; }
        if (!empty($documentIdPrefix)) { $params['documentIdPrefix'] = array_values($documentIdPrefix); }
        return $this->call('getLastInvoiceIdAndDate', $params);
    }

    public function queryInvoice(array $args): array
    {
        return $this->call('queryInvoice', $args);
    }

    // E-Makbuz: QueryVouchers
    public function queryVouchers(string $paramType, string $parameter, string $voucherType, string $withXML = 'NONE'): array
    {
        $allowedContent = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowedContent, true)) { $wx = 'NONE'; }
        $allowedVoucher = ['SERBESTMESLEKMAKBUZU','MUSTAHSILMAKBUZU'];
        $vt = strtoupper(trim($voucherType));
        if (!in_array($vt, $allowedVoucher, true)) { $vt = 'SERBESTMESLEKMAKBUZU'; }
        return $this->call('queryVouchers', [
            'paramType' => $paramType,
            'parameter' => $parameter,
            'voucherType' => $vt,
            'withXML' => $wx,
        ]);
    }

    // E-Makbuz: SetSmmEmailSent
    public function setSmmEmailSent(array $voucherUuidList): array
    {
        return $this->call('setSmmEmailSent', [
            'voucher_uuid_list' => array_values($voucherUuidList),
        ]);
    }

    // E-Makbuz: SetMmEmailSent
    public function setMmEmailSent(array $voucherUuidList): array
    {
        return $this->call('setMmEmailSent', [
            'voucher_uuid_list' => array_values($voucherUuidList),
        ]);
    }

    // E-Makbuz: SetSmmDocumentFlag
    public function setSmmDocumentFlag(array $flagSetter): array
    {
        return $this->call('setSmmDocumentFlag', [
            'flagSetter' => $flagSetter,
        ]);
    }

    // E-Makbuz: SetMmDocumentFlag
    public function setMmDocumentFlag(array $flagSetter): array
    {
        return $this->call('setMmDocumentFlag', [
            'flagSetter' => $flagSetter,
        ]);
    }

    // E-Makbuz: ControlXmlSmm
    public function controlXmlSmm(string $voucherXml): array
    {
        return $this->call('controlXmlSmm', [
            'voucherXml' => $voucherXml,
            'xml' => $voucherXml,
        ]);
    }

    // E-Makbuz: ControlXmlMm
    public function controlXmlMm(string $voucherXml): array
    {
        return $this->call('controlXmlMm', [
            'voucherXml' => $voucherXml,
            'xml' => $voucherXml,
        ]);
    }

    // E-İrsaliye: SendDespatch
    public function sendDespatch(array $inputDocumentList): array
    {
        return $this->call('sendDespatch', [
            'inputDocumentList' => $this->normalizeInputDocumentList($inputDocumentList),
        ]);
    }

    // E-İrsaliye: SendReceiptAdvice
    public function sendReceiptAdvice(array $inputDocumentList): array
    {
        return $this->call('sendReceiptAdvice', [
            'inputDocumentList' => $this->normalizeInputDocumentList($inputDocumentList),
        ]);
    }

    // E-İrsaliye: ControlDespatchXML
    public function controlDespatchXML(string $despatchXML): array
    {
        return $this->call('controlDespatchXML', [
            'despatchXML' => $despatchXML,
            'xml' => $despatchXML,
        ]);
    }

    // E-İrsaliye: ControlReceiptAdviceXML
    public function controlReceiptAdviceXML(string $receiptAdviceXML): array
    {
        return $this->call('controlReceiptAdviceXML', [
            'receiptAdviceXML' => $receiptAdviceXML,
            'xml' => $receiptAdviceXML,
        ]);
    }

    // E-İrsaliye: UpdateDespatchXML
    public function updateDespatchXML(array $inputDocumentList): array
    {
        return $this->call('updateDespatchXML', [
            'inputDocumentList' => $this->normalizeInputDocumentList($inputDocumentList),
        ]);
    }

    // E-İrsaliye: GetCustomerGBList
    public function getCustomerGBList(): array
    {
        return $this->call('getCustomerGBList', []);
    }

    // E-İrsaliye Sorgu: QueryUsers
    public function despatchQueryUsers(?string $startDate = null, ?string $finishDate = null, ?string $vknTckn = null): array
    {
        $params = [];
        if (!empty($startDate)) { $params['startDate'] = $startDate; }
        if (!empty($finishDate)) { $params['finishDate'] = $finishDate; }
        if (!empty($vknTckn)) { $params['vkn_tckn'] = $vknTckn; }
        return $this->call('despatchQueryUsers', $params);
    }

    // E-İrsaliye Sorgu: GetLastDepatchIdAndDate
    public function getLastDepatchIdAndDate(?string $sourceId = null, ?array $documentIdPrefix = null): array
    {
        $sid = $sourceId ?: (string) (config('kolaysoft.source_id') ?? '');
        $params = [];
        if (!empty($sid)) { $params['source_id'] = $sid; }
        if (!empty($documentIdPrefix)) { $params['documentIdPrefix'] = array_values($documentIdPrefix); }
        return $this->call('getLastDepatchIdAndDate', $params);
    }

    // E-İrsaliye Sorgu: Outbox temel
    public function despatchQueryOutboxDocument(string $paramType, string $parameter, string $withXML = 'NONE'): array
    {
        $allowed = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowed, true)) { $wx = 'NONE'; }
        return $this->call('despatchQueryOutboxDocument', [
            'paramType' => $paramType,
            'parameter' => $parameter,
            'withXML' => $wx,
        ]);
    }

    public function despatchQueryOutboxDocumentWithDocumentDate(string $startDate, string $endDate, string $documentType, string $queried = 'ALL', string $withXML = 'NONE'): array
    {
        $allowedContent = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowedContent, true)) { $wx = 'NONE'; }
        $allowedDoc = ['1','2'];
        $dt = in_array($documentType, $allowedDoc, true) ? $documentType : '1';
        $qAllowed = ['YES','NO','ALL'];
        $q = in_array(strtoupper($queried), $qAllowed, true) ? strtoupper($queried) : 'ALL';
        return $this->call('despatchQueryOutboxDocumentWithDocumentDate', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'documentType' => $dt,
            'queried' => $q,
            'withXML' => $wx,
        ]);
    }

    public function despatchQueryOutboxDocumentWithReceivedDate(string $startDate, string $endDate, string $documentType, string $queried = 'ALL', string $withXML = 'NONE'): array
    {
        $allowedContent = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowedContent, true)) { $wx = 'NONE'; }
        $allowedDoc = ['1','2'];
        $dt = in_array($documentType, $allowedDoc, true) ? $documentType : '1';
        $qAllowed = ['YES','NO','ALL'];
        $q = in_array(strtoupper($queried), $qAllowed, true) ? strtoupper($queried) : 'ALL';
        return $this->call('despatchQueryOutboxDocumentWithReceivedDate', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'documentType' => $dt,
            'queried' => $q,
            'withXML' => $wx,
        ]);
    }

    public function despatchQueryOutboxDocumentWithLocalId(string $localId): array
    {
        return $this->call('despatchQueryOutboxDocumentWithLocalId', [
            'localId' => $localId,
        ]);
    }

    public function despatchQueryOutboxDocumentWithGUIDList(array $guidList, string $documentType): array
    {
        $allowedDoc = ['1','2'];
        $dt = in_array($documentType, $allowedDoc, true) ? $documentType : '1';
        return $this->call('despatchQueryOutboxDocumentWithGUIDList', [
            'guidList' => array_values($guidList),
            'documentType' => $dt,
        ]);
    }

    // E-İrsaliye Sorgu: Inbox
    public function despatchQueryInboxDocument(string $paramType, string $parameter, string $withXML = 'NONE'): array
    {
        $allowed = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowed, true)) { $wx = 'NONE'; }
        return $this->call('despatchQueryInboxDocument', [
            'paramType' => $paramType,
            'parameter' => $parameter,
            'withXML' => $wx,
        ]);
    }

    public function despatchQueryInboxDocumentWithDocumentDate(string $startDate, string $endDate, string $documentType, string $queried = 'ALL', string $withXML = 'NONE', string $takenFromEntegrator = 'ALL', ?string $minRecordId = null): array
    {
        $allowedContent = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowedContent, true)) { $wx = 'NONE'; }
        $allowedDoc = ['1','2'];
        $dt = in_array($documentType, $allowedDoc, true) ? $documentType : '1';
        $qAllowed = ['YES','NO','ALL'];
        $q = in_array(strtoupper($queried), $qAllowed, true) ? strtoupper($queried) : 'ALL';
        $tAllowed = ['YES','NO','ALL'];
        $t = in_array(strtoupper($takenFromEntegrator), $tAllowed, true) ? strtoupper($takenFromEntegrator) : 'ALL';
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'documentType' => $dt,
            'queried' => $q,
            'withXML' => $wx,
            'takenFromEntegrator' => $t,
        ];
        if (!empty($minRecordId)) { $params['minRecordId'] = $minRecordId; }
        return $this->call('despatchQueryInboxDocumentWithDocumentDate', $params);
    }

    public function despatchQueryInboxDocumentWithReceivedDate(string $startDate, string $endDate, string $documentType, string $queried = 'ALL', string $withXML = 'NONE', string $takenFromEntegrator = 'ALL', ?string $minRecordId = null): array
    {
        $allowedContent = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowedContent, true)) { $wx = 'NONE'; }
        $allowedDoc = ['1','2'];
        $dt = in_array($documentType, $allowedDoc, true) ? $documentType : '1';
        $qAllowed = ['YES','NO','ALL'];
        $q = in_array(strtoupper($queried), $qAllowed, true) ? strtoupper($queried) : 'ALL';
        $tAllowed = ['YES','NO','ALL'];
        $t = in_array(strtoupper($takenFromEntegrator), $tAllowed, true) ? strtoupper($takenFromEntegrator) : 'ALL';
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'documentType' => $dt,
            'queried' => $q,
            'withXML' => $wx,
            'takenFromEntegrator' => $t,
        ];
        if (!empty($minRecordId)) { $params['minRecordId'] = $minRecordId; }
        return $this->call('despatchQueryInboxDocumentWithReceivedDate', $params);
    }

    public function despatchQueryInboxDocumentWithGUIDList(array $guidList, string $documentType): array
    {
        $allowedDoc = ['1','2'];
        $dt = in_array($documentType, $allowedDoc, true) ? $documentType : '1';
        return $this->call('despatchQueryInboxDocumentWithGUIDList', [
            'guidList' => array_values($guidList),
            'documentType' => $dt,
        ]);
    }

    public function despatchSetTakenFromEntegrator(array $documentUuidList): array
    {
        return $this->call('despatchSetTakenFromEntegrator', [
            'documentUuid' => array_values($documentUuidList),
        ]);
    }

    public function despatchQueryEnvelope(string $envelopeUUID): array
    {
        return $this->call('despatchQueryEnvelope', [
            'envelopeUUID' => $envelopeUUID,
        ]);
    }

    // E-Makbuz: QueryVouchersWithLocalId
    public function queryVouchersWithLocalId(string $localId): array
    {
        return $this->call('queryVouchersWithLocalId', [
            'localId' => $localId,
        ]);
    }

    // E-Makbuz: QueryVouchersWithDocumentDate
    public function queryVouchersWithDocumentDate(string $startDate, string $endDate, string $voucherType, string $withXML = 'NONE', ?string $minRecordId = null): array
    {
        $allowedContent = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowedContent, true)) { $wx = 'NONE'; }
        $allowedVoucher = ['SERBESTMESLEKMAKBUZU','MUSTAHSILMAKBUZU'];
        $vt = strtoupper(trim($voucherType));
        if (!in_array($vt, $allowedVoucher, true)) { $vt = 'SERBESTMESLEKMAKBUZU'; }
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'voucherType' => $vt,
            'withXML' => $wx,
        ];
        if (!empty($minRecordId)) { $params['minRecordId'] = $minRecordId; }
        return $this->call('queryVouchersWithDocumentDate', $params);
    }

    // E-Makbuz: QueryVouchersWithReceivedDate
    public function queryVouchersWithReceivedDate(string $startDate, string $endDate, string $voucherType, string $withXML = 'NONE'): array
    {
        $allowedContent = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowedContent, true)) { $wx = 'NONE'; }
        $allowedVoucher = ['SERBESTMESLEKMAKBUZU','MUSTAHSILMAKBUZU'];
        $vt = strtoupper(trim($voucherType));
        if (!in_array($vt, $allowedVoucher, true)) { $vt = 'SERBESTMESLEKMAKBUZU'; }
        return $this->call('queryVouchersWithReceivedDate', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'voucherType' => $vt,
            'withXML' => $wx,
        ]);
    }

    // E-Makbuz: QueryVouchersWithGUIDList
    public function queryVouchersWithGUIDList(array $guidList, string $voucherType): array
    {
        $allowedVoucher = ['SERBESTMESLEKMAKBUZU','MUSTAHSILMAKBUZU'];
        $vt = strtoupper(trim($voucherType));
        if (!in_array($vt, $allowedVoucher, true)) { $vt = 'SERBESTMESLEKMAKBUZU'; }
        return $this->call('queryVouchersWithGUIDList', [
            'guidList' => array_values($guidList),
            'voucherType' => $vt,
        ]);
    }

    // E-Makbuz: GetLastSMMIdAndDate
    public function getLastSMMIdAndDate(?string $sourceId = null, ?array $documentIdPrefixList = null): array
    {
        $sid = $sourceId ?: (string) (config('kolaysoft.source_id') ?? '');
        $params = [];
        if (!empty($sid)) { $params['source_id'] = $sid; }
        if (!empty($documentIdPrefixList)) { $params['documentIdPrefixList'] = array_values($documentIdPrefixList); }
        return $this->call('getLastSMMIdAndDate', $params);
    }

    // E-Makbuz: GetLastMMIdAndDate
    public function getLastMMIdAndDate(?string $sourceId = null, ?array $documentIdPrefixList = null): array
    {
        $sid = $sourceId ?: (string) (config('kolaysoft.source_id') ?? '');
        $params = [];
        if (!empty($sid)) { $params['source_id'] = $sid; }
        if (!empty($documentIdPrefixList)) { $params['documentIdPrefixList'] = array_values($documentIdPrefixList); }
        return $this->call('getLastMMIdAndDate', $params);
    }

    public function queryOutboxDocument(string $paramType, string $parameter, ?string $withXML = null): array
    {
        $params = [
            'paramType' => $paramType,
            'parameter' => $parameter,
        ];
        // withXML: XML | PDF | HTML | NONE (varsayılan NONE). Normalize et ve doğrula
        $allowed = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowed, true)) {
            $wx = 'NONE';
        }
        $params['withXML'] = $wx;
        return $this->call('queryOutboxDocument', $params);
    }

    public function queryOutboxDocumentWithDocumentDate(
        string $startDate,
        string $endDate,
        string $documentType,
        string $queried,
        ?string $withXML = 'NONE',
        ?string $minRecordId = null
    ): array {
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'documentType' => $documentType, // {1 (Fatura), 2 (Uygulama yanıtı)}
            'queried' => $queried,           // {YES, NO, ALL}
        ];
        $allowed = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowed, true)) { $wx = 'NONE'; }
        $params['withXML'] = $wx;
        if (!empty($minRecordId)) { $params['minRecordId'] = $minRecordId; }
        return $this->call('queryOutboxDocumentWithDocumentDate', $params);
    }

    public function queryInboxDocumentWithDocumentDate(
        string $startDate,
        string $endDate,
        string $documentType,
        string $queried,
        ?string $withXML = 'NONE',
        ?string $takenFromEntegrator = 'ALL',
        ?string $minRecordId = null,
        ?string $sourceId = null
    ): array {
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'documentType' => $documentType,
            'queried' => $queried,
        ];
        $allowed = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowed, true)) { $wx = 'NONE'; }
        $params['withXML'] = $wx;
        if (!empty($takenFromEntegrator)) { $params['takenFromEntegrator'] = $takenFromEntegrator; }
        if (!empty($minRecordId)) { $params['minRecordId'] = $minRecordId; }
        $sid = $sourceId ?: (string) (config('kolaysoft.source_id') ?? '');
        if ($sid !== '') { $params['sourceId'] = $sid; $params['vkn_tckn'] = $sid; $params['vknTckn'] = $sid; }
        return $this->call('queryInboxDocumentWithDocumentDate', $params);
    }

    public function queryInboxDocumentWithReceivedDate(
        string $startDate,
        string $endDate,
        string $documentType,
        string $queried,
        ?string $withXML = 'NONE',
        ?string $takenFromEntegrator = 'ALL',
        ?string $minRecordId = null,
        ?string $sourceId = null
    ): array {
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'documentType' => $documentType,
            'queried' => $queried,
        ];
        $allowed = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowed, true)) { $wx = 'NONE'; }
        $params['withXML'] = $wx;
        if (!empty($takenFromEntegrator)) { $params['takenFromEntegrator'] = $takenFromEntegrator; }
        if (!empty($minRecordId)) { $params['minRecordId'] = $minRecordId; }
        $sid = $sourceId ?: (string) (config('kolaysoft.source_id') ?? '');
        if ($sid !== '') { $params['sourceId'] = $sid; $params['vkn_tckn'] = $sid; $params['vknTckn'] = $sid; }
        return $this->call('queryInboxDocumentWithReceivedDate', $params);
    }

    public function queryInboxDocumentsWithGUIDList(array $guidList, string $documentType): array
    {
        $params = [
            'guidList' => array_values($guidList),
            'documentType' => $documentType,
        ];
        return $this->call('queryInboxDocumentsWithGUIDList', $params);
    }

    public function setTakenFromEntegrator(array $documentUuidList): array
    {
        // Dokümana göre: parametre adı documentUuid (liste)
        return $this->call('setTakenFromEntegrator', [
            'documentUuid' => array_values($documentUuidList),
        ]);
    }

    public function queryAppResponseOfOutboxDocument(string $documentUUID, ?string $withXML = 'NONE'): array
    {
        $allowed = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowed, true)) { $wx = 'NONE'; }
        return $this->call('queryAppResponseOfOutboxDocument', [
            'documentUUID' => $documentUUID,
            'withXML' => $wx,
        ]);
    }

    public function queryAppResponseOfInboxDocument(string $documentUUID, ?string $withXML = 'NONE'): array
    {
        $allowed = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowed, true)) { $wx = 'NONE'; }
        return $this->call('queryAppResponseOfInboxDocument', [
            'documentUUID' => $documentUUID,
            'withXML' => $wx,
        ]);
    }

    public function queryUsers(?string $startDate = null, ?string $finishDate = null, ?string $vknTckn = null): array
    {
        $params = [];
        if (!empty($startDate)) { $params['startDate'] = $startDate; }
        if (!empty($finishDate)) { $params['finishDate'] = $finishDate; }
        if (!empty($vknTckn)) { $params['vkn_tckn'] = $vknTckn; }
        return $this->call('queryUsers', $params);
    }

    public function setEmailSent(array $args): array
    {
        return $this->call('setEmailSent', $args);
    }

    public function getCustomerCreditSpace(?string $vknTckn = null): array
    {
        $vkn = $vknTckn ?: (string) (config('kolaysoft.source_id') ?? '');
        $params = [];
        if (!empty($vkn)) {
            $params['vkn_tckn'] = $vkn;
            $params['vknTckn'] = $vkn;
        }
        return $this->call('getCustomerCreditSpace', $params);
    }

    public function controlInvoiceXML(string $invoiceXML): array
    {
        // Dokümanda parametre adı invoiceXML; uyumluluk için xml alanını da gönderiyoruz
        return $this->call('controlInvoiceXML', [
            'invoiceXML' => $invoiceXML,
            'xml' => $invoiceXML,
        ]);
    }

    public function controlApplicationResponseXML(string $applicationResponseXML): array
    {
        return $this->call('controlApplicationResponseXML', [
            'applicationResponseXML' => $applicationResponseXML,
            'xml' => $applicationResponseXML,
        ]);
    }

    public function getCustomerPKList(): array
    {
        // HTTP header ile kimlik sağlandığından parametre yok
        return $this->call('getCustomerPKList', []);
    }

    public function queryInvoiceWithLocalId(string $localId): array
    {
        return $this->call('queryInvoiceWithLocalId', ['localId' => $localId]);
    }

    public function queryInvoiceWithDocumentDate(string $documentDate, ?string $sourceId = null): array
    {
        $params = ['documentDate' => $documentDate];
        $sid = $sourceId ?: (string) (config('kolaysoft.source_id') ?? '');
        if ($sid !== '') { $params['sourceId'] = $sid; $params['vkn_tckn'] = $sid; $params['vknTckn'] = $sid; }
        return $this->call('queryInvoiceWithDocumentDate', $params);
    }

    public function queryInvoiceWithReceivedDate(string $receivedDate, ?string $sourceId = null): array
    {
        $params = ['receivedDate' => $receivedDate];
        $sid = $sourceId ?: (string) (config('kolaysoft.source_id') ?? '');
        if ($sid !== '') { $params['sourceId'] = $sid; $params['vkn_tckn'] = $sid; $params['vknTckn'] = $sid; }
        return $this->call('queryInvoiceWithReceivedDate', $params);
    }

    public function queryInvoicesWithGUIDList(array $guids): array
    {
        return $this->call('queryInvoicesWithGUIDList', ['documentUUIDList' => $guids]);
    }

    public function isEFaturaUser(string $vknTckn): array
    {
        // Bazı sürümlerde parametre adı 'identifier' yerine 'vkn_tckn' bekleniyor
        return $this->call('isEFaturaUser', [
            'identifier' => $vknTckn,
            'vkn_tckn' => $vknTckn,
        ]);
    }

    public function getCreditActionsforCustomer(array $args): array
    {
        return $this->call('getCreditActionsforCustomer', $args);
    }

    public function getEAInvoiceStatusWithLogs(string $uuid): array
    {
        return $this->call('getEAInvoiceStatusWithLogs', ['invoiceUuid' => $uuid]);
    }

    public function getOutboxInvoiceStatusWithLogs(string $documentUuid): array
    {
        // Dokümana göre: parametre adı documentUuid
        return $this->call('getOutboxInvoiceStatusWithLogs', ['documentUuid' => $documentUuid]);
    }

    public function setDocumentFlag(array $flagSetter): array
    {
        // Doküman işaretleme bilgisi FlagSetter tipinde beklenir
        return $this->call('setDocumentFlag', ['flagSetter' => $flagSetter]);
    }

    public function queryArchivedInvoice(array $args): array
    {
        return $this->call('queryArchivedInvoice', $args);
    }

    public function queryArchivedInvoiceWithDocumentDate(string $documentDate): array
    {
        return $this->call('queryArchivedInvoiceWithDocumentDate', ['documentDate' => $documentDate]);
    }

    public function queryEnvelope(string $envelopeUUID): array
    {
        return $this->call('queryEnvelope', ['envelopeUUID' => $envelopeUUID]);
    }

    public function queryArchivedOutboxDocument(
        string $paramType,
        string $parameter,
        string $withXML,
        int $fiscalYear
    ): array {
        $allowed = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowed, true)) { $wx = 'NONE'; }
        return $this->call('queryArchivedOutboxDocument', [
            'paramType' => $paramType,
            'parameter' => $parameter,
            'withXML' => $wx,
            'fiscalYear' => $fiscalYear,
        ]);
    }

    public function queryArchivedInboxDocument(
        string $paramType,
        string $parameter,
        string $withXML,
        int $fiscalYear
    ): array {
        $allowed = ['XML','PDF','HTML','NONE'];
        $wx = strtoupper(trim($withXML ?? 'NONE'));
        if (!in_array($wx, $allowed, true)) { $wx = 'NONE'; }
        return $this->call('queryArchivedInboxDocument', [
            'paramType' => $paramType,
            'parameter' => $parameter,
            'withXML' => $wx,
            'fiscalYear' => $fiscalYear,
        ]);
    }

    public function loadOutboxInvoice(array $inputDocumentList): array
    {
        return $this->call('loadOutboxInvoice', [
            'inputDocumentList' => $this->normalizeInputDocumentList($inputDocumentList),
        ]);
    }

    public function loadInboxInvoice(array $inputDocumentList): array
    {
        return $this->call('loadInboxInvoice', [
            'inputDocumentList' => $this->normalizeInputDocumentList($inputDocumentList),
        ]);
    }

    public function queryLoadedOutboxDocument(string $paramType, string $parameter, string $withXML = 'NO'): array
    {
        // LoadInvoiceWS sürümünde withXML: YES|NO
        $allowed = ['YES','NO'];
        $wx = strtoupper(trim($withXML ?? 'NO'));
        if (!in_array($wx, $allowed, true)) { $wx = 'NO'; }
        return $this->call('queryLoadedOutboxDocument', [
            'paramType' => $paramType,
            'parameter' => $parameter,
            'withXML' => $wx,
        ]);
    }

    public function queryLoadedInboxDocument(string $paramType, string $parameter, string $withXML = 'NO'): array
    {
        $allowed = ['YES','NO'];
        $wx = strtoupper(trim($withXML ?? 'NO'));
        if (!in_array($wx, $allowed, true)) { $wx = 'NO'; }
        return $this->call('queryLoadedInboxDocument', [
            'paramType' => $paramType,
            'parameter' => $parameter,
            'withXML' => $wx,
        ]);
    }

    // QueryInvoiceWS: büyük zip indirimi gerektiren metotlar
    public function getUserPKList(): array
    {
        // Parametre yok; HTTP başlığındaki kimlik ile çalışır
        return $this->call('getUserPKList', []);
    }

    public function getUserGBList(): array
    {
        return $this->call('getUserGBList', []);
    }
}

