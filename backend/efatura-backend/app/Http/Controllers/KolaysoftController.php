<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Kolaysoft\KolaysoftClient;
use Illuminate\Support\Facades\Log;

class KolaysoftController extends Controller
{
    public function creditCount(Request $request)
    {
        $requestId = uniqid('req_', true);
        try {
            /** @var KolaysoftClient $client */
            $client = app(KolaysoftClient::class);
            // Önce parametre ile dene, yoksa varsayılan source_id ile fallback yap
            $inputVkn = $request->query('vkn_tckn');
            if ($inputVkn) {
                // Canlıda CustomerCredit bazı sürümlerde yok; doğrudan GetCustomerCreditCount'u çağır
                $res = $client->getCustomerCreditCount($inputVkn);
                Log::info('provider.creditCount (getCustomerCreditCount direct)', [
                    'requestId' => $requestId,
                    'vkn_tckn' => $inputVkn,
                    'response_ok' => $res['success'] ?? null,
                ]);
                return response()->json($res);
            }

            $vkn = config('kolaysoft.source_id');
            $res = $client->getCustomerCreditCount();
            Log::info('provider.creditCount (fallback getCustomerCreditCount)', [
                'requestId' => $requestId,
                'vkn_tckn' => $vkn,
                'response_ok' => $res['success'] ?? null,
            ]);
            return response()->json($res);
        } catch (\Throwable $e) {
            Log::error('provider.creditCount error', [
                'requestId' => $requestId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $request->query(),
            ]);
            return response()->json([
                'code' => 'provider_error',
                'message' => 'Sağlayıcı kredi sorgusunda hata oluştu',
                'requestId' => $requestId,
            ], 500);
        }
    }

    public function isEfaturaUser(Request $request)
    {
        $requestId = uniqid('req_', true);
        try {
            /** @var KolaysoftClient $client */
            $client = app(KolaysoftClient::class);
            $vknTckn = $request->query('vknTckn');
            if (!$vknTckn) {
                return response()->json(['code' => 'invalid_request', 'message' => 'vknTckn is required'], 400);
            }
            $res = $client->isEFaturaUser($vknTckn);
            Log::info('provider.isEfaturaUser', [ 'requestId' => $requestId, 'vknTckn' => $vknTckn, 'ok' => $res['success'] ?? null ]);
            return response()->json($res);
        } catch (\Throwable $e) {
            Log::error('provider.isEfaturaUser error', [ 'requestId' => $requestId, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString() ]);
            return response()->json(['code' => 'provider_error', 'message' => 'Sağlayıcı sorgusunda hata', 'requestId' => $requestId], 500);
        }
    }

