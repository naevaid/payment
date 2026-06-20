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
use Illuminate\Support\Facades\Http;
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

    public function test_authenticated_user_can_test_project_callback_url_from_dashboard(): void
    {
        Http::fake([
            'https://client-app.example.com/payment/callback-test' => Http::response([
                'ok' => true,
                'message' => 'callback received',
            ], 200),
        ]);

        $user = User::factory()->create();

        $project = Project::create([
            'app_id' => 'APP-CALLBACK-TEST',
            'project_name' => 'Callback Test Project',
            'secret_key' => 'callback-test-project-secret-1234',
            'default_callback_url' => 'https://client-app.example.com/payment/callback-default',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->from(route('dashboard.projects.edit', $project))
            ->post(route('dashboard.projects.test-callback', $project), [
                'app_id' => 'APP-CALLBACK-TEST',
                'callback_url' => 'https://client-app.example.com/payment/callback-test',
            ]);

        $response->assertRedirect(route('dashboard.projects.edit', $project));
        $response->assertSessionHas('status', 'Test callback berhasil. Endpoint membalas HTTP 200.');
        $response->assertSessionHas('callback_test', function (array $callbackTest): bool {
            return $callbackTest['success'] === true
                && $callbackTest['app_id'] === 'APP-CALLBACK-TEST'
                && $callbackTest['callback_url'] === 'https://client-app.example.com/payment/callback-test'
                && $callbackTest['status_code'] === 200
                && $callbackTest['event_type'] === 'payment.callback.test';
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://client-app.example.com/payment/callback-test'
                && $request->method() === 'POST'
                && $request->hasHeader('X-Payment-App-Id', 'APP-CALLBACK-TEST')
                && $request->hasHeader('X-Payment-Event', 'payment.callback.test')
                && $request->data()['test'] === true
                && $request->data()['app_id'] === 'APP-CALLBACK-TEST'
                && $request->data()['callback_url'] === 'https://client-app.example.com/payment/callback-test';
        });
    }

    public function test_authenticated_user_can_view_operational_dashboard_pages(): void
    {
        config([
            'queue.default' => 'redis',
            'payment.callback.queue' => 'payment-callbacks',
            'payment.callback.max_attempts' => 3,
            'payment.callback.backoff' => [60, 300, 900],
        ]);

        $user = User::factory()->create();

        $project = Project::create([
            'app_id' => 'APP-OPS',
            'project_name' => 'Ops Project',
            'secret_key' => 'ops-project-secret-1234',
            'default_callback_url' => 'https://ops.naeva.id/payment/callback',
            'is_active' => true,
        ]);

        $growthProject = Project::create([
            'app_id' => 'APP-GROWTH',
            'project_name' => 'Growth Project',
            'secret_key' => 'growth-project-secret-1234',
            'default_callback_url' => 'https://growth.naeva.id/payment/callback',
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

        $failedTransaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'GW-FAILED-001',
            'client_order_id' => 'CLIENT-FAILED-001',
            'amount' => 99000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Settlement,
            'callback_status' => CallbackStatus::Failed,
            'callback_url' => 'https://ops.naeva.id/payment/callback',
        ]);

        Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'GW-TX-FAILED-001',
            'client_order_id' => 'CLIENT-TX-FAILED-001',
            'amount' => 55000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Failed,
            'callback_status' => CallbackStatus::Failed,
            'callback_url' => 'https://ops.naeva.id/payment/callback',
        ]);

        Transaction::create([
            'project_id' => $growthProject->id,
            'gateway_order_id' => 'GW-GROWTH-SETTLEMENT-001',
            'client_order_id' => 'CLIENT-GROWTH-SETTLEMENT-001',
            'amount' => 350000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Settlement,
            'callback_status' => CallbackStatus::Success,
            'callback_url' => 'https://growth.naeva.id/payment/callback',
        ]);

        Transaction::create([
            'project_id' => $growthProject->id,
            'gateway_order_id' => 'GW-GROWTH-SETTLEMENT-002',
            'client_order_id' => 'CLIENT-GROWTH-SETTLEMENT-002',
            'amount' => 175000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Settlement,
            'callback_status' => CallbackStatus::Success,
            'callback_url' => 'https://growth.naeva.id/payment/callback',
        ]);

        $failedCallbackLog = CallbackForwardingLog::create([
            'transaction_id' => $failedTransaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://ops.naeva.id/payment/callback',
            'attempt' => 2,
            'event_type' => 'payment.status.updated',
            'payload' => ['status' => 'settlement'],
            'request_headers' => ['X-Signature' => 'retry-signature'],
            'response_status_code' => 500,
            'response_body' => 'server error',
            'success' => false,
            'error_message' => 'HTTP 500',
            'next_retry_at' => now()->addMinute(),
            'dispatched_at' => now()->subMinute(),
        ]);

        $skippedTransaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'GW-SKIPPED-001',
            'client_order_id' => 'CLIENT-SKIPPED-001',
            'amount' => 45000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Pending,
            'callback_status' => CallbackStatus::Skipped,
            'callback_url' => null,
        ]);

        CallbackForwardingLog::create([
            'transaction_id' => $skippedTransaction->id,
            'project_id' => $project->id,
            'callback_url' => '',
            'attempt' => 1,
            'event_type' => 'payment.status.updated',
            'payload' => ['status' => 'pending'],
            'request_headers' => ['X-Signature' => 'skip-signature'],
            'response_status_code' => null,
            'response_body' => null,
            'success' => false,
            'error_message' => 'Callback URL is not configured.',
            'dispatched_at' => now()->subSeconds(30),
            'responded_at' => now()->subSeconds(30),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Projects')
            ->assertSee('Webhooks')
            ->assertSee('Callbacks')
            ->assertDontSee('Rencana PRD')
            ->assertDontSee('Projects / Tenants')
            ->assertDontSee('List global, status, dan detail transaksi')
            ->assertSee('Owner-Level Reporting')
            ->assertSee('Rp 624.000')
            ->assertSee('Rp 170.000')
            ->assertSee('Rp 55.000')
            ->assertSee('Ops Project')
            ->assertSee('Growth Project')
            ->assertSee('Rp 525.000')
            ->assertSee('APP-GROWTH')
            ->assertSee('Queue & Callback Health Ops', false)
            ->assertSee('payment-callbacks')
            ->assertSee('GW-FAILED-001')
            ->assertSee('GW-SKIPPED-001')
            ->assertSee('Retry Manual Callback');

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

        $this->actingAs($user)
            ->get(route('dashboard.callback-logs.show', $failedCallbackLog))
            ->assertOk()
            ->assertSee('HTTP 500');
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

    public function test_authenticated_user_can_filter_transactions_by_app_id_and_export_csv(): void
    {
        $user = User::factory()->create();

        $opsProject = Project::create([
            'app_id' => 'APP-OPS-ALPHA',
            'project_name' => 'Ops Alpha',
            'secret_key' => 'ops-alpha-secret-1234',
            'default_callback_url' => 'https://ops-alpha.naeva.id/payment/callback',
            'is_active' => true,
        ]);

        $billingProject = Project::create([
            'app_id' => 'APP-BILLING-BETA',
            'project_name' => 'Billing Beta',
            'secret_key' => 'billing-beta-secret-1234',
            'default_callback_url' => 'https://billing-beta.naeva.id/payment/callback',
            'is_active' => true,
        ]);

        Transaction::create([
            'project_id' => $opsProject->id,
            'gateway_order_id' => 'GW-OPS-001',
            'client_order_id' => 'CLIENT-OPS-001',
            'amount' => 110000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Pending,
            'callback_status' => CallbackStatus::Queued,
            'callback_url' => 'https://ops-alpha.naeva.id/payment/callback',
        ]);

        Transaction::create([
            'project_id' => $billingProject->id,
            'gateway_order_id' => 'GW-BILLING-001',
            'client_order_id' => 'CLIENT-BILLING-001',
            'amount' => 215000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Settlement,
            'callback_status' => CallbackStatus::Success,
            'callback_url' => 'https://billing-beta.naeva.id/payment/callback',
        ]);

        $filters = [
            'app_id' => 'APP-OPS',
        ];

        $response = $this->actingAs($user)->get(route('dashboard.transactions.index', $filters));

        $response->assertOk()
            ->assertSee('GW-OPS-001')
            ->assertSee('APP-OPS-ALPHA')
            ->assertDontSee('GW-BILLING-001')
            ->assertDontSee('CLIENT-BILLING-001');

        $exportResponse = $this->actingAs($user)->get(route('dashboard.transactions.export', $filters));

        $exportResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $exportResponse->headers->get('content-type'));
        $this->assertStringContainsString('project_app_id', $exportResponse->streamedContent());
        $this->assertStringContainsString('APP-OPS-ALPHA', $exportResponse->streamedContent());
        $this->assertStringNotContainsString('APP-BILLING-BETA', $exportResponse->streamedContent());
    }

    public function test_authenticated_user_can_filter_webhook_and_callback_logs_by_app_id_and_export_csv(): void
    {
        $user = User::factory()->create();

        $opsProject = Project::create([
            'app_id' => 'APP-OPS-GAMMA',
            'project_name' => 'Ops Gamma',
            'secret_key' => 'ops-gamma-secret-1234',
            'default_callback_url' => 'https://ops-gamma.naeva.id/payment/callback',
            'is_active' => true,
        ]);

        $billingProject = Project::create([
            'app_id' => 'APP-BILLING-DELTA',
            'project_name' => 'Billing Delta',
            'secret_key' => 'billing-delta-secret-1234',
            'default_callback_url' => 'https://billing-delta.naeva.id/payment/callback',
            'is_active' => true,
        ]);

        $opsTransaction = Transaction::create([
            'project_id' => $opsProject->id,
            'gateway_order_id' => 'GW-WH-OPS-001',
            'client_order_id' => 'CLIENT-WH-OPS-001',
            'amount' => 135000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Pending,
            'callback_status' => CallbackStatus::Queued,
            'callback_url' => 'https://ops-gamma.naeva.id/payment/callback',
        ]);

        $billingTransaction = Transaction::create([
            'project_id' => $billingProject->id,
            'gateway_order_id' => 'GW-WH-BILLING-001',
            'client_order_id' => 'CLIENT-WH-BILLING-001',
            'amount' => 246000,
            'currency' => 'IDR',
            'status' => TransactionStatus::Settlement,
            'callback_status' => CallbackStatus::Success,
            'callback_url' => 'https://billing-delta.naeva.id/payment/callback',
        ]);

        MidtransWebhookLog::create([
            'transaction_id' => $opsTransaction->id,
            'order_id' => 'GW-WH-OPS-001',
            'midtrans_transaction_id' => 'MID-WH-OPS-001',
            'transaction_status' => 'pending',
            'signature_key' => 'signature-ops',
            'payload' => ['status' => 'pending'],
            'headers' => ['x-source' => 'test'],
            'is_signature_valid' => true,
            'processing_status' => 'received',
            'received_at' => now(),
        ]);

        MidtransWebhookLog::create([
            'transaction_id' => $billingTransaction->id,
            'order_id' => 'GW-WH-BILLING-001',
            'midtrans_transaction_id' => 'MID-WH-BILLING-001',
            'transaction_status' => 'settlement',
            'signature_key' => 'signature-billing',
            'payload' => ['status' => 'settlement'],
            'headers' => ['x-source' => 'test'],
            'is_signature_valid' => true,
            'processing_status' => 'processed',
            'received_at' => now(),
        ]);

        CallbackForwardingLog::create([
            'transaction_id' => $opsTransaction->id,
            'project_id' => $opsProject->id,
            'callback_url' => 'https://ops-gamma.naeva.id/payment/callback',
            'attempt' => 1,
            'event_type' => 'payment.status.updated',
            'payload' => ['status' => 'pending'],
            'request_headers' => ['X-Signature' => 'ops'],
            'response_status_code' => 500,
            'response_body' => 'error',
            'success' => false,
            'error_message' => 'HTTP 500',
            'dispatched_at' => now(),
        ]);

        CallbackForwardingLog::create([
            'transaction_id' => $billingTransaction->id,
            'project_id' => $billingProject->id,
            'callback_url' => 'https://billing-delta.naeva.id/payment/callback',
            'attempt' => 1,
            'event_type' => 'payment.status.updated',
            'payload' => ['status' => 'settlement'],
            'request_headers' => ['X-Signature' => 'billing'],
            'response_status_code' => 200,
            'response_body' => 'ok',
            'success' => true,
            'dispatched_at' => now(),
            'responded_at' => now(),
        ]);

        $filters = [
            'app_id' => 'APP-OPS',
        ];

        $webhookResponse = $this->actingAs($user)->get(route('dashboard.webhook-logs.index', $filters));

        $webhookResponse->assertOk()
            ->assertSee('GW-WH-OPS-001')
            ->assertSee('APP-OPS-GAMMA')
            ->assertDontSee('GW-WH-BILLING-001')
            ->assertDontSee('MID-WH-BILLING-001');

        $webhookExport = $this->actingAs($user)->get(route('dashboard.webhook-logs.export', $filters));

        $webhookExport->assertOk();
        $this->assertStringContainsString('text/csv', (string) $webhookExport->headers->get('content-type'));
        $this->assertStringContainsString('project_app_id', $webhookExport->streamedContent());
        $this->assertStringContainsString('APP-OPS-GAMMA', $webhookExport->streamedContent());
        $this->assertStringNotContainsString('APP-BILLING-DELTA', $webhookExport->streamedContent());

        $callbackResponse = $this->actingAs($user)->get(route('dashboard.callback-logs.index', $filters));

        $callbackResponse->assertOk()
            ->assertSee('Ops Gamma')
            ->assertSee('APP-OPS-GAMMA')
            ->assertSee('https://ops-gamma.naeva.id/payment/callback')
            ->assertDontSee('https://billing-delta.naeva.id/payment/callback');

        $callbackExport = $this->actingAs($user)->get(route('dashboard.callback-logs.export', $filters));

        $callbackExport->assertOk();
        $this->assertStringContainsString('text/csv', (string) $callbackExport->headers->get('content-type'));
        $this->assertStringContainsString('project_app_id', $callbackExport->streamedContent());
        $this->assertStringContainsString('APP-OPS-GAMMA', $callbackExport->streamedContent());
        $this->assertStringNotContainsString('APP-BILLING-DELTA', $callbackExport->streamedContent());
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
