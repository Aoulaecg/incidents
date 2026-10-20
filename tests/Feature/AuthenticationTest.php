<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SupervisorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_login_lists_only_active_authorized_accounts_from_the_database(): void
    {
        $user = User::factory()->create(['prenom' => 'Sara', 'nom' => 'Bennani']);
        $inactive = User::factory()->create(['active' => false]);
        $admin = User::factory()->create(['role' => 'admin']);
        $unknownRole = User::factory()->create(['role' => 'autre']);

        $this->get(route('login'))->assertOk()
            ->assertSee('Sara Bennani')
            ->assertDontSee($inactive->full_name)
            ->assertDontSee($admin->full_name)
            ->assertDontSee($unknownRole->full_name)
            ->assertDontSee($user->email)
            ->assertDontSee($user->password)
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_empty_user_list_has_a_clear_message(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Aucun superviseur');
    }

    public function test_root_redirects_to_login_and_internal_page_requires_authentication(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_valid_credentials_authenticate_and_regenerate_the_session(): void
    {
        $user = User::factory()->create();
        $this->withSession(['marker' => 'preserved']);
        $previousSession = session()->getId();

        $this->post(route('login.store'), ['user_id' => $user->id, 'password' => \Database\Factories\UserFactory::testPassword()])
            ->assertRedirect(route('dashboard'))->assertSessionHas('marker', 'preserved');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousSession, session()->getId());
        $this->get(route('dashboard'))->assertOk()->assertSee($user->full_name);
        $this->get(route('login'))->assertRedirect(route('dashboard'));
    }

    public function test_missing_user_and_password_receive_french_validation_errors(): void
    {
        $this->post(route('login.store'), [])->assertSessionHasErrors([
            'user_id' => 'Veuillez sélectionner votre utilisateur.',
            'password' => 'Veuillez saisir votre mot de passe.',
        ]);
        $this->assertGuest();
    }

    public function test_wrong_password_is_not_flashed_and_selected_user_is_preserved(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'user_id' => $user->id, 'password' => 'incorrect-secret',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('password')
            ->assertSessionHas('_old_input.user_id', $user->id)
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_unknown_inactive_and_non_supervisor_accounts_receive_the_same_error(): void
    {
        $inactive = User::factory()->create(['active' => false]);
        $admin = User::factory()->create(['role' => 'autre']);
        $error = 'Connexion impossible. Vérifiez vos identifiants.';

        foreach ([$inactive->id, $admin->id, 999999] as $id) {
            $this->post(route('login.store'), ['user_id' => $id, 'password' => \Database\Factories\UserFactory::testPassword()])
                ->assertSessionHasErrors(['password' => $error]);
            $this->assertGuest();
        }
    }

    public function test_sixth_attempt_is_blocked_even_with_the_correct_password_and_expires(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['user_id' => $user->id, 'password' => 'wrong']);
        }

        $this->post(route('login.store'), ['user_id' => $user->id, 'password' => \Database\Factories\UserFactory::testPassword()])
            ->assertSessionHasErrors('password');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('password'));
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->post(route('login.store'), ['user_id' => $user->id, 'password' => \Database\Factories\UserFactory::testPassword()])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_switching_user_ids_does_not_bypass_the_ip_limit(): void
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->post(route('login.store'), ['user_id' => 10000 + $attempt, 'password' => 'wrong']);
        }

        $user = User::factory()->create();
        $this->post(route('login.store'), ['user_id' => $user->id, 'password' => \Database\Factories\UserFactory::testPassword()])
            ->assertSessionHasErrors('password');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('password'));
        $this->assertGuest();
    }

    public function test_success_clears_the_account_attempts(): void
    {
        $user = User::factory()->create();
        $key = 'login:'.hash('sha256', $user->id.'|127.0.0.1');
        $this->post(route('login.store'), ['user_id' => $user->id, 'password' => 'wrong']);
        $this->assertSame(1, RateLimiter::attempts($key));
        $this->post(route('login.store'), ['user_id' => $user->id, 'password' => \Database\Factories\UserFactory::testPassword()]);
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_disabled_account_loses_access_to_an_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['active' => false])->save();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_session_and_rotates_the_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['marker' => 'removed']);
        session()->regenerateToken();
        $oldToken = session()->token();
        $oldId = session()->getId();

        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionMissing('marker');
        $this->assertGuest();
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
    }

    public function test_login_and_logout_reject_requests_without_a_csrf_token(): void
    {
        // Laravel normally bypasses CSRF during tests: enable the real middleware here.
        $this->app->detectEnvironment(fn () => 'local');
        $user = User::factory()->create();
        $this->post(route('login.store'), ['user_id' => $user->id, 'password' => \Database\Factories\UserFactory::testPassword()])
            ->assertStatus(419);
        $this->assertGuest();
        $this->actingAs($user)->post(route('logout'))->assertStatus(419);
    }

    public function test_seeders_do_not_create_accounts_or_change_existing_credentials(): void
    {
        $user = User::factory()->create(['active' => false]);
        $originalHash = $user->password;
        $this->seed(SupervisorSeeder::class);
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertFalse($user->fresh()->active);
        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_demo_accounts_are_not_seeded_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        (new SupervisorSeeder)->run();
        $this->assertDatabaseCount('users', 0);
    }
}
