<?php

namespace Tests\Feature;

use App\Jobs\SendDeclarationNotification;
use App\Mail\DeclarationRecorded;
use App\Models\DeclarationNotification;
use App\Models\Incident;
use App\Models\Parking;
use App\Models\User;
use App\Services\DeclarationNotificationService;
use App\Services\DeclarationService;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\MailFake;
use RuntimeException;
use Tests\TestCase;

class DeclarationNotificationTest extends TestCase
{
    // Real commits are necessary to exercise the afterCommit callback and database queue.
    use DatabaseMigrations;

    private User $supervisor;

    private User $director;

    private Parking $parking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        $this->supervisor = User::factory()->create(['prenom' => 'Rabie', 'nom' => 'Benmahmoud']);
        $this->director = User::factory()->create(['role' => User::ROLE_DIRECTOR, 'prenom' => 'Hamza', 'nom' => 'ELHAJOUI']);
        $this->parking = Parking::create(['nom' => 'Parking CGPark']);
        $this->supervisor->parkings()->attach($this->parking);
    }

    private function data(array $extra = []): array
    {
        return array_replace([
            'parking_id' => $this->parking->id,
            'submission_token' => (string) Str::uuid(),
            'date_incident' => now('Africa/Casablanca')->toDateString(),
            'heure_incident' => '09:30',
            'description' => "Fumée dans le parking.\nIntervention du superviseur.",
        ], $extra);
    }

    private function incident(array $extra = []): DeclarationNotification
    {
        $this->actingAs($this->supervisor)->post(route('supervisor.incidents.store'), $this->data($extra))
            ->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

        return DeclarationNotification::latest('id')->firstOrFail();
    }

    public function test_incident_is_committed_and_queued_without_sending_during_the_request(): void
    {
        $notification = $this->incident();
        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('declaration_notifications', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame('pending', $notification->status);
        $this->assertSame(0, $notification->attempts);
        Mail::assertNothingSent();

        // Exercise actual serialization, worker execution and the Mailable from the database queue.
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        Mail::assertSent(DeclarationRecorded::class, function ($mail) {
            return $mail->hasTo($this->director->email)
                && $mail->envelope()->subject === '[CGPark] Nouvel incident signalé – Parking CGPark'
                && $mail->notification->details['supervisor'] === $this->supervisor->full_name
                && $mail->notification->details['time'] === '09:30';
        });
        $this->assertDatabaseHas('declaration_notifications', [
            'id' => $notification->id, 'status' => 'sent', 'attempts' => 1,
            'recipient_user_id' => $this->director->id, 'recipient_email' => $this->director->email,
        ]);
        $this->assertNotNull($notification->refresh()->sent_at);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_ras_uses_casablanca_today_and_sends_the_distinct_email(): void
    {
        // UTC is still on the previous date at this instant in Casablanca.
        $this->travelTo(Carbon::parse('2026-10-16 00:15:00', 'Africa/Casablanca'));
        $data = $this->data(['confirm_ras' => 1, 'date_incident' => '2000-01-01']);
        $this->actingAs($this->supervisor)->post(route('supervisor.ras.store'), $data)->assertSessionHasNoErrors();
        $notification = DeclarationNotification::firstOrFail();
        $this->assertSame('16/10/2026', $notification->details['date']);
        $this->assertNull($notification->details['description']);
        $this->assertNull($notification->details['time']);
        (new SendDeclarationNotification($notification->id))->handle(app(DeclarationNotificationService::class));
        Mail::assertSent(DeclarationRecorded::class, fn ($mail) => $mail->hasTo($this->director->email)
            && $mail->envelope()->subject === '[CGPark] Rien à signaler – Parking CGPark – 16/10/2026');
        $this->assertSame('00:15', $notification->refresh()->sent_at->format('H:i'));
    }

    public function test_replays_and_repeated_ras_create_one_notification_each(): void
    {
        $data = $this->data();
        $this->actingAs($this->supervisor)->post(route('supervisor.incidents.store'), $data);
        $this->post(route('supervisor.incidents.store'), $data);
        $this->post(route('supervisor.incidents.store'), array_replace($data, ['description' => 'Different']))
            ->assertSessionHasErrors('submission_token');
        $this->assertDatabaseCount('declaration_notifications', 1);
        $this->assertDatabaseCount('jobs', 1);

        $rasData = $this->data(['confirm_ras' => 1]);
        $this->travel(1)->days();
        $this->post(route('supervisor.ras.store'), $rasData);
        $this->post(route('supervisor.ras.store'), $rasData);
        $this->post(route('supervisor.ras.store'), array_replace($rasData, ['submission_token' => (string) Str::uuid()]));
        $this->assertDatabaseCount('declaration_notifications', 2);
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_nothing_is_queued_before_commit_and_a_rollback_removes_the_outbox(): void
    {
        DB::beginTransaction();
        app(DeclarationService::class)->store($this->supervisor, $this->data(), Incident::INCIDENT);
        $this->assertDatabaseCount('declaration_notifications', 1);
        $this->assertDatabaseCount('jobs', 0);
        DB::rollBack();
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('declaration_notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertNothingSent();
    }

    public function test_invalid_unassigned_inactive_and_contradictory_submissions_never_queue_mail(): void
    {
        $this->actingAs($this->supervisor)->post(route('supervisor.incidents.store'), $this->data(['description' => '']))
            ->assertSessionHasErrors('description');
        $otherParking = Parking::create(['nom' => 'Non affecté']);
        $this->post(route('supervisor.incidents.store'), $this->data(['parking_id' => $otherParking->id]))
            ->assertSessionHasErrors('parking_id');
        $this->parking->update(['active' => false]);
        $this->post(route('supervisor.incidents.store'), $this->data())->assertSessionHasErrors('parking_id');
        $this->assertDatabaseCount('declaration_notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->parking->update(['active' => true]);
        $this->incident();
        $this->post(route('supervisor.ras.store'), $this->data(['confirm_ras' => 1]))->assertSessionHasErrors('parking_id');
        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('declaration_notifications', 1);
        Mail::assertNothingSent();
    }

    public function test_failed_smtp_preserves_the_declaration_and_retries_without_exposing_secrets(): void
    {
        $notification = $this->incident();
        Log::spy();
        Mail::shouldReceive('to')->once()->with($this->director->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP password=never-expose-this'));
        try {
            (new SendDeclarationNotification($notification->id))->handle(app(DeclarationNotificationService::class));
            $this->fail('Delivery should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('delivery_failed', $exception->getMessage());
        }
        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseHas('declaration_notifications', ['status' => 'failed', 'attempts' => 1, 'last_error' => 'delivery_failed', 'sent_at' => null]);
        Log::shouldHaveReceived('warning')->with('declaration_notification_delivery_failed', \Mockery::on(fn ($context) => ! str_contains(json_encode($context), 'never-expose-this')))->once();

        Mail::swap(new MailFake(new MailManager($this->app)));
        $job = new SendDeclarationNotification($notification->id);
        $job->handle(app(DeclarationNotificationService::class));
        $job->handle(app(DeclarationNotificationService::class));
        Mail::assertSentCount(1);
        $this->assertSame(2, $notification->refresh()->attempts);
        $this->assertSame('sent', $notification->status);
        $this->assertNull($notification->last_error);
    }

    public function test_worker_releases_failed_deliveries_for_a_later_attempt(): void
    {
        $notification = $this->incident();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('secret-smtp-response'));
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->assertDatabaseHas('declaration_notifications', ['id' => $notification->id, 'status' => 'failed', 'attempts' => 1]);
        $job = DB::table('jobs')->first();
        $this->assertSame(1, $job->attempts);
        $this->assertGreaterThan(now()->timestamp, $job->available_at);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_missing_director_fails_safely_and_can_be_retried_after_account_repair(): void
    {
        $this->director->forceFill(['active' => false])->save();
        $notification = $this->incident();
        try {
            (new SendDeclarationNotification($notification->id))->handle(app(DeclarationNotificationService::class));
            $this->fail('Missing director should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('director_unavailable_or_ambiguous', $exception->getMessage());
        }
        Mail::assertNothingSent();
        (new SendDeclarationNotification($notification->id))->failed(null);
        $this->director->forceFill(['active' => true, 'email' => 'updated-director@example.test'])->save();
        // Remove the first simulated job; in production the worker removes exhausted jobs.
        DB::table('jobs')->delete();
        $this->artisan('notifications:dispatch --retry-failed')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        Mail::assertSent(DeclarationRecorded::class, fn ($mail) => $mail->hasTo('updated-director@example.test'));
        $this->assertSame('sent', $notification->refresh()->status);
    }

    public function test_queue_outage_does_not_break_the_save_and_dispatch_recovers_the_outbox(): void
    {
        Schema::rename('jobs', 'unavailable_jobs');
        $notification = $this->incident();
        $this->assertSame('pending', $notification->status);
        $this->assertNull($notification->queued_at);
        $this->assertDatabaseCount('incidents', 1);
        Mail::assertNothingSent();

        Schema::rename('unavailable_jobs', 'jobs');
        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertNotNull($notification->refresh()->queued_at);
    }

    public function test_recipient_selection_never_broadcasts_to_other_roles_or_other_directors(): void
    {
        User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->create(['role' => User::ROLE_DIRECTOR, 'prenom' => 'Autre', 'nom' => 'Directeur']);
        $notification = $this->incident();
        (new SendDeclarationNotification($notification->id))->handle(app(DeclarationNotificationService::class));
        Mail::assertSentCount(1);
        Mail::assertSent(DeclarationRecorded::class, fn ($mail) => $mail->hasTo($this->director->email) && count($mail->to) === 1);
    }

    public function test_ambiguous_director_accounts_do_not_receive_incident_data(): void
    {
        $this->director->forceFill(['prenom' => 'Autre'])->save();
        User::factory()->create(['role' => User::ROLE_DIRECTOR]);
        $notification = $this->incident();
        $this->expectExceptionMessage('director_unavailable_or_ambiguous');
        try {
            (new SendDeclarationNotification($notification->id))->handle(app(DeclarationNotificationService::class));
        } finally {
            Mail::assertNothingSent();
        }
    }

    public function test_html_and_plain_emails_include_logo_details_and_protected_journal_link(): void
    {
        $notification = $this->incident(['description' => "<script>alert('test')</script>\nDeuxième ligne & détails."]);
        $mail = new DeclarationRecorded($notification);
        $html = $mail->render();
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('INCIDENT SIGNALÉ', $html);
        $this->assertStringContainsString('Rabie Benmahmoud', $html);
        $this->assertStringContainsString('09:30', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Deuxième ligne &amp; détails.', $html);
        $this->assertStringContainsString('Consulter le journal', $html);
        $this->assertStringContainsString('max-width:600px', $html);
        $text = view('emails.declaration-recorded-text', $mail->content()->with)->render();
        $this->assertStringContainsString('Description :', $text);
        $this->assertStringContainsString('/directeur?', $text);
        $this->assertStringNotContainsString('password', $html);

        $this->post(route('logout'));
        $this->get($mail->content()->with['journalUrl'])->assertRedirect(route('director.login'));
        $this->post(route('director.login.store'), ['email' => $this->director->email, 'password' => UserFactory::testPassword()])
            ->assertRedirect(route('director.dashboard'));
    }

    public function test_history_is_available_only_in_the_director_space(): void
    {
        $notification = $this->incident();
        $this->actingAs($this->director)->get(route('director.notifications'))->assertOk()
            ->assertSee('En attente')->assertSee('sans confirmation de lecture')->assertSee('Parking CGPark');
        (new SendDeclarationNotification($notification->id))->handle(app(DeclarationNotificationService::class));
        $this->get(route('director.notifications'))->assertSee('Envoyé')->assertSee($this->director->email);
        $this->actingAs($this->supervisor)->get(route('director.notifications'))->assertForbidden();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('director.notifications'))->assertForbidden();
        $this->post(route('logout'));
        $this->get(route('director.notifications'))->assertRedirect(route('director.login'));
    }

    public function test_real_mime_message_has_html_plain_text_and_an_embedded_logo(): void
    {
        $notification = $this->incident();
        $manager = new MailManager($this->app);
        $sent = $manager->mailer('array')->to($this->director->email)->send(new DeclarationRecorded($notification));
        $message = $sent->getSymfonySentMessage()->getOriginalMessage();
        $this->assertStringContainsString('cid:', $message->getHtmlBody());
        $this->assertStringContainsString('Intervention du superviseur.', $message->getTextBody());
        $this->assertStringContainsString('/directeur?', $message->getTextBody());
        $this->assertCount(1, $message->getAttachments());
        $this->assertSame('inline', $message->getAttachments()[0]->getDisposition());
        $this->assertSame('image', $message->getAttachments()[0]->getMediaType());
    }

    public function test_both_email_layouts_and_history_render_for_browser_review(): void
    {
        $notification = $this->incident(['description' => "Description complète sur plusieurs lignes.\n".str_repeat('Longue description de l’incident à afficher. ', 70)]);
        $incidentHtml = (new DeclarationRecorded($notification))->render();
        // Only alter the in-memory template input, never the declaration in the database.
        $notification->type = Incident::RAS;
        $rasHtml = (new DeclarationRecorded($notification))->render();
        $this->assertStringContainsString('RIEN À SIGNALER', $rasHtml);
        $this->assertStringNotContainsString('Description complète', $rasHtml);
        $historyHtml = $this->actingAs($this->director)->get(route('director.notifications'))->assertOk()->getContent();
        if (getenv('EXPORT_REVIEW_VIEWS') === '1') {
            if (! is_dir(base_path('.artifacts'))) {
                mkdir(base_path('.artifacts'), 0777, true);
            }
            foreach (['email-incident' => $incidentHtml, 'email-ras' => $rasHtml, 'notification-history' => $historyHtml] as $name => $html) {
                file_put_contents(base_path('.artifacts/'.$name.'.html'), $html);
            }
        }
    }
}
