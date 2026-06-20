<?php

namespace Tests\Feature;

use App\Enums\CallbackStatus;
use App\Enums\TransactionStatus;
use App\Jobs\ForwardTransactionCallback;
use App\Models\CallbackForwardingLog;
use App\Models\MidtransWebhookLog;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DashboardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manage_projects(): void
    {
        $user = User::factory()->create();

        $createResponse = $this->actingAs($user)->post(route('dashboard.projects.store'), [
            'project_name' => 'Billing Internal',
            'app_id' => 'APP-BILLING',
            'secret_key' => 'secret-key-billing-1234',
            'default_callback_url' => 'https://billing.naeva.id/payment/callback',
            'is_active' => '1',
            'metadata_json' => '{"team":"finance"}',
        ]);

        $project = Project::firstOrFail();

        $createResponse->assertRedirect(route('dashboard.projects.show', $project));
        $this->assertSame('Billing Internal', $project->project_name);
        $this->assertTrue($project->is_active);

        $updateResponse = $this->actingAs($user)->put(route('dashboard.projects.update', $project), [
            'project_name' => 'Billing Internal Updated',
            'app_id' => 'APP-BILLING',
            'default_callback_url' => 'https://billing.naeva.id/payment/callback-v2',
            'metadata_json' => '{"team":"finance","version":2}',
        ]);

        $updateResponse->assertRedirect(route('dashboard.projects.show', $project));

        $project->refresh();

        $this->assertSame('Billing Internal Updated', $project->project_name);
        $this->assertSame('https://billing.naeva.id/payment/callback-v2', $project->default_callback_url);
        $this->assertSame(['team' => 'finance', 'version' => 2], $project->metadata);
    }

    public function test_authenticated_user_can_regenerate_project_credentials(): void
    {
        $user = User::factory()->create();

        $project = Project::create([
            'app_id' => 'APP-STATIC',
            'project_name' => 'Credential Project',
            'secret_key' => 'credential-project-secret-1234',
            'default_callback_url' => 'https://credential.naeva.id/payment/callback',
            'is_active' => true,
        ]);

        $oldAppId = $project->app_id;
        $oldSecretKey = $project->secret_key;

        $appIdResponse = $this->actingAs($user)->post(route('dashboard.projects.regenerate-app-id', $project));

        $appIdResponse->assertRedirect(route('dashboard.projects.show', $project));
        $appIdResponse->assertSessionHas('generated_credentials.app_id');

        $project->refresh();

        $this->assertNotSame($oldAppId, $project->app_id);
        $appIdResponse->assertSessionHas('generated_credentials.app_id', $project->app_id);

        $secretKeyResponse = $this->actingAs($user)->post(route('dashboard.projects.regenerate-secret-key', $project));

        $secretKeyResponse->assertRedirect(route('dashboard.projects.show', $project));
        $secretKeyResponse->assertSessionHas('generated_credentials.secret_key');

        $project->refresh();

        $this->assertNotSame($oldSecretKey, $project->secret_key);
        $secretKeyResponse->assertSessionHas('generated_credentials.secret_key', $project->secret_key);

        $this->actingAs($user)
            ->get(route('dashboard.projects.show', $project))
            ->assertOk()
            ->assertSee('aria-label="Lihat secret key"', false)
            ->assertSee('aria-label="Salin secret key"', false);
    }

    public function test_authenticated_user_can_view_operational_dashboard_pages(): void
    {
        $user = User::factory()->create();

        $project = Project::create([
            'app_id' => 'APP-OPS',
            'project_name' => 'Ops Project',
            'secret_key' => 'ops-project-secret-1234',
            'default_callback_url' => 'https://ops.naeva.id/payment/callback',
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'GW-ORDER-001',
            'client_order_id' => 'CLIENT-001',
            'midtrans_transaction_id' => 'MID-001',
            'amount' => 125000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Pending,
            'callback_status' => CallbackStatus::Queued,
            'callback_url' => 'https://ops.naeva.id/payment/callback',
            'payment_type' => 'bank_transfer',
            'customer_details' => ['first_name' => 'Amin'],
            'item_details' => [['name' => 'Invoice', 'price' => 125000]],
            'metadata' => ['source' => 'test'],
            'midtrans_payload' => ['token' => 'snap-token'],
        ]);

        $webhookLog = MidtransWebhookLog::create([
            'transaction_id' => $transaction->id,
            'order_id' => $transaction->gateway_order_id,
            'midtrans_transaction_id' => $transaction->midtrans_transaction_id,
            'transaction_status' => 'pending',
            'signature_key' => 'signature-key',
            'payload' => ['transaction_status' => 'pending'],
            'headers' => ['x-callback-token' => 'abc'],
            'is_signature_valid' => true,
            'processing_status' => 'received',
            'received_at' => now(),
        ]);

        $callbackLog = CallbackForwardingLog::create([
            'transaction_id' => $transaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://ops.naeva.id/payment/callback',
            'attempt' => 1,
            'event_type' => 'payment.status.updated',
            'payload' => ['status' => 'pending'],
            'request_headers' => ['X-Signature' => 'abc'],
            'response_status_code' => 200,
            'response_body' => 'ok',
            'success' => true,
            'dispatched_at' => now(),
            'responded_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.transactions.index'))
            ->assertOk()
            ->assertSee('GW-ORDER-001')
            ->assertSee('Ops Project');

        $this->actingAs($user)
            ->get(route('dashboard.transactions.show', $transaction))
            ->assertOk()
            ->assertSee('CLIENT-001')
            ->assertSee('bank_transfer');

        $this->actingAs($user)
            ->get(route('dashboard.webhook-logs.index'))
            ->assertOk()
            ->assertSee('GW-ORDER-001');

        $this->actingAs($user)
            ->get(route('dashboard.webhook-logs.show', $webhookLog))
            ->assertOk()
            ->assertSee('signature-key');

        $this->actingAs($user)
            ->get(route('dashboard.callback-logs.index'))
            ->assertOk()
            ->assertSee('https://ops.naeva.id/payment/callback');

        $this->actingAs($user)
            ->get(route('dashboard.callback-logs.show', $callbackLog))
            ->assertOk()
            ->assertSee('payment.status.updated')
            ->assertSee('ok');
    }

    public function test_authenticated_user_can_filter_dashboard_logs_by_date_and_export_csv(): void
    {
        $user = User::factory()->create();

        $project = Project::create([
            'app_id' => 'APP-FILTER',
            'project_name' => 'Filter Project',
            'secret_key' => 'filter-project-secret-1234',
            'default_callback_url' => 'https://filter.naeva.id/payment/callback',
            'is_active' => true,
        ]);

        $olderTransaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'GW-OLD-001',
            'client_order_id' => 'CLIENT-OLD-001',
            'amount' => 150000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Pending,
            'callback_status' => CallbackStatus::Queued,
            'callback_url' => 'https://filter.naeva.id/payment/callback/old',
        ]);

        $olderTransaction->forceFill([
            'created_at' => Carbon::parse('2026-06-10 09:00:00'),
            'updated_at' => Carbon::parse('2026-06-10 09:00:00'),
        ])->saveQuietly();

        $recentTransaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'GW-RECENT-001',
            'client_order_id' => 'CLIENT-RECENT-001',
            'amount' => 275000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Settlement,
            'callback_status' => CallbackStatus::Success,
            'callback_url' => 'https://filter.naeva.id/payment/callback/recent',
            'payment_type' => 'qris',
        ]);

        $recentTransaction->forceFill([
            'created_at' => Carbon::parse('2026-06-20 14:30:00'),
            'updated_at' => Carbon::parse('2026-06-20 14:30:00'),
        ])->saveQuietly();

        MidtransWebhookLog::create([
            'transaction_id' => $olderTransaction->id,
            'order_id' => $olderTransaction->gateway_order_id,
            'midtrans_transaction_id' => 'MID-OLD-001',
            'transaction_status' => 'pending',
            'signature_key' => 'signature-old',
            'payload' => ['status' => 'pending'],
            'headers' => ['x-source' => 'test'],
            'is_signature_valid' => true,
            'processing_status' => 'received',
            'received_at' => Carbon::parse('2026-06-10 09:15:00'),
        ]);

        MidtransWebhookLog::create([
            'transaction_id' => $recentTransaction->id,
            'order_id' => $recentTransaction->gateway_order_id,
            'midtrans_transaction_id' => 'MID-RECENT-001',
            'transaction_status' => 'settlement',
            'signature_key' => 'signature-recent',
            'payload' => ['status' => 'settlement'],
            'headers' => ['x-source' => 'test'],
            'is_signature_valid' => true,
            'processing_status' => 'processed',
            'received_at' => Carbon::parse('2026-06-20 14:35:00'),
            'processed_at' => Carbon::parse('2026-06-20 14:36:00'),
        ]);

        CallbackForwardingLog::create([
            'transaction_id' => $olderTransaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://filter.naeva.id/payment/callback/old',
            'attempt' => 1,
            'event_type' => 'payment.status.updated',
            'payload' => ['status' => 'pending'],
            'request_headers' => ['X-Signature' => 'old'],
            'response_status_code' => 500,
            'response_body' => 'error',
            'success' => false,
            'error_message' => 'HTTP 500',
            'dispatched_at' => Carbon::parse('2026-06-10 09:20:00'),
        ]);

        CallbackForwardingLog::create([
            'transaction_id' => $recentTransaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://filter.naeva.id/payment/callback/recent',
            'attempt' => 1,
            'event_type' => 'payment.status.updated',
            'payload' => ['status' => 'settlement'],
            'request_headers' => ['X-Signature' => 'recent'],
            'response_status_code' => 200,
            'response_body' => 'ok',
            'success' => true,
            'dispatched_at' => Carbon::parse('2026-06-20 14:40:00'),
            'responded_at' => Carbon::parse('2026-06-20 14:41:00'),
        ]);

        $dateFilters = [
            'date_from' => '2026-06-19',
            'date_to' => '2026-06-20',
        ];

        $this->actingAs($user)
            ->get(route('dashboard.transactions.index', $dateFilters))
            ->assertOk()
            ->assertSee('GW-RECENT-001')
            ->assertDontSee('GW-OLD-001');

        $this->actingAs($user)
            ->get(route('dashboard.webhook-logs.index', $dateFilters))
            ->assertOk()
            ->assertSee('GW-RECENT-001')
            ->assertDontSee('GW-OLD-001');

        $this->actingAs($user)
            ->get(route('dashboard.callback-logs.index', $dateFilters))
            ->assertOk()
            ->assertSee('https://filter.naeva.id/payment/callback/recent')
            ->assertDontSee('https://filter.naeva.id/payment/callback/old');

        $transactionsExport = $this->actingAs($user)->get(route('dashboard.transactions.export', $dateFilters));
        $transactionsExport->assertOk();
        $this->assertStringContainsString('text/csv', (string) $transactionsExport->headers->get('content-type'));
        $this->assertStringContainsString('GW-RECENT-001', $transactionsExport->streamedContent());
        $this->assertStringNotContainsString('GW-OLD-001', $transactionsExport->streamedContent());

        $webhookExport = $this->actingAs($user)->get(route('dashboard.webhook-logs.export', $dateFilters));
        $webhookExport->assertOk();
        $this->assertStringContainsString('text/csv', (string) $webhookExport->headers->get('content-type'));
        $this->assertStringContainsString('MID-RECENT-001', $webhookExport->streamedContent());
        $this->assertStringNotContainsString('MID-OLD-001', $webhookExport->streamedContent());

        $callbackExport = $this->actingAs($user)->get(route('dashboard.callback-logs.export', $dateFilters));
        $callbackExport->assertOk();
        $this->assertStringContainsString('text/csv', (string) $callbackExport->headers->get('content-type'));
        $this->assertStringContainsString('https://filter.naeva.id/payment/callback/recent', $callbackExport->streamedContent());
        $this->assertStringNotContainsString('https://filter.naeva.id/payment/callback/old', $callbackExport->streamedContent());
    }

    public function test_authenticated_user_can_retry_failed_callback_from_dashboard(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $project = Project::create([
            'app_id' => 'APP-RETRY',
            'project_name' => 'Retry Project',
            'secret_key' => 'retry-project-secret-1234',
            'default_callback_url' => 'https://retry.naeva.id/payment/callback',
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'GW-RETRY-001',
            'client_order_id' => 'CLIENT-RETRY-001',
            'amount' => 99000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Settlement,
            'callback_status' => CallbackStatus::Failed,
            'callback_url' => 'https://retry.naeva.id/payment/callback',
        ]);

        $callbackLog = CallbackForwardingLog::create([
            'transaction_id' => $transaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://retry.naeva.id/payment/callback',
            'attempt' => 3,
            'event_type' => 'payment.status.updated',
            'payload' => ['status' => 'settlement'],
            'request_headers' => ['X-Signature' => 'retry'],
            'response_status_code' => 500,
            'response_body' => 'server error',
            'success' => false,
            'error_message' => 'HTTP 500',
            'dispatched_at' => now(),
            'responded_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.callback-logs.retry', $callbackLog));

        $response->assertRedirect(route('dashboard.callback-logs.show', $callbackLog));
        $response->assertSessionHas('status', 'Retry manual callback sudah dijadwalkan untuk transaksi ini.');

        $transaction->refresh();

        $this->assertSame(CallbackStatus::Queued, $transaction->callback_status);
        Queue::assertPushed(ForwardTransactionCallback::class, fn (ForwardTransactionCallback $job) => $job->transactionId === $transaction->id);
    }
}
