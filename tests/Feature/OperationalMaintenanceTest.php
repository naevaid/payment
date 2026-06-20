<?php

namespace Tests\Feature;

use App\Enums\CallbackStatus;
use App\Enums\TransactionStatus;
use App\Models\CallbackForwardingLog;
use App\Models\MidtransWebhookLog;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_data_purge_removes_old_logs_after_fifteen_days(): void
    {
        Carbon::setTestNow('2026-06-20 12:00:00');

        $project = Project::create([
            'app_id' => 'APP-PURGE',
            'project_name' => 'Purge Project',
            'secret_key' => 'purge-project-secret-1234',
            'default_callback_url' => 'https://purge.naeva.id/payment/callback',
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'GW-PURGE-001',
            'client_order_id' => 'CLIENT-PURGE-001',
            'amount' => 89000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Settlement,
            'callback_status' => CallbackStatus::Success,
            'callback_url' => 'https://purge.naeva.id/payment/callback',
        ]);

        $oldWebhook = MidtransWebhookLog::create([
            'transaction_id' => $transaction->id,
            'order_id' => 'GW-OLD-WEBHOOK',
            'midtrans_transaction_id' => 'MID-OLD-WEBHOOK',
            'transaction_status' => 'settlement',
            'signature_key' => 'signature-old',
            'payload' => ['status' => 'settlement'],
            'headers' => ['x-source' => 'test'],
            'is_signature_valid' => true,
            'processing_status' => 'processed',
            'received_at' => now()->subDays(16),
            'processed_at' => now()->subDays(16),
        ]);

        $recentWebhook = MidtransWebhookLog::create([
            'transaction_id' => $transaction->id,
            'order_id' => 'GW-RECENT-WEBHOOK',
            'midtrans_transaction_id' => 'MID-RECENT-WEBHOOK',
            'transaction_status' => 'settlement',
            'signature_key' => 'signature-recent',
            'payload' => ['status' => 'settlement'],
            'headers' => ['x-source' => 'test'],
            'is_signature_valid' => true,
            'processing_status' => 'processed',
            'received_at' => now()->subDays(3),
            'processed_at' => now()->subDays(3),
        ]);

        $oldCallback = CallbackForwardingLog::create([
            'transaction_id' => $transaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://purge.naeva.id/payment/callback',
            'attempt' => 1,
            'event_type' => 'payment.status.updated',
            'payload' => ['status' => 'settlement'],
            'request_headers' => ['X-Signature' => 'old'],
            'response_status_code' => 200,
            'response_body' => 'ok',
            'success' => true,
            'dispatched_at' => now()->subDays(16),
            'responded_at' => now()->subDays(16),
        ]);

        $recentCallback = CallbackForwardingLog::create([
            'transaction_id' => $transaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://purge.naeva.id/payment/callback',
            'attempt' => 1,
            'event_type' => 'payment.status.updated',
            'payload' => ['status' => 'settlement'],
            'request_headers' => ['X-Signature' => 'recent'],
            'response_status_code' => 200,
            'response_body' => 'ok',
            'success' => true,
            'dispatched_at' => now()->subDays(2),
            'responded_at' => now()->subDays(2),
        ]);

        DB::table('failed_jobs')->insert([
            [
                'uuid' => '11111111-1111-1111-1111-111111111111',
                'connection' => 'redis',
                'queue' => 'payment-callbacks',
                'payload' => '{}',
                'exception' => 'old failure',
                'failed_at' => now()->subDays(16),
            ],
            [
                'uuid' => '22222222-2222-2222-2222-222222222222',
                'connection' => 'redis',
                'queue' => 'payment-callbacks',
                'payload' => '{}',
                'exception' => 'recent failure',
                'failed_at' => now()->subDays(2),
            ],
        ]);

        $this->artisan('payment:purge-operational-data --days=15')
            ->expectsOutput('Operational data purge selesai.')
            ->assertSuccessful();

        $this->assertModelMissing($oldWebhook);
        $this->assertModelExists($recentWebhook);
        $this->assertModelMissing($oldCallback);
        $this->assertModelExists($recentCallback);
        $this->assertDatabaseMissing('failed_jobs', [
            'uuid' => '11111111-1111-1111-1111-111111111111',
        ]);
        $this->assertDatabaseHas('failed_jobs', [
            'uuid' => '22222222-2222-2222-2222-222222222222',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'gateway_order_id' => 'GW-PURGE-001',
        ]);

        Carbon::setTestNow();
    }
}
