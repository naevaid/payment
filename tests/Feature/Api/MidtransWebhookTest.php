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
