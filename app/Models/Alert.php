<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use Carbon\Carbon;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Alerta de seguridad generada por una regla de deteccion.
 *
 * Una alerta no es un evento: es la agrupacion de varios eventos que
 * comparten una misma causa raiz. El evento dice "algo ocurrio"; la
 * alerta dice "varios eventos encajan en un patron que requiere
 * atencion".
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @property AlertSeverity $severity
 * @property AlertStatus $status
 * @property string $source_ip
 * @property string $detection_rule
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property int $event_count
 */
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    /**
     * La asignacion masiva se limita a campos que el usuario puede
     * realmente establecer desde la aplicacion.
     *
     * Quedan fuera detection_rule, severity, source_ip y los campos de
     * ventana temporal porque los calcula el servicio de deteccion a
     * partir de evidencia observada. Permitir que el cliente los envíe
     * permitiria falsificar el origen de una alerta.
     */
    protected $fillable = [
        'title',
        'description',
        'status',
    ];

    /**
     * Serializacion expuesta por la API.
     *
     * Al exponer la IP de origen asumimos que los consumidores son
     * usuarios autenticados con permiso de lectura; la autorizacion se
     * aplica en el controller, no aqui.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'status' => AlertStatus::class,
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'event_count' => 'integer',
        ];
    }

    /**
     * Indica que Factory debe utilizar este modelo.
     */
    protected static function newFactory(): AlertFactory
    {
        return AlertFactory::new();
    }

    /**
     * Incidentes que han agrupado esta alerta.
     *
     * Una alerta puede pertenecer a varios incidentes a lo largo del
     * tiempo: un ataque recurrente se reinvestiga cada vez, y cada
     * investigacion es un incidente distinto.
     */
    public function incidents(): BelongsToMany
    {
        return $this->belongsToMany(Incident::class, 'incident_alert')
            ->withPivot('linked_at')
            ->withTimestamps();
    }

    /**
     * Filtra las alertas que siguen abiertas o en revision.
     *
     * El filtro se escribe con los valores del enum en lugar de texto
     * literal para que un cambio de dominio no pueda desincronizarse de
     * las consultas: si el enum cambia, la referencia deja de compilar.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AlertStatus::OPEN->value,
            AlertStatus::ACKNOWLEDGED->value,
        ]);
    }

    /**
     * Filtra alertas cuya severidad es igual o superior a la indicada.
     */
    public function scopeWithSeverityAtLeast(Builder $query, AlertSeverity $minimum): Builder
    {
        /*
         * El enum se persiste como texto, asi que la comparacion se
         * resuelve con un CASE. Es la unica forma de comparar
         * severidades de forma ordinal sin duplicar la tabla de
         * niveles en SQL.
         */
        $levels = collect(AlertSeverity::cases())
            ->filter(fn (AlertSeverity $severity) => $severity->isAtLeast($minimum))
            ->map(fn (AlertSeverity $severity) => $severity->value)
            ->all();

        return $query->whereIn('severity', $levels);
    }

    /**
     * Eventos de seguridad que sustentan esta alerta.
     */
    public function securityEvents(): BelongsToMany
    {
        /*
         * No se declara withTimestamps() a proposito: la tabla pivote
         * usa linked_at en lugar de created_at y updated_at. Ese campo
         * registra cuando se asocio el evento a la alerta, que es lo
         * relevante para una auditoria. Los timestamps genericos se
         * actualizarian en cada sincronizacion y perderian el momento
         * real de la vinculacion.
         */
        return $this->belongsToMany(SecurityEvent::class, 'alert_security_event')
            ->withPivot('linked_at');
    }

    /**
     * Indica si la alerta sigue requiriendo atencion.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Indica si la alerta tiene severidad alta o critica.
     *
     * El dashboard usa este metodo para el contador de alertas graves;
     * mantener la definicion aqui evita que cada vista replique la
     * comparacion de severidades.
     */
    public function isHighSeverity(): bool
    {
        return $this->severity->isAtLeast(AlertSeverity::HIGH);
    }

    /**
     * Verifica si la alerta puede pasar al estado indicado.
     */
    public function canTransitionTo(AlertStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }
}
