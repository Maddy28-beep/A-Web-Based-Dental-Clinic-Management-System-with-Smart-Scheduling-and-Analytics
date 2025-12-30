<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthUiSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_staff_is_redirected_away_from_client_pages(): void
    {
        $admin = User::create([
            'name' => 'Admin Client Redirect',
            'email' => 'admin.client.redirect@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_role' => 'admin', 'auth_interface' => 'staff'])
            ->get('/welcome')
            ->assertStatus(302)
            ->assertRedirect('/charting');

        $this->actingAs($admin)
            ->withSession(['auth_role' => 'admin', 'auth_interface' => 'staff'])
            ->get('/')
            ->assertStatus(302)
            ->assertRedirect('/charting');
    }

    public function test_invalid_staff_session_role_is_forced_to_relogin(): void
    {
        $admin = User::create([
            'name' => 'Admin Session Invalid',
            'email' => 'admin.session.invalid@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get('/charting')
            ->assertStatus(302)
            ->assertRedirect('/staff/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_client_welcome_page_does_not_show_staff_login_ui(): void
    {
        $this->get('/welcome')
            ->assertStatus(200)
            ->assertDontSee('Clinic staff login')
            ->assertDontSee('Sign In')
            ->assertDontSee('action="/login"', false)
            ->assertDontSee('/staff/login', false)
            ->assertDontSee('Staff Portal');
    }

    public function test_public_booking_page_does_not_expose_staff_entry_points(): void
    {
        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee('/staff/login', false)
            ->assertDontSee('Staff Login')
            ->assertDontSee('/charting', false);
    }

    public function test_staff_login_route_shows_staff_login_ui(): void
    {
        $this->get('/staff/login')
            ->assertStatus(200)
            ->assertSee('Clinic staff login')
            ->assertSee('action="/login"', false);
    }

    public function test_authenticated_staff_is_redirected_away_from_login_page(): void
    {
        $admin = User::create([
            'name' => 'Admin Login Redirect',
            'email' => 'admin.login.redirect@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get('/login')
            ->assertStatus(302)
            ->assertRedirect('/charting');
    }

    public function test_receptionist_login_redirects_to_appointments_dashboard(): void
    {
        User::create([
            'name' => 'Receptionist Login Redirect',
            'email' => 'receptionist.login.redirect@example.com',
            'password' => 'password',
            'role' => 'receptionist',
        ]);

        $this->post('/login', [
            'email' => 'receptionist.login.redirect@example.com',
            'password' => 'password',
        ])
            ->assertStatus(302)
            ->assertRedirect('/appointments-dashboard');
    }

    public function test_non_staff_role_is_rejected_by_staff_login_flow(): void
    {
        User::create([
            'name' => 'Patient User',
            'email' => 'patient.user@example.com',
            'password' => 'password',
            'role' => 'patient',
        ]);

        $this->from('/staff/login')
            ->post('/login', [
                'email' => 'patient.user@example.com',
                'password' => 'password',
            ])
            ->assertStatus(302)
            ->assertRedirect('/staff/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
