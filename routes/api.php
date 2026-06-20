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

        Route::get('/transactions/lookup', function (Request $request) {
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
                'data' => [
                    'gateway_order_id' => $transaction->gateway_order_id,
                    'order_id' => $transaction->client_order_id,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status->value,
                    'callback_status' => $transaction->callback_status->value,
                    'payment_type' => $transaction->payment_type,
                    'redirect_url' => $transaction->snap_redirect_url,
                ],
            ]);
        });

        Route::get('/transactions/{gatewayOrderId}', function (Request $request, string $gatewayOrderId) {
            $project = $request->attributes->get('project');

            $transaction = Transaction::query()
                ->where('project_id', $project->id)
                ->where('gateway_order_id', $gatewayOrderId)
                ->firstOrFail();

            return response()->json([
                'data' => [
                    'gateway_order_id' => $transaction->gateway_order_id,
                    'order_id' => $transaction->client_order_id,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status->value,
                    'callback_status' => $transaction->callback_status->value,
                    'payment_type' => $transaction->payment_type,
                    'redirect_url' => $transaction->snap_redirect_url,
                ],
            ]);
        });
    });
});
