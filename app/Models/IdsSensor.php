<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Sensor IDS con acceso a la API.
 *
 * @property int $id
 * @property string $name
 * @property string $token_hash
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon|null $revoked_at
 */
class IdsSensor extends Model
{
    protected $fillable = ['name'];

    // El hash nunca se devuelve en respuestas JSON.
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(IdsAlert::class, 'sensor_id');
    }

    /**
     * Crea un sensor y devuelve [sensor, token en claro].
     * El token en claro no se guarda en ningun sitio: hay que copiarlo ya.
     *
     * @return array{0: self, 1: string}
     */
    public static function register(string $name): array
    {
        // Prefijo "ids_" para reconocer el token si aparece en un log o repositorio.
        $plain = 'ids_'.Str::random(48);

        $sensor = new self(['name' => $name]);
        $sensor->token_hash = self::hash($plain);
        $sensor->save();

        return [$sensor, $plain];
    }

    /**
     * Busca el sensor activo (no revocado) al que pertenece un token.
     */
    public static function findActiveByToken(string $plain): ?self
    {
        return self::query()
            ->where('token_hash', self::hash($plain))
            ->whereNull('revoked_at')
            ->first();
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