    public function controlXml(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['xml' => ['required','string']]);
        return response()->json($client->controlInvoiceXML($data['xml']));
    }

    public function lastInvoice()
    {
        $requestId = uniqid('req_', true);
        try {
            /** @var KolaysoftClient $client */
            $client = app(KolaysoftClient::class);
            $res = $client->getLastInvoiceIdAndDate();
            Log::info('provider.getLastInvoiceIdAndDate', [ 'requestId' => $requestId, 'ok' => $res['success'] ?? null ]);
            return response()->json($res);
        } catch (\Throwable $e) {
            Log::error('provider.getLastInvoiceIdAndDate error', [ 'requestId' => $requestId, 'message' => $e->getMessage() ]);
            return response()->json(['code' => 'provider_error', 'message' => 'Sağlayıcı son fatura sorgusunda hata', 'requestId' => $requestId], 500);
        }
    }

    public function sendInvoice(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'xml' => ['nullable','string'],
            'documentUUID' => ['nullable','string'],
            'inputDocument' => ['nullable','array'],
        ]);

        if (!empty($data['xml'])) {
            $input = [
                'documentUUID' => $data['documentUUID'] ?? null,
                'xml' => $data['xml'],
            ];
            return response()->json($client->sendInvoice($input));
        }

        if (!empty($data['inputDocument'])) {
            return response()->json($client->sendInvoice($data['inputDocument']));
        }

        return response()->json(['code' => 'invalid_request', 'message' => 'Provide xml or inputDocument'], 400);
    }

    public function cancel(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'invoiceUuid' => ['required','string'],
            'cancelReason' => ['nullable','string'],
            'cancelDate' => ['nullable','date'],
        ]);
        return response()->json($client->cancelInvoice(
            $data['invoiceUuid'],
            $data['cancelReason'] ?? 'Customer request',
            $data['cancelDate'] ?? now()->toDateString()
        ));
    }

    public function queryInvoice(Request $request, KolaysoftClient $client)
    {
        $args = $request->validate(['*' => []]);
        return response()->json($client->queryInvoice($args));
    }

    public function queryWithLocalId(Request $request, KolaysoftClient $client)
    {
        $localId = $request->query('localId');
        if (!$localId) {
            return response()->json(['code' => 'invalid_request', 'message' => 'localId is required'], 400);
        }
        return response()->json($client->queryInvoiceWithLocalId($localId));
    }

    public function statusWithLogs(Request $request)
    {
        $requestId = uniqid('req_', true);
        try {
            /** @var KolaysoftClient $client */
            $client = app(KolaysoftClient::class);
            $uuid = $request->query('uuid');
            if (!$uuid) {
                return response()->json(['code' => 'invalid_request', 'message' => 'uuid is required'], 400);
            }
            $res = $client->getEAInvoiceStatusWithLogs($uuid);
            Log::info('provider.getEAInvoiceStatusWithLogs', [ 'requestId' => $requestId, 'uuid' => $uuid, 'ok' => $res['success'] ?? null ]);
            return response()->json($res);
        } catch (\Throwable $e) {
            Log::error('provider.getEAInvoiceStatusWithLogs error', [ 'requestId' => $requestId, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString() ]);
            return response()->json(['code' => 'provider_error', 'message' => 'Sağlayıcı log sorgusunda hata', 'requestId' => $requestId], 500);
        }
    }

    public function updateInvoice(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'inputDocumentList' => ['required','array','min:1'],
        ]);
        return response()->json($client->updateInvoice($data['inputDocumentList']));
    }

    public function setEmailSent(Request $request, KolaysoftClient $client)
    {
        $args = $request->validate(['*' => []]);
        return response()->json($client->setEmailSent($args));
    }

    public function creditSpace(KolaysoftClient $client)
    {
        return response()->json($client->getCustomerCreditSpace());
    }

    public function queryWithDocumentDate(Request $request, KolaysoftClient $client)
    {
        $date = $request->query('documentDate');
        if (!$date) {
            return response()->json(['code' => 'invalid_request', 'message' => 'documentDate is required'], 400);
        }
        return response()->json($client->queryInvoiceWithDocumentDate($date));
    }

    public function queryWithReceivedDate(Request $request, KolaysoftClient $client)
    {
        $date = $request->query('receivedDate');
        if (!$date) {
            return response()->json(['code' => 'invalid_request', 'message' => 'receivedDate is required'], 400);
        }
        return response()->json($client->queryInvoiceWithReceivedDate($date));
    }

    // Inbox (gelen kutusu) için tarih bazlı sorgu (QueryInboxDocumentWithReceivedDate)
    public function inboxWithReceivedDate(Request $request, KolaysoftClient $client)
    {
        $date = $request->query('date');
        if (!$date) {
            return response()->json(['code' => 'invalid_request', 'message' => 'date is required'], 400);
        }
        // Aynı günü başlangıç/bitiş olarak kullan; belge tipi/queried/withXML defaultları ALL/NONE
        $sid = (string) (config('kolaysoft.source_id') ?? '');
        $res = $client->queryInboxDocumentWithReceivedDate($date, $date, 'ALL', 'ALL', 'NONE', 'ALL', null, $sid);
        return response()->json($res);
    }

    // Inbox faturalarını sisteme yükle (LoadInboxInvoice)
    public function inboxLoad(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'inputDocumentList' => ['required','array','min:1'],
        ]);
        return response()->json($client->loadInboxInvoice($data['inputDocumentList']));
    }

    public function queryWithGuidList(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'documentUUIDList' => ['required','array','min:1'],
        ]);
        return response()->json($client->queryInvoicesWithGUIDList($data['documentUUIDList']));
    }

    public function creditActions(Request $request, KolaysoftClient $client)
    {
        $args = $request->validate(['*' => []]);
        return response()->json($client->getCreditActionsforCustomer($args));
    }

    public function queryArchived(Request $request, KolaysoftClient $client)
    {
        $args = $request->validate(['*' => []]);
        return response()->json($client->queryArchivedInvoice($args));
    }

    public function queryArchivedWithDocumentDate(Request $request, KolaysoftClient $client)
    {
        $date = $request->query('documentDate');
        if (!$date) {
            return response()->json(['code' => 'invalid_request', 'message' => 'documentDate is required'], 400);
        }
        return response()->json($client->queryArchivedInvoiceWithDocumentDate($date));
    }

    // ===== E-MAKBUZ (e-SMM / e-MM) INTERNAL ENDPOINTS =====
    public function voucherSendSMM(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['voucherXMLList' => ['required','array','min:1']]);
        return response()->json($client->sendSMM($data['voucherXMLList']));
    }

    public function voucherSendMM(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['voucherXMLList' => ['required','array','min:1']]);
        return response()->json($client->sendMM($data['voucherXMLList']));
    }

    public function voucherUpdateSMM(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['voucherXMLList' => ['required','array','min:1']]);
        return response()->json($client->updateSMM($data['voucherXMLList']));
    }

    public function voucherUpdateMM(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['voucherXMLList' => ['required','array','min:1']]);
        return response()->json($client->updateMM($data['voucherXMLList']));
    }

    public function voucherCancelSMM(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'voucherUuid' => ['required','string'],
            'cancelReason' => ['required','string'],
            'cancelDate' => ['required','date'],
        ]);
        return response()->json($client->cancelSMM($data['voucherUuid'], $data['cancelReason'], $data['cancelDate']));
    }

    public function voucherCancelMM(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'voucherUuid' => ['required','string'],
            'cancelReason' => ['required','string'],
            'cancelDate' => ['required','date'],
        ]);
        return response()->json($client->cancelMM($data['voucherUuid'], $data['cancelReason'], $data['cancelDate']));
    }

    public function voucherLastSMM(KolaysoftClient $client, Request $request)
    {
        $params = [
            'sourceId' => $request->query('source_id'),
            'documentIdPrefixList' => $request->query('documentIdPrefixList'),
        ];
        return response()->json($client->getLastSMMIdAndDate($params['sourceId'], is_array($params['documentIdPrefixList']) ? $params['documentIdPrefixList'] : null));
    }

    public function voucherLastMM(KolaysoftClient $client, Request $request)
    {
        $params = [
            'sourceId' => $request->query('source_id'),
            'documentIdPrefixList' => $request->query('documentIdPrefixList'),
        ];
        return response()->json($client->getLastMMIdAndDate($params['sourceId'], is_array($params['documentIdPrefixList']) ? $params['documentIdPrefixList'] : null));
    }

    public function voucherQuery(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'paramType' => ['required','string'],
            'parameter' => ['required','string'],
            'voucherType' => ['required','string'],
            'withXML' => ['nullable','string'],
        ]);
        return response()->json($client->queryVouchers($data['paramType'], $data['parameter'], $data['voucherType'], $data['withXML'] ?? 'NONE'));
    }

    public function voucherQueryWithLocalId(Request $request, KolaysoftClient $client)
    {
        $localId = $request->query('localId');
        if (!$localId) { return response()->json(['code' => 'invalid_request', 'message' => 'localId is required'], 400); }
        return response()->json($client->queryVouchersWithLocalId($localId));
    }

    public function voucherQueryWithDocumentDate(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'startDate' => ['required','string'],
            'endDate' => ['required','string'],
            'voucherType' => ['required','string'],
            'withXML' => ['nullable','string'],
            'minRecordId' => ['nullable','string'],
        ]);
        return response()->json($client->queryVouchersWithDocumentDate($data['startDate'], $data['endDate'], $data['voucherType'], $data['withXML'] ?? 'NONE', $data['minRecordId'] ?? null));
    }

    public function voucherQueryWithReceivedDate(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'startDate' => ['required','string'],
            'endDate' => ['required','string'],
            'voucherType' => ['required','string'],
            'withXML' => ['nullable','string'],
        ]);
        return response()->json($client->queryVouchersWithReceivedDate($data['startDate'], $data['endDate'], $data['voucherType'], $data['withXML'] ?? 'NONE'));
    }

    public function voucherQueryWithGuidList(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'guidList' => ['required','array','min:1'],
            'voucherType' => ['required','string'],
        ]);
        return response()->json($client->queryVouchersWithGUIDList($data['guidList'], $data['voucherType']));
    }

    public function voucherSetSmmEmailSent(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['voucher_uuid_list' => ['required','array','min:1']]);
        return response()->json($client->setSmmEmailSent($data['voucher_uuid_list']));
    }

    public function voucherSetMmEmailSent(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['voucher_uuid_list' => ['required','array','min:1']]);
        return response()->json($client->setMmEmailSent($data['voucher_uuid_list']));
    }

    public function voucherSetSmmDocumentFlag(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['flagSetter' => ['required','array']]);
        return response()->json($client->setSmmDocumentFlag($data['flagSetter']));
    }

    public function voucherSetMmDocumentFlag(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['flagSetter' => ['required','array']]);
        return response()->json($client->setMmDocumentFlag($data['flagSetter']));
    }

    public function voucherControlXmlSmm(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['xml' => ['required','string']]);
        return response()->json($client->controlXmlSmm($data['xml']));
    }

    public function voucherControlXmlMm(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['xml' => ['required','string']]);
        return response()->json($client->controlXmlMm($data['xml']));
    }

    // ===== E-İRSALİYE (Despatch) INTERNAL ENDPOINTS =====
    public function despatchSendDespatch(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['inputDocumentList' => ['required','array','min:1']]);
        return response()->json($client->sendDespatch($data['inputDocumentList']));
    }

    public function despatchSendReceiptAdvice(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['inputDocumentList' => ['required','array','min:1']]);
        return response()->json($client->sendReceiptAdvice($data['inputDocumentList']));
    }

    public function despatchControlDespatchXML(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['xml' => ['required','string']]);
        return response()->json($client->controlDespatchXML($data['xml']));
    }

    public function despatchControlReceiptAdviceXML(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['xml' => ['required','string']]);
        return response()->json($client->controlReceiptAdviceXML($data['xml']));
    }

    public function despatchUpdateDespatchXML(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['inputDocumentList' => ['required','array','min:1']]);
        return response()->json($client->updateDespatchXML($data['inputDocumentList']));
    }

    public function despatchGetCustomerGBList(KolaysoftClient $client)
    {
        return response()->json($client->getCustomerGBList());
    }

    public function despatchGetCustomerPKList(KolaysoftClient $client)
    {
        return response()->json($client->getCustomerPKList());
    }

    public function despatchQueryUsers(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'startDate' => ['nullable','string'],
            'finishDate' => ['nullable','string'],
            'vkn_tckn' => ['nullable','string'],
        ]);
        return response()->json($client->despatchQueryUsers($data['startDate'] ?? null, $data['finishDate'] ?? null, $data['vkn_tckn'] ?? null));
    }

    public function despatchGetLastDepatchIdAndDate(Request $request, KolaysoftClient $client)
    {
        $sid = $request->query('source_id');
        $prefix = $request->query('documentIdPrefix');
        return response()->json($client->getLastDepatchIdAndDate($sid, is_array($prefix) ? $prefix : null));
    }

    public function despatchQueryOutboxDocument(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'paramType' => ['required','string'],
            'parameter' => ['required','string'],
            'withXML' => ['nullable','string'],
        ]);
        return response()->json($client->despatchQueryOutboxDocument($data['paramType'], $data['parameter'], $data['withXML'] ?? 'NONE'));
    }

    public function despatchQueryOutboxDocumentWithDocumentDate(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'startDate' => ['required','string'],
            'endDate' => ['required','string'],
            'documentType' => ['required','string'],
            'queried' => ['nullable','string'],
            'withXML' => ['nullable','string'],
        ]);
        return response()->json($client->despatchQueryOutboxDocumentWithDocumentDate($data['startDate'], $data['endDate'], $data['documentType'], $data['queried'] ?? 'ALL', $data['withXML'] ?? 'NONE'));
    }

    public function despatchQueryOutboxDocumentWithReceivedDate(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'startDate' => ['required','string'],
            'endDate' => ['required','string'],
            'documentType' => ['required','string'],
            'queried' => ['nullable','string'],
            'withXML' => ['nullable','string'],
        ]);
        return response()->json($client->despatchQueryOutboxDocumentWithReceivedDate($data['startDate'], $data['endDate'], $data['documentType'], $data['queried'] ?? 'ALL', $data['withXML'] ?? 'NONE'));
    }

    public function despatchQueryOutboxDocumentWithLocalId(Request $request, KolaysoftClient $client)
    {
        $localId = $request->query('localId');
        if (!$localId) { return response()->json(['code' => 'invalid_request', 'message' => 'localId is required'], 400); }
        return response()->json($client->despatchQueryOutboxDocumentWithLocalId($localId));
    }

    public function despatchQueryOutboxDocumentWithGUIDList(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'guidList' => ['required','array','min:1'],
            'documentType' => ['required','string'],
        ]);
        return response()->json($client->despatchQueryOutboxDocumentWithGUIDList($data['guidList'], $data['documentType']));
    }

    public function despatchQueryInboxDocument(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'paramType' => ['required','string'],
            'parameter' => ['required','string'],
            'withXML' => ['nullable','string'],
        ]);
        return response()->json($client->despatchQueryInboxDocument($data['paramType'], $data['parameter'], $data['withXML'] ?? 'NONE'));
    }

    public function despatchQueryInboxDocumentWithDocumentDate(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'startDate' => ['required','string'],
            'endDate' => ['required','string'],
            'documentType' => ['required','string'],
            'queried' => ['nullable','string'],
            'withXML' => ['nullable','string'],
            'takenFromEntegrator' => ['nullable','string'],
            'minRecordId' => ['nullable','string'],
        ]);
        return response()->json($client->despatchQueryInboxDocumentWithDocumentDate(
            $data['startDate'], $data['endDate'], $data['documentType'], $data['queried'] ?? 'ALL', $data['withXML'] ?? 'NONE', $data['takenFromEntegrator'] ?? 'ALL', $data['minRecordId'] ?? null
        ));
    }

    public function despatchQueryInboxDocumentWithReceivedDate(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'startDate' => ['required','string'],
            'endDate' => ['required','string'],
            'documentType' => ['required','string'],
            'queried' => ['nullable','string'],
            'withXML' => ['nullable','string'],
            'takenFromEntegrator' => ['nullable','string'],
            'minRecordId' => ['nullable','string'],
        ]);
        return response()->json($client->despatchQueryInboxDocumentWithReceivedDate(
            $data['startDate'], $data['endDate'], $data['documentType'], $data['queried'] ?? 'ALL', $data['withXML'] ?? 'NONE', $data['takenFromEntegrator'] ?? 'ALL', $data['minRecordId'] ?? null
        ));
    }

    public function despatchQueryInboxDocumentWithGUIDList(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate([
            'guidList' => ['required','array','min:1'],
            'documentType' => ['required','string'],
        ]);
        return response()->json($client->despatchQueryInboxDocumentWithGUIDList($data['guidList'], $data['documentType']));
    }

    public function despatchSetTakenFromEntegrator(Request $request, KolaysoftClient $client)
    {
        $data = $request->validate(['documentUuid' => ['required','array','min:1']]);
        return response()->json($client->despatchSetTakenFromEntegrator($data['documentUuid']));
    }

    public function despatchQueryEnvelope(Request $request, KolaysoftClient $client)
    {
        $envelopeUUID = $request->query('envelopeUUID');
        if (!$envelopeUUID) { return response()->json(['code' => 'invalid_request', 'message' => 'envelopeUUID is required'], 400); }
        return response()->json($client->despatchQueryEnvelope($envelopeUUID));
    }
}

