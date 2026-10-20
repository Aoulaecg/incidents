<?php
namespace Tests\Feature;

use App\Models\{Incident, IncidentAudit, Parking, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeclarationTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->withoutVite(); }
    private function supervisor(): array
    {
        $user = User::factory()->create();
        $parking = Parking::create(['nom' => 'Taghazout', 'active' => true]);
        $user->parkings()->attach($parking);
        return [$user, $parking];
    }
    private function data(Parking $parking, array $extra = []): array
    {
        return array_replace(['parking_id' => $parking->id, 'date_incident' => now()->toDateString(), 'heure_incident' => '09:30', 'description' => 'Départ de fumée maîtrisé.', 'submission_token' => (string) Str::uuid()], $extra);
    }
    public function test_supervisor_parking_lists_follow_the_verified_three_person_assignment_map(): void
    {
        foreach (config('parking_assignments') as $name => $parkings) {
            [$first, $last] = explode(' ', $name, 2);
            User::factory()->create(['prenom' => $first, 'nom' => $last]);
        }
        $this->artisan('app:assign-parking-map')->assertSuccessful();
        $this->assertDatabaseCount('parkings', 0);
        $this->artisan('app:assign-parking-map --apply')->assertSuccessful();
        $this->artisan('app:assign-parking-map --apply')->assertSuccessful();
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('parkings', 14);
        $this->assertDatabaseCount('parking_user', 14);
        foreach (User::all() as $user) {
            $this->assertEqualsCanonicalizing(config('parking_assignments')[$user->full_name], $user->parkings()->pluck('nom')->all());
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $this->assertSame(count(config('parking_assignments')[$user->full_name]), $response->viewData('parkings')->count());
            foreach (Parking::whereNotIn('id', $user->parkings()->pluck('parkings.id'))->get() as $other) $response->assertDontSee($other->nom);
        }
    }
    public function test_missing_or_ambiguous_real_identity_aborts_all_assignment_changes(): void
    {
        $this->artisan('app:assign-parking-map --apply')->assertFailed();
        $this->assertDatabaseCount('parkings', 0);
        foreach (config('parking_assignments') as $name => $_) {
            [$first, $last] = explode(' ', $name, 2);
            User::factory()->create(['prenom' => $first, 'nom' => $last]);
        }
        User::factory()->create(['prenom' => 'Rabie', 'nom' => 'Benmahmoud']);
        $this->artisan('app:assign-parking-map --apply')->assertFailed();
        $this->assertDatabaseCount('parking_user', 0);
    }
    public function test_incident_is_attributed_to_the_authenticated_user_and_saved_once(): void
    {
        [$user, $parking] = $this->supervisor();
        $data = $this->data($parking, ['user_id' => 999, 'role' => 'admin']);
        $this->actingAs($user)->post(route('supervisor.incidents.store'), $data)->assertRedirect(route('dashboard'));
        $this->post(route('supervisor.incidents.store'), $data)->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('incident_audits', 1);
        $this->assertDatabaseHas('incidents', ['user_id' => $user->id, 'parking_id' => $parking->id, 'type_declaration' => 'incident', 'description' => $data['description']]);
        $this->get(route('dashboard'))->assertSee($data['description']);
    }
    public function test_reusing_an_incident_token_with_different_facts_is_rejected(): void
    {
        [$user, $parking] = $this->supervisor(); $data = $this->data($parking);
        $this->actingAs($user)->post(route('supervisor.incidents.store'), $data);
        $this->post(route('supervisor.incidents.store'), array_replace($data, ['description' => 'Autre fait']))->assertSessionHasErrors('submission_token');
        $this->assertDatabaseCount('incidents', 1);
    }
    public function test_an_unassigned_or_inactive_parking_cannot_receive_a_declaration(): void
    {
        [$user, $parking] = $this->supervisor();
        $other = Parking::create(['nom' => 'Autre parking']);
        $this->actingAs($user)->post(route('supervisor.incidents.store'), $this->data($other))->assertSessionHasErrors('parking_id');
        $parking->update(['active' => false]);
        $this->post(route('supervisor.incidents.store'), $this->data($parking))->assertSessionHasErrors('parking_id');
        $this->assertDatabaseCount('incidents', 0);
    }
    public function test_ras_needs_no_description_uses_morocco_today_and_cannot_be_duplicated(): void
    {
        [$user, $parking] = $this->supervisor();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-12 23:55', 'Africa/Casablanca'));
        $data = ['parking_id' => $parking->id, 'confirm_ras' => 1, 'submission_token' => (string) Str::uuid(), 'date_incident' => '2000-01-01', 'heure_incident' => '01:00'];
        $this->actingAs($user)->post(route('supervisor.ras.store'), $data)->assertRedirect(route('dashboard'));
        $this->post(route('supervisor.ras.store'), array_replace($data, ['submission_token' => (string) Str::uuid()]))->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('incidents', 1);
        $ras = Incident::first();
        $this->assertNull($ras->description);
        $this->assertNull($ras->heure_incident);
        $this->assertSame('2026-10-12', $ras->date_incident->toDateString());
        $this->assertSame('23:55', $ras->validated_at->format('H:i'));
    }
    public function test_ras_requires_confirmation_and_is_refused_after_an_incident_by_another_supervisor(): void
    {
        [$user, $parking] = $this->supervisor();
        $other = User::factory()->create(); $other->parkings()->attach($parking);
        $this->actingAs($other)->post(route('supervisor.incidents.store'), $this->data($parking));
        $this->actingAs($user)->post(route('supervisor.ras.store'), ['parking_id' => $parking->id, 'submission_token' => (string) Str::uuid()])->assertSessionHasErrors('confirm_ras');
        $this->post(route('supervisor.ras.store'), ['parking_id' => $parking->id, 'submission_token' => (string) Str::uuid(), 'confirm_ras' => 1])->assertSessionHasErrors('parking_id');
        $this->assertDatabaseCount('incidents', 1);
    }
    public function test_later_incident_supersedes_ras_without_deleting_its_owner_or_history(): void
    {
        [$user, $parking] = $this->supervisor();
        $other = User::factory()->create(); $other->parkings()->attach($parking);
        $this->actingAs($user)->post(route('supervisor.ras.store'), ['parking_id' => $parking->id, 'submission_token' => (string) Str::uuid(), 'confirm_ras' => 1]);
        $ras = Incident::first();
        $this->actingAs($other)->post(route('supervisor.incidents.store'), $this->data($parking));
        $ras->refresh();
        $this->assertSame('superseded', $ras->status);
        $this->assertSame($user->id, $ras->user_id);
        $this->assertNotNull($ras->superseded_at);
        $this->assertNotNull($ras->superseded_by_id);
        $this->assertDatabaseCount('incidents', 2);
        $this->assertDatabaseCount('incident_audits', 3);
        $this->assertDatabaseHas('incident_audits', ['incident_id' => $ras->id, 'actor_user_id' => $other->id, 'event' => 'ras_superseded']);
        $this->actingAs($user)->get(route('dashboard'))->assertSee('Confirmation annulée');
    }
    public function test_shared_parkings_show_other_supervisors_but_hide_unassigned_declarations(): void
    {
        [$user, $parking] = $this->supervisor();
        $other = User::factory()->create(); $other->parkings()->attach($parking);
        $this->actingAs($other)->post(route('supervisor.incidents.store'), $this->data($parking, ['description' => 'Visible sur le parking partagé']));
        $private = Parking::create(['nom' => 'Parking privé']); $other->parkings()->attach($private);
        $this->post(route('supervisor.incidents.store'), $this->data($private, ['description' => 'Déclaration confidentielle hors périmètre']));
        $this->actingAs($user)->get(route('dashboard'))->assertSee('Visible sur le parking partagé')->assertSee($other->full_name)
            ->assertDontSee('Déclaration confidentielle hors périmètre')->assertDontSee('Parking privé');
    }
    public function test_description_is_required_and_old_values_survive_validation_errors(): void
    {
        [$user, $parking] = $this->supervisor();
        $data = $this->data($parking, ['description' => '']);
        $this->actingAs($user)->from(route('dashboard'))->post(route('supervisor.incidents.store'), $data)
            ->assertSessionHasErrors('description')->assertSessionHas('_old_input.parking_id', $parking->id);
        $this->assertDatabaseCount('incidents', 0);
        $this->get(route('dashboard'))->assertOk();
    }
    public function test_director_has_global_filters_counts_and_no_write_or_admin_access(): void
    {
        [$user, $parking] = $this->supervisor();
        $missing = Parking::create(['nom' => 'Sans déclaration']);
        Parking::create(['nom' => 'Parking inactif', 'active' => false]);
        $this->actingAs($user)->post(route('supervisor.ras.store'), ['parking_id' => $parking->id, 'submission_token' => (string) Str::uuid(), 'confirm_ras' => 1]);
        $this->post(route('supervisor.incidents.store'), $this->data($parking));
        $director = User::factory()->create(['role' => User::ROLE_DIRECTOR]);
        $response = $this->actingAs($director)->get(route('director.dashboard'))->assertOk()->assertSee($parking->nom)->assertSee($missing->nom);
        $this->assertSame(['incidents' => 1, 'ras' => 0, 'missing' => 1], $response->viewData('stats'));
        $response = $this->get(route('director.dashboard', ['parking_id' => $parking->id, 'date' => now()->toDateString(), 'type' => 'incident', 'q' => 'fumée', 'sort' => 'oldest']))->assertOk();
        $this->assertSame(1, $response->viewData('declarations')->total());
        $this->assertSame(0, $response->viewData('stats')['missing']);
        $response = $this->get(route('director.dashboard', ['date' => now()->subDay()->toDateString()]))->assertOk();
        $this->assertSame(0, $response->viewData('declarations')->total());
        $this->assertSame(2, $response->viewData('stats')['missing']);
        $this->post(route('supervisor.incidents.store'), $this->data($parking))->assertForbidden();
        $this->post(route('supervisor.ras.store'), [])->assertForbidden();
        $this->get(route('admin.supervisors.index'))->assertForbidden();
        $this->actingAs($user)->get(route('director.dashboard'))->assertForbidden();
    }
    public function test_administrator_can_assign_parkings_and_create_a_real_director(): void
    {
        [$user, $parking] = $this->supervisor(); $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('admin.supervisors.parkings.edit', $user))->assertOk();
        $this->put(route('admin.supervisors.parkings.update', $user), ['parking_ids' => []])->assertRedirect(route('admin.supervisors.index'));
        $this->assertSame(0, $user->parkings()->count());
        $this->put(route('admin.supervisors.parkings.update', $user), ['parking_ids' => [$parking->id]])->assertRedirect(route('admin.supervisors.index'));
        $this->assertSame(1, $user->parkings()->count());
        $this->put(route('admin.supervisors.parkings.update', $user), ['parking_ids' => [$parking->id, $parking->id]])->assertSessionHasErrors('parking_ids.0');
        $password = Str::password(24);
        $this->post(route('admin.supervisors.store'), ['prenom' => 'Direction', 'nom' => 'CGPark', 'email' => 'direction@example.test', 'role' => 'directeur', 'active' => 1, 'password' => $password, 'password_confirmation' => $password])->assertRedirect(route('admin.supervisors.index'));
        $director = User::where('email', 'direction@example.test')->firstOrFail();
        $this->assertSame('password.change', $director->homeRoute());
        $director->forceFill(['must_change_password' => false])->save();
        $this->assertSame('director.dashboard', $director->homeRoute());
        $this->actingAs($user)->put(route('admin.supervisors.parkings.update', $user), ['parking_ids' => []])->assertForbidden();
    }
    public function test_director_login_paginates_and_keeps_combined_filters(): void
    {
        [$user, $parking] = $this->supervisor();
        $this->actingAs($user);
        for ($i = 0; $i < 21; $i++) $this->post(route('supervisor.incidents.store'), $this->data($parking, ['description' => 'Déclaration suivie '.$i]));
        $this->post(route('logout'));
        $director = User::factory()->create(['role' => 'directeur']);
        $this->post(route('director.login.store'), ['email' => $director->email, 'password' => \Database\Factories\UserFactory::testPassword()])->assertRedirect(route('director.dashboard'));
        $response = $this->get(route('director.dashboard', ['parking_id' => $parking->id, 'date' => now()->toDateString(), 'q' => 'suivie', 'page' => 2]))->assertOk();
        $this->assertSame(21, $response->viewData('declarations')->total());
        $this->assertSame(1, $response->viewData('declarations')->count());
        $this->assertStringContainsString('parking_id=', $response->viewData('declarations')->url(1));
        $response->assertDontSee('Gestion des superviseurs');
    }
    public function test_declarations_have_csrf_protection_and_single_parking_selection(): void
    {
        [$user, $parking] = $this->supervisor();
        $this->actingAs($user)->get(route('dashboard'))->assertSee('value="'.$parking->id.'" selected', false);
        $this->app->detectEnvironment(fn () => 'local');
        $this->post(route('supervisor.incidents.store'), $this->data($parking))->assertStatus(419);
        $this->post(route('supervisor.ras.store'), [])->assertStatus(419);
    }
    public function test_workspaces_can_be_rendered_for_browser_review(): void
    {
        [$user, $parking] = $this->supervisor();
        $this->actingAs($user)->post(route('supervisor.incidents.store'), $this->data($parking));
        Parking::create(['nom' => 'Aéroport Marrakech', 'active' => true]);
        $views = ['supervisor-workspace' => $this->get(route('dashboard'))->assertOk()->getContent()];
        $director = User::factory()->create(['role' => 'directeur']);
        $views['director-workspace'] = $this->actingAs($director)->get(route('director.dashboard'))->assertOk()->getContent();
        $admin = User::factory()->create(['role' => 'admin']);
        $views['parking-assignments'] = $this->actingAs($admin)->get(route('admin.supervisors.parkings.edit', $user))->assertOk()->getContent();
        foreach ($views as $name => $html) {
            $this->assertStringNotContainsString('@else', $html);
            if (getenv('EXPORT_REVIEW_VIEWS') === '1') {
                if (! is_dir(base_path('.artifacts'))) mkdir(base_path('.artifacts'), 0777, true);
                file_put_contents(base_path('.artifacts/'.$name.'.html'), $html);
            }
        }
    }
}
