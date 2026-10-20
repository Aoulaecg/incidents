<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateFirstAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_admin_is_created_interactively_once_with_a_hashed_personal_password(): void
    {
        $existing = User::factory()->create();
        $oldHash = $existing->password;
        $password = Str::password(24);
        $this->artisan('app:create-admin')
            ->expectsQuestion('Prénom', 'Nadia')
            ->expectsQuestion('Nom', 'Idrissi')
            ->expectsQuestion('Adresse email professionnelle', 'nadia@example.test')
            ->expectsQuestion('Mot de passe (12 caractères, majuscule, minuscule, chiffre, symbole)', $password)
            ->expectsQuestion('Confirmer le mot de passe', $password)
            ->expectsConfirmation('Créer ce compte administrateur réel ?', 'yes')
            ->assertSuccessful();
        $admin = User::where('email', 'nadia@example.test')->firstOrFail();
        $this->assertTrue($admin->isAdministrator());
        $this->assertFalse($admin->must_change_password);
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertSame($oldHash, $existing->fresh()->password);
        $this->artisan('app:create-admin')->assertFailed();
        $this->assertDatabaseCount('users', 2);
    }

    public function test_invalid_credentials_do_not_create_an_admin_or_overwrite_an_existing_account(): void
    {
        $existing = User::factory()->create();
        $this->artisan('app:create-admin')
            ->expectsQuestion('Prénom', 'Nadia')
            ->expectsQuestion('Nom', 'Idrissi')
            ->expectsQuestion('Adresse email professionnelle', $existing->email)
            ->expectsQuestion('Mot de passe (12 caractères, majuscule, minuscule, chiffre, symbole)', 'weak')
            ->expectsQuestion('Confirmer le mot de passe', 'weak')
            ->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertFalse($existing->fresh()->isAdministrator());
    }

    public function test_cancelling_bootstrap_leaves_the_database_unchanged(): void
    {
        $password = Str::password(24);
        $this->artisan('app:create-admin')
            ->expectsQuestion('Prénom', 'Nadia')
            ->expectsQuestion('Nom', 'Idrissi')
            ->expectsQuestion('Adresse email professionnelle', 'nadia@example.test')
            ->expectsQuestion('Mot de passe (12 caractères, majuscule, minuscule, chiffre, symbole)', $password)
            ->expectsQuestion('Confirmer le mot de passe', $password)
            ->expectsConfirmation('Créer ce compte administrateur réel ?', 'no')
            ->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
    }
}
