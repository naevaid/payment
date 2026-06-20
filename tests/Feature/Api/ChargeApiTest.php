<?php

namespace Tests\Feature\Api;

use App\Models\Project;
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
            ->assertJsonPath('message', 'Invalid project request signature.');
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
            ->assertJsonPath('message', 'Project is inactive.');

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

    public function test_project_can_lookup_transaction_by_client_order_id(): void
    {
        $project = Project::create([
            'app_id' => 'project_lookup_prod',
            'project_name' => 'Project Lookup',
            'secret_key' => 'secret-lookup-123',
            'default_callback_url' => 'https://project-lookup.test/api/payment/callback',
            'is_active' => true,
        ]);

        $transaction = \App\Models\Transaction::create([
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
            ->assertJsonPath('data.redirect_url', 'https://midtrans.test/snap/lookup-001');
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

        \App\Models\Transaction::create([
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
            ->assertJsonPath('message', 'Invalid project request signature.');
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
