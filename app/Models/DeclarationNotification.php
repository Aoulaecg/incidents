<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeclarationNotification extends Model
{
    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array', 'attempts' => 'integer', 'queued_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::SENT => 'Envoyé',
            self::FAILED => 'Échec',
            default => 'En attente',
        };
    }
}
