<?php

namespace Tests\Feature\Api;

use App\Jobs\ForwardTransactionCallback;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_midtrans_webhook_updates_transaction_and_dispatches_forwarding_job(): void
    {
        Queue::fake();

        config([
            'services.midtrans.server_key' => 'midtrans-server-key',
        ]);

        $project = Project::create([
            'app_id' => 'project_a_prod',
            'project_name' => 'Project A',
            'secret_key' => 'secret-abc123',
            'default_callback_url' => 'https://project-a.test/api/payment/callback',
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'PROJECTA-01JABC1234567890',
            'client_order_id' => 'INV-PROJECTA-001',
            'amount' => 150000,
            'currency' => 'IDR',
            'status' => 'pending',
            'callback_status' => 'pending',
            'callback_url' => 'https://project-a.test/api/payment/callback',
            'customer_details' => [
                'first_name' => 'Budi',
            ],
        ]);

        $payload = [
            'transaction_id' => 'midtrans-trx-001',
            'order_id' => $transaction->gateway_order_id,
            'status_code' => '200',
            'gross_amount' => '150000',
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

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'settlement',
            'callback_status' => 'queued',
            'midtrans_transaction_id' => 'midtrans-trx-001',
            'payment_type' => 'bank_transfer',
        ]);

        $this->assertDatabaseHas('midtrans_webhook_logs', [
            'transaction_id' => $transaction->id,
            'order_id' => $transaction->gateway_order_id,
            'processing_status' => 'processed',
            'is_signature_valid' => true,
        ]);

        Queue::assertPushed(ForwardTransactionCallback::class, function (ForwardTransactionCallback $job) use ($transaction): bool {
            return $job->transactionId === $transaction->id;
        });
    }

    public function test_midtrans_notification_endpoint_is_reachable_for_url_check(): void
    {
        $response = $this->getJson('/api/v1/callback/midtrans');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Midtrans notification endpoint is reachable.');
    }

    public function test_midtrans_notification_post_ping_from_veritrans_is_accepted_for_url_check(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Veritrans',
        ])->postJson('/api/v1/callback/midtrans', []);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Midtrans notification endpoint is reachable.');

        $this->assertDatabaseHas('midtrans_webhook_logs', [
            'processing_status' => 'reachable_check',
            'notes' => 'Midtrans notification URL reachability check received.',
            'is_signature_valid' => false,
        ]);
    }

    public function test_midtrans_notification_with_unknown_order_from_veritrans_is_ignored_with_success_response(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Veritrans',
        ])->postJson('/api/v1/callback/midtrans', [
            'order_id' => 'MIDTRANS-TEST-ORDER-UNKNOWN',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '10000',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Midtrans notification endpoint is reachable.')
            ->assertJsonPath('ignored', true);

        $this->assertDatabaseHas('midtrans_webhook_logs', [
            'order_id' => 'MIDTRANS-TEST-ORDER-UNKNOWN',
            'processing_status' => 'ignored',
            'notes' => 'Midtrans notification ignored because order ID is unknown to this payment service.',
            'is_signature_valid' => false,
        ]);
    }

    public function test_duplicate_midtrans_webhook_is_accepted_without_dispatching_duplicate_callback_job(): void
    {
        Queue::fake();

        config([
            'services.midtrans.server_key' => 'midtrans-server-key',
        ]);

        $project = Project::create([
            'app_id' => 'project_dup_prod',
            'project_name' => 'Project Duplicate Webhook',
            'secret_key' => 'secret-dup-123',
            'default_callback_url' => 'https://project-dup.test/api/payment/callback',
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'PROJECTDUP-01JABC1234567890',
            'client_order_id' => 'INV-PROJECTDUP-001',
            'midtrans_transaction_id' => 'midtrans-trx-duplicate-001',
            'amount' => 175000,
            'currency' => 'IDR',
            'status' => 'settlement',
            'callback_status' => 'success',
            'callback_url' => 'https://project-dup.test/api/payment/callback',
            'payment_type' => 'bank_transfer',
            'customer_details' => [
                'first_name' => 'Sari',
            ],
            'last_webhook_at' => now()->subMinute(),
        ]);

        $payload = [
            'transaction_id' => 'midtrans-trx-duplicate-001',
            'order_id' => $transaction->gateway_order_id,
            'status_code' => '200',
            'gross_amount' => '175000',
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
        ];

        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'midtrans-server-key',
        );

        $response = $this->postJson('/api/v1/callback/midtrans', $payload);

        $response->assertOk()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'settlement',
            'callback_status' => 'success',
            'midtrans_transaction_id' => 'midtrans-trx-duplicate-001',
        ]);

        $this->assertDatabaseHas('midtrans_webhook_logs', [
            'transaction_id' => $transaction->id,
            'order_id' => $transaction->gateway_order_id,
            'processing_status' => 'duplicate',
            'is_signature_valid' => true,
        ]);

        Queue::assertNothingPushed();
    }
}
