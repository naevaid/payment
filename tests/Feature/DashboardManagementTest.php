<?php

namespace Tests\Feature;

use App\Enums\CallbackStatus;
use App\Enums\TransactionStatus;
use App\Models\CallbackForwardingLog;
use App\Models\MidtransWebhookLog;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_authenticated_user_can_view_operational_dashboard_pages(): void
    {
        $user = User::factory()->create();

        $project = Project::create([
            'app_id' => 'APP-OPS',
            'project_name' => 'Ops Project',
            'secret_key' => 'ops-project-secret-1234',
            'default_callback_url' => 'https://ops.naeva.id/callback',
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
            'callback_url' => 'https://ops.naeva.id/callback',
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
            'callback_url' => 'https://ops.naeva.id/callback',
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
            ->assertSee('https://ops.naeva.id/callback');

        $this->actingAs($user)
            ->get(route('dashboard.callback-logs.show', $callbackLog))
            ->assertOk()
            ->assertSee('payment.status.updated')
            ->assertSee('ok');
    }
}
