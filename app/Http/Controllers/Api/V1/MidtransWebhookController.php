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
        $isReachabilityCheck = $this->isReachabilityCheck($request, $payload);
        $signatureValid = $isReachabilityCheck
            ? false
            : $midtransService->verifySignature($payload);

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

        if ($isReachabilityCheck) {
            $webhookLog->forceFill([
                'processing_status' => 'reachable_check',
                'notes' => 'Midtrans notification URL reachability check received.',
                'processed_at' => now(),
            ])->save();

            return response()->json([
                'ok' => true,
                'message' => 'Midtrans notification endpoint is reachable.',
            ]);
        }

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

        $mappedStatus = $midtransService->mapTransactionStatus($payload);

        if ($this->isDuplicateWebhook($transaction, $payload, $mappedStatus->value)) {
            $webhookLog->forceFill([
                'transaction_id' => $transaction->id,
                'processing_status' => 'duplicate',
                'notes' => 'Duplicate Midtrans webhook received with no state change.',
                'processed_at' => now(),
            ])->save();

            return response()->json([
                'status' => 'accepted',
                'duplicate' => true,
            ]);
        }

        DB::transaction(function () use ($payload, $transaction, $webhookLog, $mappedStatus): void {
            $transaction->forceFill([
                'status' => $mappedStatus,
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

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function isDuplicateWebhook(Transaction $transaction, array $payload, string $mappedStatus): bool
    {
        $incomingTransactionId = $payload['transaction_id'] ?? null;
        $incomingPaymentType = $payload['payment_type'] ?? $transaction->payment_type;

        return $transaction->last_webhook_at !== null
            && $transaction->status->value === $mappedStatus
            && (blank($incomingTransactionId) || $transaction->midtrans_transaction_id === $incomingTransactionId)
            && (string) ($transaction->payment_type ?? '') === (string) ($incomingPaymentType ?? '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function isReachabilityCheck(Request $request, array $payload): bool
    {
        $userAgent = strtolower((string) $request->userAgent());

        return str_contains($userAgent, 'veritrans')
            && blank($payload['order_id'] ?? null)
            && blank($payload['signature_key'] ?? null)
            && blank($payload['transaction_status'] ?? null)
            && blank($payload['status_code'] ?? null)
            && blank($payload['gross_amount'] ?? null);
    }
}
