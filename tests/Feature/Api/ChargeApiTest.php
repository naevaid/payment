<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\CallbackForwardingLog;
use App\Models\MidtransWebhookLog;
use App\Models\Transaction;
use App\Support\ProjectRequestSignature;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChargeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_can_create_midtrans_charge_transaction(): void
    {
        config([
            'services.midtrans.server_key' => 'midtrans-server-key',
            'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
            'services.midtrans.snap_path' => '/snap/v1/transactions',
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-xyz',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-xyz',
            ], 201),
        ]);

        $project = Project::create([
            'app_id' => 'project_a_prod',
            'project_name' => 'Project A',
            'secret_key' => 'secret-abc123',
            'default_callback_url' => 'https://project-a.test/api/payment/callback',
            'is_active' => true,
        ]);

        $payload = [
            'order_id' => 'INV-PROJECTA-001',
            'gross_amount' => 150000,
            'customer_details' => [
                'first_name' => 'Budi',
                'email' => 'budi@example.com',
            ],
            'item_details' => [
                [
                    'id' => 'SKU-1',
                    'price' => 150000,
                    'quantity' => 1,
                    'name' => 'Invoice Payment',
                ],
            ],
        ];
        $timestamp = (string) now()->timestamp;

        $response = $this->postJson(
            '/api/v1/charge',
            $payload,
            $this->signedHeaders($project, $payload, $timestamp),
        );

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('project.app_id', 'project_a_prod')
            ->assertJsonPath('order_id', 'INV-PROJECTA-001')
            ->assertJsonPath('token', 'snap-token-xyz')
            ->assertJsonPath('idempotency.reused', false);

        $this->assertDatabaseHas('transactions', [
            'project_id' => $project->id,
            'client_order_id' => 'INV-PROJECTA-001',
            'status' => 'pending',
            'callback_status' => 'pending',
            'snap_token' => 'snap-token-xyz',
        ]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
                && $request['transaction_details']['gross_amount'] === 150000
                && $request['customer_details']['first_name'] === 'Budi';
        });
    }

    public function test_duplicate_charge_request_with_same_payload_reuses_existing_transaction(): void
    {
        config([
            'services.midtrans.server_key' => 'midtrans-server-key',
            'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
            'services.midtrans.snap_path' => '/snap/v1/transactions',
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-reuse',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-reuse',
            ], 201),
        ]);

        $project = Project::create([
            'app_id' => 'project_idempotent_prod',
            'project_name' => 'Project Idempotent',
            'secret_key' => 'secret-idempotent-123',
            'default_callback_url' => 'https://project-idempotent.test/api/payment/callback',
            'is_active' => true,
        ]);

        $payload = [
            'order_id' => 'INV-IDEMPOTENT-001',
            'gross_amount' => 99000,
            'customer_details' => [
                'first_name' => 'Dina',
                'email' => 'dina@example.com',
            ],
            'item_details' => [
                [
                    'id' => 'SKU-IDEMPOTENT-1',
                    'price' => 99000,
                    'quantity' => 1,
                    'name' => 'Monthly Billing',
                ],
            ],
            'metadata' => [
                'invoice_id' => 2001,
            ],
        ];

        $timestamp = (string) now()->timestamp;
        $headers = $this->signedHeaders($project, $payload, $timestamp);

        $firstResponse = $this->postJson('/api/v1/charge', $payload, $headers);
        $secondResponse = $this->postJson('/api/v1/charge', $payload, $headers);

        $firstResponse->assertCreated()
            ->assertJsonPath('idempotency.reused', false);

        $secondResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('idempotency.reused', true)
            ->assertJsonPath('order_id', 'INV-IDEMPOTENT-001')
            ->assertJsonPath('token', 'snap-token-reuse');

        $this->assertDatabaseCount('transactions', 1);

        Http::assertSentCount(1);
    }

    public function test_duplicate_charge_request_with_different_payload_returns_conflict(): void
    {
        config([
            'services.midtrans.server_key' => 'midtrans-server-key',
            'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
            'services.midtrans.snap_path' => '/snap/v1/transactions',
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-conflict',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-conflict',
            ], 201),
        ]);

        $project = Project::create([
            'app_id' => 'project_conflict_prod',
            'project_name' => 'Project Conflict',
            'secret_key' => 'secret-conflict-123',
            'default_callback_url' => 'https://project-conflict.test/api/payment/callback',
            'is_active' => true,
        ]);

        $firstPayload = [
            'order_id' => 'INV-CONFLICT-001',
            'gross_amount' => 125000,
            'customer_details' => [
                'first_name' => 'Rina',
                'email' => 'rina@example.com',
            ],
        ];

        $secondPayload = [
            'order_id' => 'INV-CONFLICT-001',
            'gross_amount' => 130000,
            'customer_details' => [
                'first_name' => 'Rina',
                'email' => 'rina@example.com',
            ],
        ];

        $firstTimestamp = (string) now()->timestamp;
        $secondTimestamp = (string) now()->addSecond()->timestamp;

        $this->postJson(
            '/api/v1/charge',
            $firstPayload,
            $this->signedHeaders($project, $firstPayload, $firstTimestamp),
        )->assertCreated();

        $response = $this->postJson(
            '/api/v1/charge',
            $secondPayload,
            $this->signedHeaders($project, $secondPayload, $secondTimestamp),
        );

        $response->assertConflict()
            ->assertJsonPath('message', 'Order ID sudah pernah digunakan dengan payload yang berbeda.')
            ->assertJsonPath('error.code', 'order_id_conflict')
            ->assertJsonPath('error.status', 409)
            ->assertJsonPath('existing_transaction.order_id', 'INV-CONFLICT-001');

        $this->assertDatabaseCount('transactions', 1);

        Http::assertSentCount(1);
    }

    public function test_project_charge_request_is_rejected_when_hmac_signature_is_invalid(): void
    {
        config([
            'payment.auth.allow_legacy_secret_header' => false,
        ]);

        $project = Project::create([
            'app_id' => 'project_invalid_sig',
            'project_name' => 'Project Invalid Signature',
            'secret_key' => 'secret-invalid-123',
            'default_callback_url' => 'https://project-invalid.test/api/payment/callback',
            'is_active' => true,
        ]);

        $payload = [
            'order_id' => 'INV-INVALID-SIG-001',
            'gross_amount' => 50000,
        ];

        $response = $this->postJson('/api/v1/charge', $payload, [
            'X-App-ID' => $project->app_id,
            'X-Timestamp' => (string) now()->timestamp,
            'X-Payment-Signature' => 'invalid-signature',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid project request signature.')
            ->assertJsonPath('error.code', 'invalid_project_signature')
            ->assertJsonPath('error.status', 401);
    }

    public function test_inactive_project_cannot_create_charge_even_with_valid_signature(): void
    {
        config([
            'payment.auth.allow_legacy_secret_header' => false,
        ]);

        $project = Project::create([
            'app_id' => 'project_inactive_prod',
            'project_name' => 'Project Inactive',
            'secret_key' => 'secret-inactive-123',
            'default_callback_url' => 'https://project-inactive.test/api/payment/callback',
            'is_active' => false,
        ]);

        $payload = [
            'order_id' => 'INV-INACTIVE-001',
            'gross_amount' => 99000,
            'customer_details' => [
                'first_name' => 'Inactive Project',
            ],
        ];

        $timestamp = (string) now()->timestamp;

        $response = $this->postJson(
            '/api/v1/charge',
            $payload,
            $this->signedHeaders($project, $payload, $timestamp),
        );

        $response->assertForbidden()
            ->assertJsonPath('message', 'Project is inactive.')
            ->assertJsonPath('error.code', 'project_inactive')
            ->assertJsonPath('error.status', 403);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_project_can_still_use_legacy_secret_header_during_migration(): void
    {
        config([
            'services.midtrans.server_key' => 'midtrans-server-key',
            'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
            'services.midtrans.snap_path' => '/snap/v1/transactions',
            'payment.auth.allow_legacy_secret_header' => true,
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-legacy',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-legacy',
            ], 201),
        ]);

        $project = Project::create([
            'app_id' => 'project_legacy_prod',
            'project_name' => 'Project Legacy',
            'secret_key' => 'legacy-secret-abc123',
            'default_callback_url' => 'https://project-legacy.test/api/payment/callback',
            'is_active' => true,
        ]);

        $response = $this->postJson(
            '/api/v1/charge',
            [
                'order_id' => 'INV-LEGACY-001',
                'gross_amount' => 87000,
                'customer_details' => [
                    'first_name' => 'Legacy User',
                    'email' => 'legacy@example.com',
                ],
            ],
            [
                'X-App-ID' => $project->app_id,
                'X-Secret-Key' => 'legacy-secret-abc123',
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('token', 'snap-token-legacy');
    }

    public function test_project_profile_returns_integration_readiness_metadata(): void
    {
        config([
            'app.url' => 'https://payment.test',
            'services.midtrans.is_production' => false,
            'payment.auth.allow_legacy_secret_header' => true,
            'payment.callback.queue' => 'payment-callbacks',
            'payment.callback.timeout_seconds' => 12,
            'payment.callback.max_attempts' => 4,
            'payment.callback.backoff' => [60, 120, 300],
        ]);

        $project = Project::create([
            'app_id' => 'project_profile_prod',
            'project_name' => 'Project Profile',
            'secret_key' => 'secret-profile-123',
            'default_callback_url' => 'https://project-profile.test/api/payment/callback',
            'is_active' => true,
        ]);

        $timestamp = (string) now()->timestamp;
        $headers = $this->signedGetHeaders(
            project: $project,
            path: '/api/v1/projects/me',
            timestamp: $timestamp,
        );

        $response = $this->get('/api/v1/projects/me', $headers);

        $response->assertOk()
            ->assertJsonPath('data.app_id', 'project_profile_prod')
            ->assertJsonPath('data.project_name', 'Project Profile')
            ->assertJsonPath('data.authentication.mode', 'hmac_signature')
            ->assertJsonPath('data.authentication.request_headers.app_id', 'X-App-ID')
            ->assertJsonPath('data.authentication.request_headers.timestamp', 'X-Timestamp')
            ->assertJsonPath('data.authentication.request_headers.signature', 'X-Payment-Signature')
            ->assertJsonPath('data.authentication.legacy_secret_header.enabled', true)
            ->assertJsonPath('data.integration.base_url', 'https://payment.test/api/v1')
            ->assertJsonPath('data.integration.environment', 'sandbox')
            ->assertJsonPath('data.integration.endpoints.charge', '/api/v1/charge')
            ->assertJsonPath('data.callback.default_url', 'https://project-profile.test/api/payment/callback')
            ->assertJsonPath('data.callback.retry.queue', 'payment-callbacks')
            ->assertJsonPath('data.callback.retry.timeout_seconds', 12)
            ->assertJsonPath('data.callback.retry.max_attempts', 4)
            ->assertJsonPath('data.callback.retry.backoff_seconds.1', 120)
            ->assertJsonPath('data.callback.delivery_headers.signature', 'X-Payment-Signature')
            ->assertJsonPath('data.callback.signature.algorithm', 'sha256')
            ->assertJsonPath('data.callback.signature.uses_project_secret_key', true)
            ->assertJsonPath('data.readiness.status', 'ready')
            ->assertJsonPath('data.readiness.can_charge', true)
            ->assertJsonPath('data.readiness.has_default_callback_url', true)
            ->assertJsonCount(3, 'data.readiness.checks');
    }

    public function test_project_profile_marks_action_required_when_default_callback_url_is_missing(): void
    {
        $project = Project::create([
            'app_id' => 'project_profile_missing_callback',
            'project_name' => 'Project Without Callback',
            'secret_key' => 'secret-missing-callback-123',
            'default_callback_url' => null,
            'is_active' => true,
        ]);

        $timestamp = (string) now()->timestamp;
        $headers = $this->signedGetHeaders(
            project: $project,
            path: '/api/v1/projects/me',
            timestamp: $timestamp,
        );

        $response = $this->get('/api/v1/projects/me', $headers);

        $response->assertOk()
            ->assertJsonPath('data.default_callback_url', null)
            ->assertJsonPath('data.readiness.status', 'action_required')
            ->assertJsonPath('data.readiness.can_charge', true)
            ->assertJsonPath('data.readiness.has_default_callback_url', false)
            ->assertJsonPath('data.readiness.checks.1.name', 'default_callback_url_configured')
            ->assertJsonPath('data.readiness.checks.1.passed', false);
    }

    public function test_project_can_lookup_transaction_by_client_order_id(): void
    {
        $project = Project::create([
            'app_id' => 'project_lookup_prod',
            'project_name' => 'Project Lookup',
            'secret_key' => 'secret-lookup-123',
            'default_callback_url' => 'https://project-lookup.test/api/payment/callback',
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'PROJECTLOOKUP-01JABC1234567890',
            'client_order_id' => 'INV-LOOKUP-001',
            'amount' => 123000,
            'currency' => 'IDR',
            'status' => 'pending',
            'callback_status' => 'queued',
            'callback_url' => 'https://project-lookup.test/api/payment/callback',
            'payment_type' => 'bank_transfer',
            'snap_redirect_url' => 'https://midtrans.test/snap/lookup-001',
            'metadata' => [
                'invoice_id' => 7001,
            ],
            'customer_details' => [
                'first_name' => 'Lookup User',
                'email' => 'lookup@example.com',
            ],
            'last_webhook_at' => now()->subMinute(),
        ]);

        MidtransWebhookLog::create([
            'transaction_id' => $transaction->id,
            'order_id' => $transaction->gateway_order_id,
            'midtrans_transaction_id' => 'midtrans-lookup-001',
            'transaction_status' => 'pending',
            'signature_key' => 'valid-signature',
            'payload' => ['transaction_status' => 'pending'],
            'headers' => ['content-type' => 'application/json'],
            'is_signature_valid' => true,
            'processing_status' => 'processed',
            'received_at' => now()->subMinute(),
            'processed_at' => now()->subMinute(),
        ]);

        CallbackForwardingLog::create([
            'transaction_id' => $transaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://project-lookup.test/api/payment/callback',
            'attempt' => 1,
            'event_type' => 'payment.status.updated',
            'payload' => ['transaction_status' => 'pending'],
            'request_headers' => ['X-Payment-App-Id' => $project->app_id],
            'response_status_code' => 500,
            'response_body' => 'temporary error',
            'success' => false,
            'error_message' => 'HTTP 500',
            'next_retry_at' => now()->addMinute(),
            'dispatched_at' => now()->subSeconds(30),
            'responded_at' => now()->subSeconds(29),
        ]);

        $timestamp = (string) now()->timestamp;
        $headers = $this->signedGetHeaders(
            project: $project,
            path: '/api/v1/transactions/lookup?identifier=INV-LOOKUP-001&by=client_order_id',
            timestamp: $timestamp,
        );

        $response = $this->get(
            '/api/v1/transactions/lookup?identifier=INV-LOOKUP-001&by=client_order_id',
            $headers,
        );

        $response->assertOk()
            ->assertJsonPath('data.gateway_order_id', $transaction->gateway_order_id)
            ->assertJsonPath('data.order_id', 'INV-LOOKUP-001')
            ->assertJsonPath('data.callback_status', 'queued')
            ->assertJsonPath('data.redirect_url', 'https://midtrans.test/snap/lookup-001')
            ->assertJsonPath('data.callback_url', 'https://project-lookup.test/api/payment/callback')
            ->assertJsonPath('data.metadata.invoice_id', 7001)
            ->assertJsonPath('data.customer_details.first_name', 'Lookup User')
            ->assertJsonPath('data.latest_webhook.processing_status', 'processed')
            ->assertJsonPath('data.latest_webhook.is_signature_valid', true)
            ->assertJsonPath('data.latest_callback.attempt', 1)
            ->assertJsonPath('data.latest_callback.success', false)
            ->assertJsonPath('data.latest_callback.response_status_code', 500);
    }

    public function test_transaction_lookup_returns_standard_not_found_error(): void
    {
        $project = Project::create([
            'app_id' => 'project_lookup_404_prod',
            'project_name' => 'Project Lookup 404',
            'secret_key' => 'secret-lookup-404-123',
            'default_callback_url' => 'https://project-lookup-404.test/api/payment/callback',
            'is_active' => true,
        ]);

        $timestamp = (string) now()->timestamp;
        $headers = $this->signedGetHeaders(
            project: $project,
            path: '/api/v1/transactions/UNKNOWN-ORDER-001',
            timestamp: $timestamp,
        );

        $response = $this->get('/api/v1/transactions/UNKNOWN-ORDER-001', $headers);

        $response->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonPath('error.code', 'resource_not_found')
            ->assertJsonPath('error.status', 404);
    }

    public function test_project_can_lookup_transaction_with_auto_identifier_mode(): void
    {
        $project = Project::create([
            'app_id' => 'project_lookup_auto_prod',
            'project_name' => 'Project Lookup Auto',
            'secret_key' => 'secret-lookup-auto-123',
            'default_callback_url' => 'https://project-lookup-auto.test/api/payment/callback',
            'is_active' => true,
        ]);

        Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'PROJECTAUTO-01JABC1234567890',
            'client_order_id' => 'INV-AUTO-001',
            'amount' => 78000,
            'currency' => 'IDR',
            'status' => 'settlement',
            'callback_status' => 'success',
            'callback_url' => 'https://project-lookup-auto.test/api/payment/callback',
            'payment_type' => 'qris',
        ]);

        $timestamp = (string) now()->timestamp;
        $headers = $this->signedGetHeaders(
            project: $project,
            path: '/api/v1/transactions/lookup?identifier=INV-AUTO-001',
            timestamp: $timestamp,
        );

        $response = $this->get(
            '/api/v1/transactions/lookup?identifier=INV-AUTO-001',
            $headers,
        );

        $response->assertOk()
            ->assertJsonPath('data.order_id', 'INV-AUTO-001')
            ->assertJsonPath('data.status', 'settlement')
            ->assertJsonPath('data.callback_status', 'success')
            ->assertJsonPath('data.payment_type', 'qris');
    }

    public function test_project_can_get_transaction_detail_by_gateway_order_id_with_operational_summary(): void
    {
        $project = Project::create([
            'app_id' => 'project_detail_prod',
            'project_name' => 'Project Detail',
            'secret_key' => 'secret-detail-123',
            'default_callback_url' => 'https://project-detail.test/api/payment/callback',
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'PROJECTDETAIL-01JABC1234567890',
            'client_order_id' => 'INV-DETAIL-001',
            'amount' => 455000,
            'currency' => 'IDR',
            'status' => 'settlement',
            'callback_status' => 'success',
            'callback_url' => 'https://project-detail.test/api/payment/callback',
            'payment_type' => 'bank_transfer',
            'snap_redirect_url' => 'https://midtrans.test/snap/detail-001',
            'metadata' => [
                'subscription_id' => 88,
            ],
            'customer_details' => [
                'first_name' => 'Detail User',
            ],
            'paid_at' => now()->subMinutes(5),
            'last_webhook_at' => now()->subMinutes(5),
        ]);

        MidtransWebhookLog::create([
            'transaction_id' => $transaction->id,
            'order_id' => $transaction->gateway_order_id,
            'midtrans_transaction_id' => 'midtrans-detail-001',
            'transaction_status' => 'settlement',
            'signature_key' => 'valid-signature',
            'payload' => ['transaction_status' => 'settlement'],
            'headers' => ['content-type' => 'application/json'],
            'is_signature_valid' => true,
            'processing_status' => 'processed',
            'received_at' => now()->subMinutes(5),
            'processed_at' => now()->subMinutes(5),
        ]);

        CallbackForwardingLog::create([
            'transaction_id' => $transaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://project-detail.test/api/payment/callback',
            'attempt' => 1,
            'event_type' => 'payment.status.updated',
            'payload' => ['transaction_status' => 'settlement'],
            'request_headers' => ['X-Payment-App-Id' => $project->app_id],
            'response_status_code' => 200,
            'response_body' => 'ok',
            'success' => true,
            'error_message' => null,
            'next_retry_at' => null,
            'dispatched_at' => now()->subMinutes(5),
            'responded_at' => now()->subMinutes(5),
        ]);

        $timestamp = (string) now()->timestamp;
        $headers = $this->signedGetHeaders(
            project: $project,
            path: '/api/v1/transactions/PROJECTDETAIL-01JABC1234567890',
            timestamp: $timestamp,
        );

        $response = $this->get(
            '/api/v1/transactions/PROJECTDETAIL-01JABC1234567890',
            $headers,
        );

        $response->assertOk()
            ->assertJsonPath('data.gateway_order_id', 'PROJECTDETAIL-01JABC1234567890')
            ->assertJsonPath('data.order_id', 'INV-DETAIL-001')
            ->assertJsonPath('data.status', 'settlement')
            ->assertJsonPath('data.callback_status', 'success')
            ->assertJsonPath('data.latest_webhook.status', 'settlement')
            ->assertJsonPath('data.latest_callback.success', true)
            ->assertJsonPath('data.latest_callback.response_status_code', 200)
            ->assertJsonPath('data.metadata.subscription_id', 88)
            ->assertJsonPath('data.customer_details.first_name', 'Detail User');
    }

    public function test_project_can_get_callback_delivery_history_for_transaction(): void
    {
        $project = Project::create([
            'app_id' => 'project_history_prod',
            'project_name' => 'Project History',
            'secret_key' => 'secret-history-123',
            'default_callback_url' => 'https://project-history.test/api/payment/callback',
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'PROJECTHISTORY-01JABC1234567890',
            'client_order_id' => 'INV-HISTORY-001',
            'amount' => 145000,
            'currency' => 'IDR',
            'status' => 'settlement',
            'callback_status' => 'queued',
            'callback_url' => 'https://project-history.test/api/payment/callback',
            'payment_type' => 'bank_transfer',
        ]);

        CallbackForwardingLog::create([
            'transaction_id' => $transaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://project-history.test/api/payment/callback',
            'attempt' => 1,
            'event_type' => 'payment.status.updated',
            'payload' => ['transaction_status' => 'settlement'],
            'request_headers' => [
                'X-Payment-App-Id' => $project->app_id,
                'X-Payment-Delivery-Id' => 'delivery-001',
            ],
            'response_status_code' => 500,
            'response_body' => 'temporary error',
            'success' => false,
            'error_message' => 'HTTP 500',
            'next_retry_at' => now()->addMinute(),
            'dispatched_at' => now()->subMinutes(3),
            'responded_at' => now()->subMinutes(3),
        ]);

        CallbackForwardingLog::create([
            'transaction_id' => $transaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://project-history.test/api/payment/callback',
            'attempt' => 2,
            'event_type' => 'payment.status.updated',
            'payload' => ['transaction_status' => 'settlement'],
            'request_headers' => [
                'X-Payment-App-Id' => $project->app_id,
                'X-Payment-Delivery-Id' => 'delivery-002',
            ],
            'response_status_code' => 502,
            'response_body' => 'bad gateway',
            'success' => false,
            'error_message' => 'HTTP 502',
            'next_retry_at' => now()->addMinutes(5),
            'dispatched_at' => now()->subMinutes(2),
            'responded_at' => now()->subMinutes(2),
        ]);

        CallbackForwardingLog::create([
            'transaction_id' => $transaction->id,
            'project_id' => $project->id,
            'callback_url' => 'https://project-history.test/api/payment/callback',
            'attempt' => 3,
            'event_type' => 'payment.status.updated',
            'payload' => ['transaction_status' => 'settlement'],
            'request_headers' => [
                'X-Payment-App-Id' => $project->app_id,
                'X-Payment-Delivery-Id' => 'delivery-003',
            ],
            'response_status_code' => 200,
            'response_body' => 'ok',
            'success' => true,
            'error_message' => null,
            'next_retry_at' => null,
            'dispatched_at' => now()->subMinute(),
            'responded_at' => now()->subMinute(),
        ]);

        $timestamp = (string) now()->timestamp;
        $headers = $this->signedGetHeaders(
            project: $project,
            path: '/api/v1/transactions/PROJECTHISTORY-01JABC1234567890/callback-history?limit=2',
            timestamp: $timestamp,
        );

        $response = $this->get(
            '/api/v1/transactions/PROJECTHISTORY-01JABC1234567890/callback-history?limit=2',
            $headers,
        );

        $response->assertOk()
            ->assertJsonPath('data.gateway_order_id', 'PROJECTHISTORY-01JABC1234567890')
            ->assertJsonPath('data.order_id', 'INV-HISTORY-001')
            ->assertJsonPath('data.callback_status', 'queued')
            ->assertJsonCount(2, 'data.history')
            ->assertJsonPath('data.history.0.attempt', 3)
            ->assertJsonPath('data.history.0.success', true)
            ->assertJsonPath('data.history.0.delivery_id', 'delivery-003')
            ->assertJsonPath('data.history.1.attempt', 2)
            ->assertJsonPath('data.history.1.response_status_code', 502)
            ->assertJsonPath('data.history.1.error_message', 'HTTP 502');
    }

    public function test_lookup_query_tampering_is_rejected_by_hmac_signature(): void
    {
        $project = Project::create([
            'app_id' => 'project_lookup_tamper_prod',
            'project_name' => 'Project Lookup Tamper',
            'secret_key' => 'secret-lookup-tamper-123',
            'default_callback_url' => 'https://project-lookup-tamper.test/api/payment/callback',
            'is_active' => true,
        ]);

        $timestamp = (string) now()->timestamp;
        $headers = $this->signedGetHeaders(
            project: $project,
            path: '/api/v1/transactions/lookup?identifier=INV-TAMPER-001',
            timestamp: $timestamp,
        );

        $response = $this->get(
            '/api/v1/transactions/lookup?identifier=INV-TAMPER-999',
            $headers,
        );

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid project request signature.')
            ->assertJsonPath('error.code', 'invalid_project_signature')
            ->assertJsonPath('error.status', 401);
    }

    public function test_charge_validation_error_uses_standard_api_error_shape(): void
    {
        config([
            'payment.auth.allow_legacy_secret_header' => false,
        ]);

        $project = Project::create([
            'app_id' => 'project_validation_prod',
            'project_name' => 'Project Validation',
            'secret_key' => 'secret-validation-123',
            'default_callback_url' => 'https://project-validation.test/api/payment/callback',
            'is_active' => true,
        ]);

        $payload = [
            'order_id' => '',
            'gross_amount' => 0,
            'customer_details' => [],
        ];

        $timestamp = (string) now()->timestamp;

        $response = $this->postJson(
            '/api/v1/charge',
            $payload,
            $this->signedHeaders($project, $payload, $timestamp),
        );

        $errors = $response->json('error.details.errors');

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.status', 422)
            ->assertJsonPath('error.details.errors.order_id.0', 'The order id field is required.')
            ->assertJsonPath('error.details.errors.gross_amount.0', 'The gross amount field must be at least 1.');

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('customer_details.first_name', $errors);
        $this->assertSame(
            'The customer details.first name field is required.',
            $errors['customer_details.first_name'][0] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function signedHeaders(Project $project, array $payload, string $timestamp): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = app(ProjectRequestSignature::class)->make(
            appId: $project->app_id,
            secretKey: $project->secret_key,
            timestamp: $timestamp,
            method: 'POST',
            path: '/api/v1/charge',
            body: $body,
        );

        return [
            'Accept' => 'application/json',
            'X-App-ID' => $project->app_id,
            'X-Timestamp' => $timestamp,
            'X-Payment-Signature' => $signature,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function signedGetHeaders(Project $project, string $path, string $timestamp): array
    {
        $request = Request::create($path, 'GET');
        $signature = app(ProjectRequestSignature::class)->forRequest(
            request: $request,
            appId: $project->app_id,
            secretKey: $project->secret_key,
            timestamp: $timestamp,
        );

        return [
            'X-App-ID' => $project->app_id,
            'X-Timestamp' => $timestamp,
            'X-Payment-Signature' => $signature,
        ];
    }
}
