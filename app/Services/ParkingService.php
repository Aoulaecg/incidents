<?php

namespace App\Services;

use App\Models\{Parking, User};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParkingService
{
    public function save(User $actor, array $data, ?Parking $target = null): Parking
    {
        try {
            return DB::transaction(function () use ($actor, $data, $target) {
                $admins = User::where('role', User::ROLE_ADMIN)->orderBy('id')->lockForUpdate()->get();
                abort_unless($admins->contains(fn ($admin) => $admin->id === $actor->id && $admin->active), 403);
                $parking = $target ? Parking::whereKey($target->id)->lockForUpdate()->firstOrFail() : new Parking;
                $parking->fill(collect($data)->only(['nom', 'active'])->all())->save();
                return $parking;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['nom' => 'Un parking portant ce nom existe déjà.']);
        }
    }
}
