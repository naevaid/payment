<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_opening_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_user_can_register_and_receive_welcome_notification(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Naeva Business',
            'email' => 'business@naeva.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $user = User::where('email', 'business@naeva.id')->firstOrFail();

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, WelcomeUserNotification::class);
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'password' => 'Password123!',
        ]);

        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $loginResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $logoutResponse = $this->post('/logout');

        $logoutResponse->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'Naeva Business',
            'email' => 'business@naeva.id',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee('Dashboard utama payment service')
            ->assertSee('business@naeva.id');
    }

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'business@naeva.id',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }
}
