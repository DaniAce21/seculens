<?php

namespace App\Models;

use Database\Factories\SecurityEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    use HasFactory;

    /**
     * Indica qué Factory debe utilizar este modelo.
     */
    protected static function newFactory()
    {
        return SecurityEventFactory::new();
    }

    /**
     * Campos que pueden asignarse mediante asignación masiva.
     */
    protected $fillable = [
        'event_type',
        'username',
        'source_ip',
        'user_agent',
        'result',
        'occurred_at',
        'metadata',
    ];

    /**
     * Convierte automáticamente determinados campos.
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}