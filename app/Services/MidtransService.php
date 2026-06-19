<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MidtransService
{
    public function generateGatewayOrderId(string $appId): string
    {
        $prefix = Str::upper(Str::substr(Str::slug($appId, '-'), 0, 12));

        return $prefix.'-'.Str::ulid();
    }

    /**
     * @return array<string, mixed>
     */
    public function createSnapTransaction(Transaction $transaction): array
    {
        $response = $this->client()->post(
            config('services.midtrans.snap_path'),
            [
                'transaction_details' => [
                    'order_id' => $transaction->gateway_order_id,
                    'gross_amount' => $transaction->amount,
                ],
                'customer_details' => $transaction->customer_details ?? [],
                'item_details' => $transaction->item_details ?? [],
                'custom_field1' => (string) $transaction->project?->app_id,
                'custom_field2' => $transaction->client_order_id,
                'enabled_payments' => config('services.midtrans.enabled_payments'),
                'expiry' => $this->buildExpiryPayload($transaction),
            ],
        );

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload): bool
    {
        $serverKey = (string) config('services.midtrans.server_key');

        if (
            blank($serverKey) ||
            blank($payload['order_id'] ?? null) ||
            blank($payload['status_code'] ?? null) ||
            blank($payload['gross_amount'] ?? null) ||
            blank($payload['signature_key'] ?? null)
        ) {
            return false;
        }

        $expectedSignature = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$serverKey,
        );

        return hash_equals($expectedSignature, (string) $payload['signature_key']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function mapTransactionStatus(array $payload): TransactionStatus
    {
        $status = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus = (string) ($payload['fraud_status'] ?? '');

        return match ($status) {
            'capture' => $fraudStatus === 'challenge'
                ? TransactionStatus::Pending
                : TransactionStatus::Settlement,
            'settlement' => TransactionStatus::Settlement,
            'pending' => TransactionStatus::Pending,
            'expire' => TransactionStatus::Expired,
            'cancel' => TransactionStatus::Cancelled,
            'refund', 'partial_refund', 'chargeback' => TransactionStatus::Refunded,
            default => TransactionStatus::Failed,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildExpiryPayload(Transaction $transaction): ?array
    {
        if (! $transaction->expires_at) {
            return null;
        }

        return [
            'start_time' => now()->format('Y-m-d H:i:s O'),
            'unit' => 'minute',
            'duration' => max(now()->diffInMinutes($transaction->expires_at, false), 1),
        ];
    }

    private function client(): PendingRequest
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $baseUrl = (string) config('services.midtrans.base_url');

        if (blank($serverKey) || blank($baseUrl)) {
            throw new RuntimeException('Midtrans configuration is incomplete.');
        }

        return Http::baseUrl($baseUrl)
            ->withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.midtrans.timeout', 10))
            ->withOptions([
                'verify' => (bool) config('services.midtrans.verify_ssl', true),
            ]);
    }
}
