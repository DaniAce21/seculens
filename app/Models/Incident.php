<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\IncidentStatus;
use Carbon\Carbon;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Incidente: unidad de trabajo del analista sobre una o mas alertas.
 *
 * El incidente es mas que un contenedor de alertas. Lleva el titular
 * responsable, la prioridad, las notas de la investigacion y las marcas
 * temporales del ciclo de vida. Las alertas son los hallazgos; el
 * incidente es el trabajo para resolverlos.
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @property IncidentStatus $status
 * @property AlertSeverity $priority
 * @property int|null $assigned_to
 * @property Carbon $opened_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 */
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    /**
     * Campos asignables de forma masiva.
     *
     * status, assigned_to, opened_at, resolved_at y closed_at quedan
     * fuera a proposito: los gestiona IncidentService, que es el
     * unico que conoce las transiciones validas y las marcas de tiempo
     * correctas. Permitirlos desde el controller abriria la puerta a
     * estados invalidos y a fechas incoherentes.
     */
    protected $fillable = [
        'title',
        'description',
        'priority',
    ];

    /**
     * Conversiones de tipo.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'priority' => AlertSeverity::class,
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Indica que Factory debe utilizar este modelo.
     */
    protected static function newFactory(): IncidentFactory
    {
        return IncidentFactory::new();
    }

    /**
     * Analista responsable del incidente.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Notas de la investigacion, en orden cronologico.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(IncidentNote::class)->oldest();
    }

    /**
     * Alertas agrupadas bajo este incidente.
     */
    public function alerts(): BelongsToMany
    {
        return $this->belongsToMany(Alert::class, 'incident_alert')
            ->withPivot('linked_at')
            ->withTimestamps();
    }

    /**
     * Filtra incidentes que siguen abiertos al trabajo.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            IncidentStatus::OPEN->value,
            IncidentStatus::IN_PROGRESS->value,
            IncidentStatus::RESOLVED->value,
        ]);
    }

    /**
     * Filtra incidentes en un estado concreto.
     */
    public function scopeInStatus(Builder $query, IncidentStatus ...$statuses): Builder
    {
        return $query->whereIn(
            'status',
            array_map(fn (IncidentStatus $status) => $status->value, $statuses),
        );
    }

    /**
     * Filtra incidentes asignados a un usuario.
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Indica si el incidente sigue abierto al trabajo.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Verifica si puede pasar al estado indicado.
     */
    public function canTransitionTo(IncidentStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    /**
     * Dias transcurridos desde la apertura.
     *
     * Se usa en la interfaz para resaltar los incidentes ageing
     * Reutiliza Carbon internamente, por eso es un entero y no un
     * metodo.
     */
    public function ageInDays(): int
    {
        $end = $this->closed_at ?? now();

        return (int) $this->opened_at->diffInDays($end);
    }
}
