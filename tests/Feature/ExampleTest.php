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
            ->assertSee('Login')
            ->assertSee('Register');
    }

    public function test_the_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('Halaman login sedang disiapkan.');
    }

    public function test_the_register_page_is_accessible(): void
    {
        $response = $this->get('/register');

        $response->assertOk()
            ->assertSee('Halaman register publik sementara placeholder.');
    }
}
