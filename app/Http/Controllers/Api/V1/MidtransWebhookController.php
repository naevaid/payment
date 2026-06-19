<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ForwardTransactionCallback;
use App\Models\MidtransWebhookLog;
use App\Models\Transaction;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MidtransWebhookController extends Controller
{
    public function store(Request $request, MidtransService $midtransService): JsonResponse
    {
        $payload = $request->all();
        $signatureValid = $midtransService->verifySignature($payload);

        $webhookLog = MidtransWebhookLog::create([
            'order_id' => $payload['order_id'] ?? null,
            'midtrans_transaction_id' => $payload['transaction_id'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'signature_key' => $payload['signature_key'] ?? null,
            'payload' => $payload,
            'headers' => Arr::map($request->headers->all(), fn (array $values) => implode(', ', $values)),
            'is_signature_valid' => $signatureValid,
            'processing_status' => 'received',
            'received_at' => now(),
        ]);

        if (! $signatureValid) {
            $webhookLog->forceFill([
                'processing_status' => 'rejected',
                'notes' => 'Invalid Midtrans signature.',
                'processed_at' => now(),
            ])->save();

            return response()->json([
                'message' => 'Invalid signature.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $transaction = Transaction::where('gateway_order_id', $payload['order_id'] ?? '')
            ->first();

        if (! $transaction) {
            $webhookLog->forceFill([
                'processing_status' => 'failed',
                'notes' => 'Transaction not found for gateway order ID.',
                'processed_at' => now(),
            ])->save();

            return response()->json([
                'message' => 'Transaction not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        DB::transaction(function () use ($payload, $midtransService, $transaction, $webhookLog): void {
            $transaction->forceFill([
                'status' => $midtransService->mapTransactionStatus($payload),
                'callback_status' => \App\Enums\CallbackStatus::Queued,
                'payment_type' => $payload['payment_type'] ?? $transaction->payment_type,
                'midtrans_transaction_id' => $payload['transaction_id'] ?? $transaction->midtrans_transaction_id,
                'midtrans_payload' => $payload,
                'last_webhook_at' => now(),
                'paid_at' => in_array($payload['transaction_status'] ?? null, ['capture', 'settlement'], true)
                    ? now()
                    : $transaction->paid_at,
            ])->save();

            $webhookLog->forceFill([
                'transaction_id' => $transaction->id,
                'processing_status' => 'processed',
                'processed_at' => now(),
            ])->save();
        });

        ForwardTransactionCallback::dispatch($transaction->id)
            ->onQueue(config('payment.callback.queue'));

        return response()->json([
            'status' => 'accepted',
        ]);
    }
}
