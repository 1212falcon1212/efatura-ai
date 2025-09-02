<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\KolaysoftController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\DespatchController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\AdminController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\MockController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\PaymentController;

Route::get('/health', function () {
    Log::info('healthcheck', ['time' => now()->toISOString()]);
    return response()->json(['status' => 'ok', 'time' => now()->toISOString()]);
});

// Public auth endpoints under versioned namespace (no API key required)
Route::prefix('v1')->group(function () {
    Route::post('/auth/signup', [AuthController::class, 'signup']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/docs/openapi.yaml', [DocsController::class, 'openapi']);
    Route::get('/docs/redoc', [DocsController::class, 'redoc']);
    Route::get('/healthchecks/ping', [DocsController::class, 'healthcheckPing']);
    Route::post('/invitations/accept', [InvitationController::class, 'accept']);

    // Simple mock server (OpenAPI fixture tabanlı) - yalnızca dev ortamı içindir
    if (config('app.env') !== 'production') {
        Route::any('/mock/{any?}', [MockController::class, 'handle'])->where('any', '.*');
    }
});

Route::prefix('v1')->middleware([ApiKeyMiddleware::class])->group(function () {
    Route::get('/organizations/current', function () {
        $org = request()->attributes->get('organization');
        $role = null;
        $authHeader = request()->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $raw = substr($authHeader, 7);
            $hash = hash('sha256', $raw);
            $userId = \DB::table('user_tokens')->where('token', $hash)->value('user_id');
            if ($userId) {
                $role = \DB::table('organization_user')
                    ->where('organization_id', $org->id)
                    ->where('user_id', $userId)
                    ->value('role');
            }
        }
        return response()->json([
            'id' => $org->id,
            'name' => $org->name,
            'createdAt' => $org->created_at,
            'currentUserRole' => $role,
        ]);
    });

    // Auth - protected by API Key but also requires Bearer token for user context
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::post('/auth/update-profile', [AuthController::class, 'updateProfile']);
    Route::get('/auth/org-settings', [AuthController::class, 'orgSettings']);
    Route::post('/auth/org-settings', [AuthController::class, 'updateOrgSettings']);
    Route::get('/auth/organizations', [AuthController::class, 'organizations']);
    Route::get('/auth/api-keys', [AuthController::class, 'apiKeys']);
    Route::post('/auth/api-keys', [AuthController::class, 'createApiKey']);
    Route::post('/auth/api-keys/{id}/revoke', [AuthController::class, 'revokeApiKey']);
    Route::post('/auth/impersonate', [AuthController::class, 'impersonate'])->middleware([RoleMiddleware::class.':owner']);
    Route::get('/audit-logs', [AuditController::class, 'index']);

    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware([IdempotencyMiddleware::class]);
    Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->middleware([IdempotencyMiddleware::class]);
    Route::post('/invoices/{id}/retry', [InvoiceController::class, 'retry'])->middleware([IdempotencyMiddleware::class]);

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::post('/customers', [CustomerController::class, 'store']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    Route::get('/credits/wallet', [CreditController::class, 'wallet']);
    Route::get('/credits/transactions', [CreditController::class, 'transactions']);
    Route::get('/credits/provider-balance', [CreditController::class, 'providerBalance']);
    Route::get('/credits/summary', [CreditController::class, 'summary'])->middleware([RoleMiddleware::class.':owner']);
    Route::get('/credits/analytics', [CreditController::class, 'analytics'])->middleware([RoleMiddleware::class.':owner']);
    Route::post('/credits/reservations/cleanup', [CreditController::class, 'cleanupReservations'])->middleware([RoleMiddleware::class.':owner']);
    Route::post('/credits/settings', [CreditController::class, 'updateSettings']);
    Route::post('/credits/purchase', [CreditController::class, 'purchase']);

    // Plans & Subscriptions (owner/admin)
    Route::get('/plans', [SubscriptionController::class, 'plans']);
    Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
    Route::post('/subscriptions/subscribe', [SubscriptionController::class, 'subscribe'])->middleware([RoleMiddleware::class.':owner']);
    Route::post('/subscriptions/cancel', [SubscriptionController::class, 'cancel'])->middleware([RoleMiddleware::class.':owner']);
    Route::post('/subscriptions/pay', [PaymentController::class, 'createPayment'])->middleware([RoleMiddleware::class.':owner']);
    Route::get('/subscriptions/3d-return', [PaymentController::class, 'threeDReturn']);
    Route::post('/credits/pay', [PaymentController::class, 'payCredits'])->middleware([RoleMiddleware::class.':owner']);
    Route::get('/billing/cards', function () {
        $org = request()->attributes->get('organization');
        return response()->json(['data' => \App\Models\SavedCard::where('organization_id', $org->id)->orderByDesc('id')->get()]);
    });
    Route::delete('/billing/cards/{id}', function ($id) {
        $org = request()->attributes->get('organization');
        \App\Models\SavedCard::where('organization_id', $org->id)->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    });

    // Invitations (owner/admin)
    Route::get('/invitations', [InvitationController::class, 'list'])->middleware([RoleMiddleware::class.':admin']);
    Route::post('/invitations', [InvitationController::class, 'create'])->middleware([RoleMiddleware::class.':admin']);

    // Admin: organizations ve plans CRUD (sadece owner)
    Route::get('/admin/organizations', [AdminController::class, 'organizations'])->middleware([RoleMiddleware::class.':owner']);
    Route::post('/admin/credits/adjust', [AdminController::class, 'adjustCredits'])->middleware([RoleMiddleware::class.':owner']);
    Route::get('/admin/dead-letters', [AdminController::class, 'deadLetters'])->middleware([RoleMiddleware::class.':owner']);
    Route::delete('/admin/dead-letters/{id}', [AdminController::class, 'deadLetterDelete'])->middleware([RoleMiddleware::class.':owner']);
    Route::post('/admin/dead-letters/{id}/retry', [AdminController::class, 'deadLetterRetry'])->middleware([RoleMiddleware::class.':owner']);
    Route::post('/admin/webhooks/replay-bulk', [AdminController::class, 'replayWebhooksBulk'])->middleware([RoleMiddleware::class.':owner']);
    Route::post('/admin/invoices/requeue-bulk', [AdminController::class, 'requeueInvoicesBulk'])->middleware([RoleMiddleware::class.':owner']);
    Route::get('/admin/plans', [AdminController::class, 'plansIndex'])->middleware([RoleMiddleware::class.':owner']);
    Route::post('/admin/plans', [AdminController::class, 'plansCreate'])->middleware([RoleMiddleware::class.':owner']);
    Route::put('/admin/plans/{id}', [AdminController::class, 'plansUpdate'])->middleware([RoleMiddleware::class.':owner']);
    Route::get('/admin/coupons', [AdminController::class, 'coupons'])->middleware([RoleMiddleware::class.':owner']);
    Route::post('/admin/coupons', [AdminController::class, 'couponsCreate'])->middleware([RoleMiddleware::class.':owner']);
    Route::delete('/admin/coupons/{id}', [AdminController::class, 'couponsDelete'])->middleware([RoleMiddleware::class.':owner']);

    // Webhooks
    Route::get('/webhooks/subscriptions', [WebhookController::class, 'subscriptions']);
    Route::post('/webhooks/subscriptions', [WebhookController::class, 'createSubscription']);
    Route::delete('/webhooks/subscriptions/{id}', [WebhookController::class, 'deleteSubscription']);
    Route::get('/webhooks/deliveries', [WebhookController::class, 'deliveries']);
    Route::post('/webhooks/deliveries/{id}/replay', [WebhookController::class, 'replay']);

    // Public vendor-agnostic
    Route::get('/vouchers', [VoucherController::class, 'index']);
    Route::get('/vouchers/{id}', [VoucherController::class, 'show']);
    Route::post('/vouchers', [VoucherController::class, 'store'])->middleware([IdempotencyMiddleware::class]);
    Route::post('/vouchers/{id}/cancel', [VoucherController::class, 'cancel'])->middleware([IdempotencyMiddleware::class]);
    Route::post('/vouchers/{id}/retry', [VoucherController::class, 'retry'])->middleware([IdempotencyMiddleware::class]);

    Route::get('/despatches', [DespatchController::class, 'index']);
    Route::get('/despatches/{id}', [DespatchController::class, 'show']);
    Route::post('/despatches', [DespatchController::class, 'store'])->middleware([IdempotencyMiddleware::class]);
    Route::post('/despatches/{id}/cancel', [DespatchController::class, 'cancel'])->middleware([IdempotencyMiddleware::class]);
    Route::post('/despatches/{id}/retry', [DespatchController::class, 'retry'])->middleware([IdempotencyMiddleware::class]);

    // Internal provider test endpoints (korumalı: API Key gerektirir)
    Route::prefix('internal/providers/kolaysoft')->group(function () {
        Route::get('/credit-count', [KolaysoftController::class, 'creditCount']);
        Route::get('/is-efatura-user', [KolaysoftController::class, 'isEfaturaUser']);
        Route::post('/control-xml', [KolaysoftController::class, 'controlXml']);
        Route::get('/last-invoice', [KolaysoftController::class, 'lastInvoice']);
        Route::post('/send-invoice', [KolaysoftController::class, 'sendInvoice']);
        Route::post('/cancel', [KolaysoftController::class, 'cancel']);
        Route::get('/query-invoice', [KolaysoftController::class, 'queryInvoice']);
        Route::get('/query-with-local-id', [KolaysoftController::class, 'queryWithLocalId']);
        Route::get('/status-with-logs', [KolaysoftController::class, 'statusWithLogs']);
        Route::post('/update-invoice', [KolaysoftController::class, 'updateInvoice']);
        Route::post('/set-email-sent', [KolaysoftController::class, 'setEmailSent']);
        Route::get('/credit-space', [KolaysoftController::class, 'creditSpace']);
        Route::get('/query-with-document-date', [KolaysoftController::class, 'queryWithDocumentDate']);
        Route::get('/query-with-received-date', [KolaysoftController::class, 'queryWithReceivedDate']);
        Route::get('/inbox/with-received-date', [KolaysoftController::class, 'inboxWithReceivedDate']);
        Route::post('/inbox/load', [KolaysoftController::class, 'inboxLoad']);
        Route::post('/query-with-guid-list', [KolaysoftController::class, 'queryWithGuidList']);
        Route::get('/credit-actions', [KolaysoftController::class, 'creditActions']);
        Route::get('/archived/query', [KolaysoftController::class, 'queryArchived']);
        Route::get('/archived/query-with-document-date', [KolaysoftController::class, 'queryArchivedWithDocumentDate']);

        // E-MAKBUZ (e-SMM / e-MM)
        Route::post('/voucher/send-smm', [KolaysoftController::class, 'voucherSendSMM']);
        Route::post('/voucher/send-mm', [KolaysoftController::class, 'voucherSendMM']);
        Route::post('/voucher/update-smm', [KolaysoftController::class, 'voucherUpdateSMM']);
        Route::post('/voucher/update-mm', [KolaysoftController::class, 'voucherUpdateMM']);
        Route::post('/voucher/cancel-smm', [KolaysoftController::class, 'voucherCancelSMM']);
        Route::post('/voucher/cancel-mm', [KolaysoftController::class, 'voucherCancelMM']);
        Route::get('/voucher/last-smm', [KolaysoftController::class, 'voucherLastSMM']);
        Route::get('/voucher/last-mm', [KolaysoftController::class, 'voucherLastMM']);
        Route::get('/voucher/query', [KolaysoftController::class, 'voucherQuery']);
        Route::get('/voucher/query-with-local-id', [KolaysoftController::class, 'voucherQueryWithLocalId']);
        Route::get('/voucher/query-with-document-date', [KolaysoftController::class, 'voucherQueryWithDocumentDate']);
        Route::get('/voucher/query-with-received-date', [KolaysoftController::class, 'voucherQueryWithReceivedDate']);
        Route::post('/voucher/query-with-guid-list', [KolaysoftController::class, 'voucherQueryWithGuidList']);
        Route::post('/voucher/set-smm-email-sent', [KolaysoftController::class, 'voucherSetSmmEmailSent']);
        Route::post('/voucher/set-mm-email-sent', [KolaysoftController::class, 'voucherSetMmEmailSent']);
        Route::post('/voucher/set-smm-document-flag', [KolaysoftController::class, 'voucherSetSmmDocumentFlag']);
        Route::post('/voucher/set-mm-document-flag', [KolaysoftController::class, 'voucherSetMmDocumentFlag']);
        Route::post('/voucher/control-xml-smm', [KolaysoftController::class, 'voucherControlXmlSmm']);
        Route::post('/voucher/control-xml-mm', [KolaysoftController::class, 'voucherControlXmlMm']);

        // E-İRSALİYE (Despatch)
        Route::post('/despatch/send', [KolaysoftController::class, 'despatchSendDespatch']);
        Route::post('/despatch/send-receipt-advice', [KolaysoftController::class, 'despatchSendReceiptAdvice']);
        Route::post('/despatch/control-xml', [KolaysoftController::class, 'despatchControlDespatchXML']);
        Route::post('/despatch/control-receipt-xml', [KolaysoftController::class, 'despatchControlReceiptAdviceXML']);
        Route::post('/despatch/update', [KolaysoftController::class, 'despatchUpdateDespatchXML']);
        Route::get('/despatch/gb-list', [KolaysoftController::class, 'despatchGetCustomerGBList']);
        Route::get('/despatch/pk-list', [KolaysoftController::class, 'despatchGetCustomerPKList']);
        Route::get('/despatch/query-users', [KolaysoftController::class, 'despatchQueryUsers']);
        Route::get('/despatch/last-id-and-date', [KolaysoftController::class, 'despatchGetLastDepatchIdAndDate']);
        Route::get('/despatch/outbox', [KolaysoftController::class, 'despatchQueryOutboxDocument']);
        Route::get('/despatch/outbox-with-document-date', [KolaysoftController::class, 'despatchQueryOutboxDocumentWithDocumentDate']);
        Route::get('/despatch/outbox-with-received-date', [KolaysoftController::class, 'despatchQueryOutboxDocumentWithReceivedDate']);
        Route::get('/despatch/outbox-with-local-id', [KolaysoftController::class, 'despatchQueryOutboxDocumentWithLocalId']);
        Route::post('/despatch/outbox-with-guid-list', [KolaysoftController::class, 'despatchQueryOutboxDocumentWithGUIDList']);
        Route::get('/despatch/inbox', [KolaysoftController::class, 'despatchQueryInboxDocument']);
        Route::get('/despatch/inbox-with-document-date', [KolaysoftController::class, 'despatchQueryInboxDocumentWithDocumentDate']);
        Route::get('/despatch/inbox-with-received-date', [KolaysoftController::class, 'despatchQueryInboxDocumentWithReceivedDate']);
        Route::post('/despatch/inbox-with-guid-list', [KolaysoftController::class, 'despatchQueryInboxDocumentWithGUIDList']);
        Route::post('/despatch/set-taken-from-entegrator', [KolaysoftController::class, 'despatchSetTakenFromEntegrator']);
        Route::get('/despatch/envelope', [KolaysoftController::class, 'despatchQueryEnvelope']);
    });
});
