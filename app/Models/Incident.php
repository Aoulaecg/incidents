<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    public const INCIDENT = 'incident';
    public const RAS = 'rien_a_signaler';
    protected $guarded = ['id'];
    protected $hidden = ['submission_token', 'ras_key'];
    protected function casts(): array
    {
        return ['date_incident' => 'date', 'validated_at' => 'datetime', 'superseded_at' => 'datetime'];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function parking(): BelongsTo { return $this->belongsTo(Parking::class); }
    public function audits(): HasMany { return $this->hasMany(IncidentAudit::class); }
    public function displayTime(): string
    {
        return $this->type_declaration === self::RAS ? $this->validated_at?->format('H:i') ?? '—' : substr($this->heure_incident ?? '', 0, 5);
    }
}
