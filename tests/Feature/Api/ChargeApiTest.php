<?php

namespace Tests\Feature\Api;

use App\Models\Project;
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

        $response = $this->postJson(
            '/api/v1/charge',
            [
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
            ],
            [
                'X-App-ID' => $project->app_id,
                'X-Secret-Key' => 'secret-abc123',
            ],
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
}
