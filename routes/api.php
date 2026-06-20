<?php

use App\Http\Controllers\Api\V1\ChargeController;
use App\Http\Controllers\Api\V1\MidtransWebhookController;
use App\Http\Middleware\AuthenticateProjectRequest;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/callback/midtrans', [MidtransWebhookController::class, 'store']);

    Route::middleware(AuthenticateProjectRequest::class)->group(function (): void {
        $serializeTransaction = function (Transaction $transaction): array {
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
                    'attempt' => $latestCallback->attempt,
                    'success' => $latestCallback->success,
                    'response_status_code' => $latestCallback->response_status_code,
                    'error_message' => $latestCallback->error_message,
                    'next_retry_at' => $latestCallback->next_retry_at?->toDateTimeString(),
                    'dispatched_at' => $latestCallback->dispatched_at?->toDateTimeString(),
                    'responded_at' => $latestCallback->responded_at?->toDateTimeString(),
                ] : null,
            ];
        };

        Route::get('/projects/me', function (Request $request) {
            $project = $request->attributes->get('project');

            return response()->json([
                'data' => [
                    'app_id' => $project->app_id,
                    'project_name' => $project->project_name,
                    'default_callback_url' => $project->default_callback_url,
                    'is_active' => $project->is_active,
                ],
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
    });
});
