<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IP incluida en la lista de vigilancia.
 *
 * @property int $id
 * @property string $ip
 * @property string $level  'watch' | 'blocked'
 * @property string|null $reason
 * @property int|null $created_by
 */
class WatchedIp extends Model
{
    public const LEVELS = [
        'watch' => 'En vigilancia',
        'blocked' => 'Bloqueada',
    ];

    protected $fillable = ['ip', 'level', 'reason'];

    /**
     * Cache en memoria durante la peticion: el componente <x-ip> se pinta
     * una vez por fila y no debe lanzar una consulta cada vez.
     *
     * @var array<string, string>|null  ip => level
     */
    private static ?array $lookup = null;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Devuelve el nivel de vigilancia de una IP, o null si no esta en la lista.
     */
    public static function levelFor(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        self::$lookup ??= self::query()->pluck('level', 'ip')->all();

        return self::$lookup[$ip] ?? null;
    }

    /**
     * Invalida la cache tras anadir o quitar una IP.
     */
    protected static function booted(): void
    {
        $flush = fn () => self::$lookup = null;

        static::saved($flush);
        static::deleted($flush);
    }
}
