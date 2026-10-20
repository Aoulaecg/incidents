<?php

namespace App\Services;

use App\Models\User;
use App\Models\Parking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAccountService
{
    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $this->lockAdministrators($actor);
            if ($data['role'] === User::ROLE_SUPERVISOR) {
                if (empty($data['parking_ids'])) throw ValidationException::withMessages(['parking_ids' => 'Sélectionnez au moins un parking actif pour ce superviseur.']);
                $this->lockActiveParkings($data['parking_ids']);
            }
            $account = new User(collect($data)->only(['prenom', 'nom', 'email', 'password'])->all());
            $account->name = $account->full_name;
            $account->role = $data['role'];
            $account->active = (bool) $data['active'];
            $account->must_change_password = true;
            $account->save();
            if ($account->role === User::ROLE_SUPERVISOR) $account->parkings()->sync($data['parking_ids']);

            return $account;
        }, 3);
    }

    public function update(User $actor, User $target, array $attributes): User
    {
        return DB::transaction(function () use ($actor, $target, $attributes) {
            // Lock administrators in a consistent order to serialize concurrent demotions.
            $administrators = $this->lockAdministrators($actor);
            $hasAssignments = array_key_exists('parking_ids', $attributes) && ($attributes['role'] ?? $target->role) === User::ROLE_SUPERVISOR;
            if ($hasAssignments) $this->lockActiveParkings($attributes['parking_ids'], $target);
            $account = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $active = (bool) ($attributes['active'] ?? $account->active);
            $role = $attributes['role'] ?? $account->role;

            if ($actor->id === $account->id && ! $active) {
                throw ValidationException::withMessages(['active' => 'Vous ne pouvez pas désactiver votre propre compte.']);
            }

            if ($account->isAdministrator() && (! $active || $role !== User::ROLE_ADMIN)
                && $administrators->where('active', true)->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Le dernier administrateur actif doit conserver ses droits et son accès.']);
            }

            $account->fill(collect($attributes)->only(['prenom', 'nom', 'email'])->all());
            $account->role = $role;
            $account->active = $active;
            $account->name = $account->full_name;

            if ($account->isDirty('active') && ! $active) {
                $account->auth_version++;
                $account->remember_token = Str::random(60);
                $this->deleteSessions($account);
            }

            $account->save();
            if ($hasAssignments && $role === User::ROLE_SUPERVISOR) $this->syncKeepingInactive($account, $attributes['parking_ids']);

            return $account;
        }, 3);
    }

    public function resetPassword(User $actor, User $target, string $password): void
    {
        DB::transaction(function () use ($actor, $target, $password) {
            $this->lockAdministrators($actor);
            $account = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $account->password = $password;
            $account->must_change_password = true;
            $account->auth_version++;
            $account->remember_token = Str::random(60);
            $account->save();
            $this->deleteSessions($account);
        }, 3);
    }

    private function lockAdministrators(User $actor): \Illuminate\Database\Eloquent\Collection
    {
        $administrators = User::where('role', User::ROLE_ADMIN)->orderBy('id')->lockForUpdate()->get();
        abort_unless($administrators->contains(fn (User $admin) => $admin->id === $actor->id && $admin->active), 403);

        return $administrators;
    }

    public function assignParkings(User $actor, User $target, array $ids): void
    {
        DB::transaction(function () use ($actor, $target, $ids) {
            $this->lockAdministrators($actor);
            $this->lockActiveParkings($ids, $target);
            $user = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($user->role !== User::ROLE_SUPERVISOR) {
                throw ValidationException::withMessages(['parking_ids' => 'Seuls les superviseurs peuvent recevoir des affectations.']);
            }
            $this->syncKeepingInactive($user, $ids);
        }, 3);
    }

    private function lockActiveParkings(array $ids, ?User $target = null): void
    {
        $existing = $target ? $target->parkings()->pluck('parkings.id')->all() : [];
        // Lock parking rows before the supervisor, as declaration writes do.
        $parkings = Parking::whereIn('id', array_unique(array_merge($ids, $existing)))->orderBy('id')->lockForUpdate()->get();
        foreach ($ids as $id) {
            if (! $parkings->contains(fn ($parking) => $parking->id == $id && $parking->active)) {
                throw ValidationException::withMessages(['parking_ids' => 'Sélectionnez uniquement des parkings actifs et existants.']);
            }
        }
    }

    private function syncKeepingInactive(User $account, array $ids): void
    {
        $inactive = $account->parkings()->where('parkings.active', false)->pluck('parkings.id')->all();
        $account->parkings()->sync(array_unique(array_merge($ids, $inactive)));
    }

    public function deleteSessions(User $account, ?string $exceptId = null): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $account->id)
                ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
                ->delete();
        }
    }
}
