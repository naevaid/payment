<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Support\ProjectRequestSignature;
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
            ->assertJsonPath('token', 'snap-token-xyz');

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
            'X-App-ID' => $project->app_id,
            'X-Timestamp' => $timestamp,
            'X-Payment-Signature' => $signature,
        ];
    }
}
