<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
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
            ->assertSee('Callback Forwarding ke Project Asal');
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
}
