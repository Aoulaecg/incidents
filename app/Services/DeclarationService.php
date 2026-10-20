<?php
namespace App\Services;

use App\Models\{Incident, IncidentAudit, Parking, User};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeclarationService
{
    public function store(User $actor, array $data, string $type): bool
    {
        $now = now('Africa/Casablanca');
        $date = $type === Incident::RAS ? $now->toDateString() : $data['date_incident'];
        try {
            return DB::transaction(function () use ($actor, $data, $type, $now, $date) {
                // A single parking lock serializes RAS/incident submissions across supervisors.
                $parking = Parking::whereKey($data['parking_id'])->lockForUpdate()->firstOrFail();
                $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->active && $user->role === User::ROLE_SUPERVISOR
                    && ! $user->must_change_password && $user->auth_version === $actor->auth_version, 403);
                $assignment = DB::table('parking_user')->where('user_id', $user->id)->where('parking_id', $parking->id)->lockForUpdate()->first();
                if (! $parking->active || ! $assignment) {
                    throw ValidationException::withMessages(['parking_id' => 'Vous n’êtes pas autorisé à déclarer pour ce parking.']);
                }
                $existing = Incident::where('user_id', $user->id)->where('submission_token', $data['submission_token'])->first();
                if ($existing) return $this->checkReplay($existing, $data, $type, $date);

                if ($type === Incident::RAS) {
                    if (Incident::where('parking_id', $parking->id)->whereDate('date_incident', $date)->where('type_declaration', Incident::INCIDENT)->exists()) {
                        throw ValidationException::withMessages(['parking_id' => 'Un incident a déjà été signalé pour ce parking aujourd’hui. Une confirmation « Rien à signaler » serait contradictoire.']);
                    }
                    if (Incident::where('ras_key', hash('sha256', $parking->id.'|'.$user->id.'|'.$date))->exists()) return false;
                }
                $incident = Incident::create([
                    'user_id' => $user->id, 'parking_id' => $parking->id, 'date_incident' => $date,
                    'heure_incident' => $type === Incident::INCIDENT ? $data['heure_incident'].':00' : null,
                    'description' => $type === Incident::INCIDENT ? $data['description'] : null,
                    'type_declaration' => $type, 'status' => 'active',
                    'validated_at' => $type === Incident::RAS ? $now : null,
                    'submission_token' => $data['submission_token'],
                    'ras_key' => $type === Incident::RAS ? hash('sha256', $parking->id.'|'.$user->id.'|'.$date) : null,
                ]);
                $this->audit($incident, $user, 'created', ['type' => $type]);
                if ($type === Incident::INCIDENT) {
                    $confirmations = Incident::where('parking_id', $parking->id)->whereDate('date_incident', $date)
                        ->where('type_declaration', Incident::RAS)->where('status', 'active')->get();
                    foreach ($confirmations as $confirmation) {
                        $confirmation->update(['status' => 'superseded', 'superseded_at' => $now, 'superseded_by_id' => $incident->id]);
                        $this->audit($confirmation, $user, 'ras_superseded', ['previous_status' => 'active', 'new_status' => 'superseded', 'incident_id' => $incident->id]);
                    }
                }
                app(DeclarationNotificationService::class)->record($incident, $user, $parking->nom);
                return true;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Incident::where('user_id', $actor->id)->where('submission_token', $data['submission_token'])->first();
            if ($existing) return $this->checkReplay($existing, $data, $type, $date);
            throw $exception;
        }
    }
    private function checkReplay(Incident $existing, array $data, string $type, string $date): bool
    {
        if ($existing->parking_id !== (int) $data['parking_id'] || $existing->type_declaration !== $type
            || $existing->date_incident->toDateString() !== $date
            || ($type === Incident::INCIDENT && ($existing->description !== $data['description'] || substr($existing->heure_incident, 0, 5) !== $data['heure_incident']))) {
            throw ValidationException::withMessages(['submission_token' => 'Ce formulaire a déjà été utilisé. Rechargez la page avant une nouvelle déclaration.']);
        }
        return false;
    }
    private function audit(Incident $incident, User $actor, string $event, array $details): void
    {
        IncidentAudit::create(['incident_id' => $incident->id, 'actor_user_id' => $actor->id, 'event' => $event, 'details' => $details, 'created_at' => now('Africa/Casablanca')]);
    }
}
