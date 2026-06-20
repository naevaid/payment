<?php

use App\Http\Controllers\Api\V1\ChargeController;
use App\Http\Controllers\Api\V1\MidtransWebhookController;
use App\Http\Middleware\AuthenticateProjectRequest;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::match(['GET', 'HEAD'], '/callback/midtrans', function () {
        return response()->json([
            'ok' => true,
            'message' => 'Midtrans notification endpoint is reachable.',
        ]);
    });

    Route::post('/callback/midtrans', [MidtransWebhookController::class, 'store']);

    Route::middleware(AuthenticateProjectRequest::class)->group(function (): void {
        $serializeCallbackLog = function ($log): array {
            return [
                'attempt' => $log->attempt,
                'event_type' => $log->event_type,
                'callback_url' => $log->callback_url,
                'success' => $log->success,
                'response_status_code' => $log->response_status_code,
                'error_message' => $log->error_message,
                'delivery_id' => $log->request_headers['X-Payment-Delivery-Id'] ?? null,
                'next_retry_at' => $log->next_retry_at?->toDateTimeString(),
                'dispatched_at' => $log->dispatched_at?->toDateTimeString(),
                'responded_at' => $log->responded_at?->toDateTimeString(),
            ];
        };

        $serializeTransaction = function (Transaction $transaction) use ($serializeCallbackLog): array {
            $latestWebhook = $transaction->webhookLogs()
                ->latest('id')
                ->first();

            $latestCallback = $transaction->callbackForwardingLogs()
                ->latest('id')
                ->first();

            return [
                'gateway_order_id' => $transaction->gateway_order_id,
                'order_id' => $transaction->client_order_id,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'status' => $transaction->status->value,
                'callback_status' => $transaction->callback_status->value,
                'payment_type' => $transaction->payment_type,
                'redirect_url' => $transaction->snap_redirect_url,
                'callback_url' => $transaction->callback_url,
                'metadata' => $transaction->metadata,
                'customer_details' => $transaction->customer_details,
                'timestamps' => [
                    'created_at' => $transaction->created_at?->toDateTimeString(),
                    'updated_at' => $transaction->updated_at?->toDateTimeString(),
                    'paid_at' => $transaction->paid_at?->toDateTimeString(),
                    'expires_at' => $transaction->expires_at?->toDateTimeString(),
                    'last_webhook_at' => $transaction->last_webhook_at?->toDateTimeString(),
                ],
                'latest_webhook' => $latestWebhook ? [
                    'status' => $latestWebhook->transaction_status,
                    'processing_status' => $latestWebhook->processing_status,
                    'is_signature_valid' => $latestWebhook->is_signature_valid,
                    'received_at' => $latestWebhook->received_at?->toDateTimeString(),
                    'processed_at' => $latestWebhook->processed_at?->toDateTimeString(),
                ] : null,
                'latest_callback' => $latestCallback ? [
                    ...$serializeCallbackLog($latestCallback),
                ] : null,
            ];
        };

        $serializeProjectProfile = function ($project): array {
            $appUrl = rtrim((string) config('app.url', ''), '/');
            $apiBaseUrl = filled($appUrl) ? $appUrl.'/api/v1' : '/api/v1';
            $defaultCallbackUrl = $project->default_callback_url;
            $hasDefaultCallbackUrl = filled($defaultCallbackUrl);
            $isReady = $project->is_active && $hasDefaultCallbackUrl;

            return [
                'app_id' => $project->app_id,
                'project_name' => $project->project_name,
                'default_callback_url' => $defaultCallbackUrl,
                'is_active' => $project->is_active,
                'authentication' => [
                    'mode' => 'hmac_signature',
                    'signature_algorithm' => (string) config('payment.auth.signature_algorithm', 'sha256'),
                    'timestamp_tolerance_seconds' => (int) config('payment.auth.timestamp_tolerance_seconds', 300),
                    'request_headers' => [
                        'app_id' => (string) config('payment.auth.app_id_header', 'X-App-ID'),
                        'timestamp' => (string) config('payment.auth.timestamp_header', 'X-Timestamp'),
                        'signature' => (string) config('payment.auth.signature_header', 'X-Payment-Signature'),
                    ],
                    'legacy_secret_header' => [
                        'enabled' => (bool) config('payment.auth.allow_legacy_secret_header', true),
                        'header' => (string) config('payment.auth.secret_key_header', 'X-Secret-Key'),
                    ],
                ],
                'integration' => [
                    'base_url' => $apiBaseUrl,
                    'environment' => config('services.midtrans.is_production', true) ? 'production' : 'sandbox',
                    'currency' => (string) config('payment.currency', 'IDR'),
                    'endpoints' => [
                        'charge' => '/api/v1/charge',
                        'project_profile' => '/api/v1/projects/me',
                        'transaction_lookup' => '/api/v1/transactions/lookup',
                        'transaction_detail' => '/api/v1/transactions/{gatewayOrderId}',
                        'callback_history' => '/api/v1/transactions/{gatewayOrderId}/callback-history',
                    ],
                ],
                'callback' => [
                    'default_url' => $defaultCallbackUrl,
                    'retry' => [
                        'queue' => (string) config('payment.callback.queue', 'payment-callbacks'),
                        'timeout_seconds' => (int) config('payment.callback.timeout_seconds', 10),
                        'max_attempts' => (int) config('payment.callback.max_attempts', 3),
                        'backoff_seconds' => array_map('intval', config('payment.callback.backoff', [60, 300, 900])),
                    ],
                    'delivery_headers' => [
                        'app_id' => 'X-Payment-App-Id',
                        'event' => 'X-Payment-Event',
                        'attempt' => 'X-Payment-Attempt',
                        'timestamp' => 'X-Payment-Timestamp',
                        'delivery_id' => 'X-Payment-Delivery-Id',
                        'signature' => 'X-Payment-Signature',
                    ],
                    'signature' => [
                        'algorithm' => 'sha256',
                        'uses_project_secret_key' => true,
                    ],
                ],
                'readiness' => [
                    'status' => $isReady ? 'ready' : 'action_required',
                    'can_charge' => $project->is_active,
                    'has_default_callback_url' => $hasDefaultCallbackUrl,
                    'checks' => [
                        [
                            'name' => 'project_active',
                            'passed' => $project->is_active,
                            'message' => $project->is_active
                                ? 'Project aktif dan dapat mengakses API tenant.'
                                : 'Project nonaktif dan tidak dapat membuat charge baru.',
                        ],
                        [
                            'name' => 'default_callback_url_configured',
                            'passed' => $hasDefaultCallbackUrl,
                            'message' => $hasDefaultCallbackUrl
                                ? 'Default callback URL sudah terpasang.'
                                : 'Default callback URL belum diatur. Callback per request tetap bisa dikirim bila disediakan.',
                        ],
                        [
                            'name' => 'hmac_signature_auth_ready',
                            'passed' => true,
                            'message' => 'Gunakan HMAC signature untuk integrasi tenant yang disarankan.',
                        ],
                    ],
                ],
            ];
        };

        Route::get('/projects/me', function (Request $request) use ($serializeProjectProfile) {
            $project = $request->attributes->get('project');

            return response()->json([
                'data' => $serializeProjectProfile($project),
            ]);
        });

        Route::post('/charge', [ChargeController::class, 'store']);

        Route::get('/transactions/lookup', function (Request $request) use ($serializeTransaction) {
            $project = $request->attributes->get('project');

            $validated = $request->validate([
                'identifier' => ['required', 'string'],
                'by' => ['nullable', 'in:auto,gateway_order_id,client_order_id'],
            ]);

            $identifier = (string) $validated['identifier'];
            $lookupField = (string) ($validated['by'] ?? 'auto');

            $query = Transaction::query()
                ->where('project_id', $project->id);

            if ($lookupField === 'gateway_order_id') {
                $query->where('gateway_order_id', $identifier);
            } elseif ($lookupField === 'client_order_id') {
                $query->where('client_order_id', $identifier);
            } else {
                $query->where(function ($builder) use ($identifier): void {
                    $builder
                        ->where('gateway_order_id', $identifier)
                        ->orWhere('client_order_id', $identifier);
                });
            }

            $transaction = $query->firstOrFail();

            return response()->json([
                'data' => $serializeTransaction($transaction),
            ]);
        });

        Route::get('/transactions/{gatewayOrderId}', function (Request $request, string $gatewayOrderId) use ($serializeTransaction) {
            $project = $request->attributes->get('project');

            $transaction = Transaction::query()
                ->where('project_id', $project->id)
                ->where('gateway_order_id', $gatewayOrderId)
                ->firstOrFail();

            return response()->json([
                'data' => $serializeTransaction($transaction),
            ]);
        });

        Route::get('/transactions/{gatewayOrderId}/callback-history', function (Request $request, string $gatewayOrderId) use ($serializeCallbackLog) {
            $project = $request->attributes->get('project');

            $validated = $request->validate([
                'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            ]);

            $limit = (int) ($validated['limit'] ?? 5);

            $transaction = Transaction::query()
                ->where('project_id', $project->id)
                ->where('gateway_order_id', $gatewayOrderId)
                ->firstOrFail();

            $logs = $transaction->callbackForwardingLogs()
                ->latest('id')
                ->limit($limit)
                ->get()
                ->map(fn ($log): array => $serializeCallbackLog($log))
                ->values();

            return response()->json([
                'data' => [
                    'gateway_order_id' => $transaction->gateway_order_id,
                    'order_id' => $transaction->client_order_id,
                    'callback_status' => $transaction->callback_status->value,
                    'history' => $logs,
                ],
            ]);
        });
    });
});
