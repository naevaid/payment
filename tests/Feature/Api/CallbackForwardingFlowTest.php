<?php

namespace Tests\Feature\Api;

use App\Models\CallbackForwardingLog;
use App\Models\Project;
use App\Models\Transaction;
use App\Services\ProjectCallbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CallbackForwardingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_midtrans_webhook_can_complete_end_to_end_callback_forwarding_when_queue_runs_sync(): void
    {
        config([
            'queue.default' => 'sync',
            'services.midtrans.server_key' => 'midtrans-server-key',
        ]);

        Http::fake([
            'https://project-end-to-end.test/api/payment/callback' => Http::response([
                'status' => 'ok',
            ], 200),
        ]);

        $project = Project::create([
            'app_id' => 'project_end_to_end_prod',
            'project_name' => 'Project End To End',
            'secret_key' => 'secret-end-to-end-123',
            'default_callback_url' => 'https://project-end-to-end.test/api/payment/callback',
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'PROJECTE2E-01JABC1234567890',
            'client_order_id' => 'INV-PROJECTE2E-001',
            'amount' => 210000,
            'currency' => 'IDR',
            'status' => 'pending',
            'callback_status' => 'pending',
            'callback_url' => 'https://project-end-to-end.test/api/payment/callback',
            'customer_details' => [
                'first_name' => 'End To End',
            ],
            'metadata' => [
                'invoice_id' => 3001,
            ],
        ]);

        $payload = [
            'transaction_id' => 'midtrans-trx-e2e-001',
            'order_id' => $transaction->gateway_order_id,
            'status_code' => '200',
            'gross_amount' => '210000',
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
        ];

        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'midtrans-server-key',
        );

        $response = $this->postJson('/api/v1/callback/midtrans', $payload);

        $response->assertOk()
            ->assertJsonPath('status', 'accepted');

        $transaction->refresh();

        $this->assertSame('settlement', $transaction->status->value);
        $this->assertSame('success', $transaction->callback_status->value);
        $this->assertSame('bank_transfer', $transaction->payment_type);

        $callbackLog = CallbackForwardingLog::query()
            ->where('transaction_id', $transaction->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($callbackLog);
        $this->assertTrue((bool) $callbackLog->success);
        $this->assertSame(200, $callbackLog->response_status_code);
        $this->assertSame('payment.status.updated', $callbackLog->event_type);
        $this->assertSame('project_end_to_end_prod', $callbackLog->request_headers['X-Payment-App-Id'] ?? null);
        $this->assertSame('1', $callbackLog->request_headers['X-Payment-Attempt'] ?? null);
        $this->assertNotEmpty($callbackLog->request_headers['X-Payment-Timestamp'] ?? null);
        $this->assertNotEmpty($callbackLog->request_headers['X-Payment-Delivery-Id'] ?? null);
        $this->assertNotEmpty($callbackLog->request_headers['X-Payment-Signature'] ?? null);
        $this->assertSame('settlement', $callbackLog->payload['transaction_status'] ?? null);
        $this->assertSame(3001, $callbackLog->payload['metadata']['invoice_id'] ?? null);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://project-end-to-end.test/api/payment/callback'
                && $request->hasHeader('X-Payment-App-Id', 'project_end_to_end_prod')
                && $request->hasHeader('X-Payment-Attempt', '1')
                && $request->hasHeader('X-Payment-Event', 'payment.status.updated')
                && filled($request->header('X-Payment-Delivery-Id')[0] ?? null)
                && $request['transaction_status'] === 'settlement';
        });
    }

    public function test_callback_forwarding_failure_records_retry_metadata_for_project_endpoint(): void
    {
        config([
            'payment.callback.backoff' => [60, 300, 900],
        ]);

        Http::fake([
            'https://project-retry.test/api/payment/callback' => Http::response([
                'message' => 'temporary failure',
            ], 500),
        ]);

        $project = Project::create([
            'app_id' => 'project_retry_prod',
            'project_name' => 'Project Retry',
            'secret_key' => 'secret-retry-123',
            'default_callback_url' => 'https://project-retry.test/api/payment/callback',
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'PROJECTRETRY-01JABC1234567890',
            'client_order_id' => 'INV-PROJECTRETRY-001',
            'midtrans_transaction_id' => 'midtrans-trx-retry-001',
            'amount' => 99000,
            'currency' => 'IDR',
            'status' => 'settlement',
            'callback_status' => 'queued',
            'callback_url' => 'https://project-retry.test/api/payment/callback',
            'payment_type' => 'bank_transfer',
            'metadata' => [
                'invoice_id' => 4001,
            ],
            'last_webhook_at' => now(),
        ]);

        try {
            app(ProjectCallbackService::class)->forward($transaction->fresh('project'), 1);
            $this->fail('Expected callback forwarding to throw when project endpoint returns 500.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Callback forwarding failed with status 500.', $exception->getMessage());
        }

        $transaction->refresh();

        $this->assertSame('queued', $transaction->callback_status->value);

        $callbackLog = CallbackForwardingLog::query()
            ->where('transaction_id', $transaction->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($callbackLog);
        $this->assertFalse((bool) $callbackLog->success);
        $this->assertSame(500, $callbackLog->response_status_code);
        $this->assertSame('HTTP 500', $callbackLog->error_message);
        $this->assertSame('1', $callbackLog->request_headers['X-Payment-Attempt'] ?? null);
        $this->assertNotEmpty($callbackLog->request_headers['X-Payment-Delivery-Id'] ?? null);
        $this->assertNotNull($callbackLog->next_retry_at);
        $this->assertTrue($callbackLog->next_retry_at->greaterThan($callbackLog->dispatched_at));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://project-retry.test/api/payment/callback'
                && $request->hasHeader('X-Payment-Attempt', '1')
                && filled($request->header('X-Payment-Delivery-Id')[0] ?? null)
                && $request['gateway_order_id'] === 'PROJECTRETRY-01JABC1234567890';
        });
    }
}
