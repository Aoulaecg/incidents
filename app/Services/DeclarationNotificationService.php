<?php

namespace App\Services;

use App\Jobs\SendDeclarationNotification;
use App\Models\DeclarationNotification;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DeclarationNotificationService
{
    // Called inside the declaration transaction: the outbox is rolled back with it.
    public function record(Incident $incident, User $supervisor, string $parkingName): void
    {
        $notification = DeclarationNotification::create([
            'incident_id' => $incident->id,
            'type' => $incident->type_declaration,
            'details' => [
                'parking' => $parkingName,
                'supervisor' => $supervisor->full_name,
                'date' => $incident->date_incident->format('d/m/Y'),
                'time' => $incident->type_declaration === Incident::INCIDENT ? $incident->displayTime() : null,
                'description' => $incident->description,
            ],
        ]);

        DB::afterCommit(fn () => $this->enqueue($notification->id));
    }

   public function directors()
{
    $directors = User::where('role', User::ROLE_DIRECTOR)
        ->where('active', true)
        ->get();

    if ($directors->isEmpty()) {
        throw new RuntimeException('director_unavailable');
    }

    foreach ($directors as $director) {
        if (! filter_var($director->email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('director_invalid_email');
        }
    }

    return $directors;
}

    public function enqueue(int $id, bool $retryFailed = false): bool
    {
        try {
            return DB::transaction(function () use ($id, $retryFailed) {
                $notification = DeclarationNotification::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($notification->status === DeclarationNotification::SENT
                    || ($notification->status === DeclarationNotification::FAILED && ! $retryFailed)
                    || ($notification->queued_at && $notification->queued_at->gt(now()->subMinutes(15)))) {
                    return false;
                }

                // Always use a real queue, even if the application's default is sync.
                // The database queue must use the same DB as the outbox.
                Bus::dispatch((new SendDeclarationNotification($id))->onConnection('database')->beforeCommit());
                $notification->update(['status' => DeclarationNotification::PENDING, 'queued_at' => now('Africa/Casablanca')]);

                return true;
            });
        } catch (Throwable $exception) {
            // Never expose a queue/SMTP failure to the supervisor. The scheduler recovers the outbox.
            Log::error('declaration_notification_queue_failed', ['notification_id' => $id, 'error_type' => $exception::class]);

            return false;
        }
    }
}
