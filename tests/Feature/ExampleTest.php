<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Project;
use App\Models\Transaction;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_displays_public_navigation(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('payment.naeva.id')
            ->assertSee('Dokumentasi API')
            ->assertSee('Login')
            ->assertSee('Register');
    }

    public function test_the_public_api_documentation_page_is_accessible(): void
    {
        $response = $this->get('/docs/api');

        $response->assertOk()
            ->assertSee('Dokumentasi integrasi')
            ->assertSee('GET /projects/me')
            ->assertSee('POST /charge')
            ->assertSee('GET /transactions/lookup')
            ->assertSee('GET /transactions/{gateway_order_id}/callback-history')
            ->assertSee('/api/v1/callback/midtrans')
            ->assertSee('/midtrans/finish')
            ->assertSee('Callback Forwarding ke Project Asal')
            ->assertSee('Contoh request cURL')
            ->assertSee('Example request body')
            ->assertSee('Example response dari endpoint project')
            ->assertSee('Contoh payload webhook dari Midtrans');
    }

    public function test_the_midtrans_finish_redirect_page_is_accessible(): void
    {
        $project = Project::create([
            'app_id' => 'APP-FINISH',
            'project_name' => 'Finish Project',
            'secret_key' => 'finish-project-secret-1234',
            'default_callback_url' => 'https://finish.naeva.id/payment/callback',
            'is_active' => true,
        ]);

        Transaction::create([
            'project_id' => $project->id,
            'gateway_order_id' => 'GW-FINISH-001',
            'client_order_id' => 'INV-001',
            'amount' => 150000,
            'currency' => 'IDR',
            'status' => 'settlement',
            'callback_status' => 'success',
            'callback_url' => 'https://finish.naeva.id/payment/callback',
        ]);

        $response = $this->get('/midtrans/finish?order_id=INV-001&transaction_status=settlement');

        $response->assertOk()
            ->assertSee('Transaksi Anda Berhasil')
            ->assertSee('INV-001')
            ->assertSee('Settlement')
            ->assertSee('Rp 150.000 IDR')
            ->assertDontSee('Lihat Dokumentasi API')
            ->assertDontSee('Finish Redirect URL');
    }

    public function test_the_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('Masuk ke dashboard utama')
            ->assertSee('Lupa password?');
    }

    public function test_the_register_page_is_accessible(): void
    {
        $response = $this->get('/register');

        $response->assertOk()
            ->assertSee('Buat akun dashboard utama')
            ->assertSee('Register');
    }

    public function test_application_timezone_defaults_to_wib_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('+07:00', config('database.connections.mysql.timezone'));
        $this->assertSame('+07:00', config('database.connections.mariadb.timezone'));
    }
}
