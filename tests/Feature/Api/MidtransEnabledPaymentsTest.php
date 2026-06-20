<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Support\ProjectRequestSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransEnabledPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_request_does_not_send_empty_enabled_payments_to_midtrans(): void
    {
        config([
            'services.midtrans.server_key' => 'midtrans-server-key',
            'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
            'services.midtrans.snap_path' => '/snap/v1/transactions',
            'services.midtrans.enabled_payments' => [],
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-xyz',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-xyz',
            ], 201),
        ]);

        $project = Project::create([
            'app_id' => 'project_enabled_payments_empty',
            'project_name' => 'Project Enabled Payments Empty',
            'secret_key' => 'secret-enabled-payments-empty',
            'default_callback_url' => 'https://project-empty.test/api/payment/callback',
            'is_active' => true,
        ]);

        $payload = [
            'order_id' => 'INV-ENABLED-PAYMENTS-EMPTY-001',
            'gross_amount' => 10000,
            'customer_details' => [
                'first_name' => 'Amin',
                'email' => 'business@naeva.id',
            ],
            'item_details' => [
                [
                    'id' => 'SKU-TEST-001',
                    'price' => 10000,
                    'quantity' => 1,
                    'name' => 'Production Eligibility Test',
                ],
            ],
        ];

        $response = $this->postJson(
            '/api/v1/charge',
            $payload,
            $this->signedHeaders($project, $payload, (string) now()->timestamp),
        );

        $response->assertCreated()
            ->assertJsonPath('token', 'snap-token-xyz');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
                && ! array_key_exists('enabled_payments', $request->data());
        });
    }

    public function test_charge_request_sends_enabled_payments_when_configured(): void
    {
        config([
            'services.midtrans.server_key' => 'midtrans-server-key',
            'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
            'services.midtrans.snap_path' => '/snap/v1/transactions',
            'services.midtrans.enabled_payments' => ['gopay', 'qris'],
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-configured',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-configured',
            ], 201),
        ]);

        $project = Project::create([
            'app_id' => 'project_enabled_payments_configured',
            'project_name' => 'Project Enabled Payments Configured',
            'secret_key' => 'secret-enabled-payments-configured',
            'default_callback_url' => 'https://project-configured.test/api/payment/callback',
            'is_active' => true,
        ]);

        $payload = [
            'order_id' => 'INV-ENABLED-PAYMENTS-CONFIGURED-001',
            'gross_amount' => 10000,
            'customer_details' => [
                'first_name' => 'Amin',
                'email' => 'business@naeva.id',
            ],
            'item_details' => [
                [
                    'id' => 'SKU-TEST-001',
                    'price' => 10000,
                    'quantity' => 1,
                    'name' => 'Production Eligibility Test',
                ],
            ],
        ];

        $response = $this->postJson(
            '/api/v1/charge',
            $payload,
            $this->signedHeaders($project, $payload, (string) now()->timestamp),
        );

        $response->assertCreated()
            ->assertJsonPath('token', 'snap-token-configured');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
                && $request['enabled_payments'] === ['gopay', 'qris'];
        });
    }

    /**
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
}
