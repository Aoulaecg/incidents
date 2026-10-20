<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parking extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Parking $parking) {
            if (! $parking->exists || $parking->isDirty('nom')) {
                $parking->nom = \App\Support\ParkingName::clean($parking->nom);
            }
            $parking->nom_key = \App\Support\ParkingName::key($parking->nom);
        });
    }
    protected $fillable = ['nom', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
    public function users(): BelongsToMany { return $this->belongsToMany(User::class)->withTimestamps(); }
    public function incidents(): HasMany { return $this->hasMany(Incident::class); }
}
