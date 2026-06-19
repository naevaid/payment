<?php

namespace App\Services;

use App\Enums\CallbackStatus;
use App\Models\CallbackForwardingLog;
use App\Models\Transaction;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ProjectCallbackService
{
    public function __construct(
        private readonly int $timeoutSeconds = 10,
    ) {
        $this->timeoutSeconds = (int) config('payment.callback.timeout_seconds', 10);
    }

    public function forward(Transaction $transaction, int $attempt): CallbackForwardingLog
    {
        $transaction->loadMissing('project');

        $callbackUrl = $transaction->callback_url ?: $transaction->project?->default_callback_url;
        $payload = $this->buildPayload($transaction);
        $headers = $this->buildHeaders($transaction, $payload);
        $dispatchedAt = now();

        if (blank($callbackUrl)) {
            $transaction->forceFill([
                'callback_status' => CallbackStatus::Skipped,
            ])->save();

            return CallbackForwardingLog::create([
                'transaction_id' => $transaction->id,
                'project_id' => $transaction->project_id,
                'callback_url' => '',
                'attempt' => $attempt,
                'event_type' => 'payment.status.updated',
                'payload' => $payload,
                'request_headers' => $headers,
                'success' => false,
                'error_message' => 'Callback URL is not configured.',
                'dispatched_at' => $dispatchedAt,
                'responded_at' => now(),
            ]);
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($this->timeoutSeconds)
                ->withHeaders($headers)
                ->post($callbackUrl, $payload);

            $log = $this->storeResponseLog(
                $transaction,
                $callbackUrl,
                $attempt,
                $payload,
                $headers,
                $response,
                $dispatchedAt,
            );

            if (! $response->successful()) {
                $transaction->forceFill([
                    'callback_status' => CallbackStatus::Queued,
                ])->save();

                throw new RuntimeException('Callback forwarding failed with status '.$response->status().'.');
            }

            $transaction->forceFill([
                'callback_status' => CallbackStatus::Success,
            ])->save();

            return $log;
        } catch (Throwable $exception) {
            if (isset($response)) {
                throw $exception;
            }

            CallbackForwardingLog::create([
                'transaction_id' => $transaction->id,
                'project_id' => $transaction->project_id,
                'callback_url' => $callbackUrl,
                'attempt' => $attempt,
                'event_type' => 'payment.status.updated',
                'payload' => $payload,
                'request_headers' => $headers,
                'success' => false,
                'error_message' => $exception->getMessage(),
                'next_retry_at' => $this->resolveNextRetryAt($attempt),
                'dispatched_at' => $dispatchedAt,
                'responded_at' => now(),
            ]);

            $transaction->forceFill([
                'callback_status' => CallbackStatus::Queued,
            ])->save();

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(Transaction $transaction): array
    {
        return [
            'order_id' => $transaction->client_order_id,
            'gateway_order_id' => $transaction->gateway_order_id,
            'transaction_status' => $transaction->status->value,
            'payment_type' => $transaction->payment_type,
            'gross_amount' => $transaction->amount,
            'transaction_time' => ($transaction->last_webhook_at ?? $transaction->updated_at)?->toDateTimeString(),
            'metadata' => $transaction->metadata ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public function buildHeaders(Transaction $transaction, array $payload): array
    {
        $project = $transaction->project;
        $signature = hash_hmac(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            (string) $project?->secret_key,
        );

        return [
            'User-Agent' => (string) config('payment.callback.user_agent'),
            'X-Payment-App-Id' => (string) $project?->app_id,
            'X-Payment-Event' => 'payment.status.updated',
            'X-Payment-Signature' => $signature,
        ];
    }

    private function storeResponseLog(
        Transaction $transaction,
        string $callbackUrl,
        int $attempt,
        array $payload,
        array $headers,
        Response $response,
        Carbon $dispatchedAt,
    ): CallbackForwardingLog {
        return CallbackForwardingLog::create([
            'transaction_id' => $transaction->id,
            'project_id' => $transaction->project_id,
            'callback_url' => $callbackUrl,
            'attempt' => $attempt,
            'event_type' => 'payment.status.updated',
            'payload' => $payload,
            'request_headers' => $headers,
            'response_status_code' => $response->status(),
            'response_body' => $response->body(),
            'success' => $response->successful(),
            'error_message' => $response->successful() ? null : 'HTTP '.$response->status(),
            'next_retry_at' => $response->successful() ? null : $this->resolveNextRetryAt($attempt),
            'dispatched_at' => $dispatchedAt,
            'responded_at' => now(),
        ]);
    }

    private function resolveNextRetryAt(int $attempt): ?Carbon
    {
        $backoff = config('payment.callback.backoff', [60, 300, 900]);
        $delay = $backoff[$attempt - 1] ?? null;

        return $delay ? now()->addSeconds((int) $delay) : null;
    }
}
