<?php

namespace App\Models;

use App\Enums\EventType;
use Carbon\Carbon;
use Database\Factories\SecurityEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento de seguridad: un hecho ocurrido en el sistema.
 *
 * Los eventos son inmutables y nunca se editan. Corregir un evento
 * destruiria su valor como evidencia: si un registro dice que hubo un
 * intento fallido a las 03:12, reescribirlo a las 03:15 Falsearia el
 * analisis forense posterior.
 *
 * @property int $id
 * @property EventType $event_type
 * @property string|null $username
 * @property string|null $source_ip
 * @property string|null $user_agent
 * @property string|null $result
 * @property Carbon $occurred_at
 * @property array<string, mixed>|null $metadata
 */
class SecurityEvent extends Model
{
    use HasFactory;

    /**
     * Indica que Factory debe utilizar este modelo.
     */
    protected static function newFactory()
    {
        return SecurityEventFactory::new();
    }

    /**
     * Campos asignables de forma masiva.
     *
     * Se usan para la ingesta de eventos, no para la vista. Un evento lo
     * crea el sistema al ocurrir, no un usuario que edita un registro
     * existente.
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
     * Conversiones de tipo.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Filtra eventos de un tipo concreto.
     */
    public function scopeOfType(Builder $query, EventType $type): Builder
    {
        return $query->where('event_type', $type->value);
    }

    /**
     * Filtra eventos de varios tipos.
     */
    public function scopeOfTypes(Builder $query, array $types): Builder
    {
        return $query->whereIn('event_type', array_map(
            fn (EventType $type) => $type->value,
            $types,
        ));
    }

    /**
     * Filtra eventos originados en una IP exacta.
     *
     * Se compara por igualdad y no de forma parcial: la IP es un
     * identificador, y un coincidencia parcial mezclaria origenes
     * distintos en un panel de investigacion.
     */
    public function scopeFromIp(Builder $query, ?string $ip): Builder
    {
        if (blank($ip)) {
            return $query;
        }

        return $query->where('source_ip', $ip);
    }

    /**
     * Filtra por nombre de usuario.
     *
     * Busqueda parcial y sin distincion de mayusculas. El comodin se
     * escapa porque el usuario escribe el valor y podria incluir % o _.
     */
    public function scopeByUsername(Builder $query, ?string $username): Builder
    {
        if (blank($username)) {
            return $query;
        }

        return $query->where('username', 'ilike', '%'.self::escapeLike($username).'%');
    }

    /**
     * Filtra por resultado (SUCCESS, FAILURE).
     */
    public function scopeWithResult(Builder $query, ?string $result): Builder
    {
        if (blank($result)) {
            return $query;
        }

        return $query->where('result', $result);
    }

    /**
     * Filtra por rango temporal inclusivo.
     */
    public function scopeOccurredBetween(Builder $query, ?Carbon $from, ?Carbon $to): Builder
    {
        if ($from) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($to) {
            $query->where('occurred_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Escapa los comodines de LIKE en una entrada de usuario.
     *
     * Sin este escape, un usuario que escriba % obtendra la lista
     * completa de eventos en lugar de una busqueda sin resultados.
     * PostgreSQL usa la barra invertida como caracter de escape por
     * defecto en LIKE e ILIKE, que es justamente lo que este metodo
     * produce.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Resultado legible del evento.
     */
    public function resultLabel(): string
    {
        return match ($this->result) {
            'SUCCESS' => 'Exitoso',
            'FAILURE' => 'Fallido',
            default => $this->result ?? 'Desconocido',
        };
    }
}
