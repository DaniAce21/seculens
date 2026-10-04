<?php

namespace App\Models;

use App\Enums\AuditAction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro inmutable de acciones sensibles.
 *
 * El modelo es append-only: no expone mass assignment y no tiene rutas
 * de actualizacion ni de borrado. Solo el servicio de auditoria escribe
 * aqui. Un log que puede editarse no sirve para reconstruir una
 * intrusion, porque deja de ser evidencia.
 *
 * created_at no se actualiza nunca; updated_at no existe en la tabla por
 * ese motivo.
 *
 * @property int $id
 * @property int|null $user_id
 * @property AuditAction $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $user_role
 * @property string|null $ip_address
 * @property array<string, mixed>|null $context
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    /**
     * Constantes de configuracion de tiempo.
     *
     * public const UPDATED_AT = null desactiva la marca updated_at, que
     * en un registro inmutable no tendria sentido. TIMESTAMP de
     * CREATED_AT hace que Eloquent rellene created_at automaticamente.
     */
    public const UPDATED_AT = null;

    /**
     * Sin mass assignment: el servicio de auditoria asigna atributo por
     * atributo. Un endpoint que pudiera crear registros de bitacora
     * con datos arbitrarios permitiria falsificar la evidencia.
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Usuario que ejecuto la accion.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Entidad sobre la que se actuo.
     *
     * relacion polimorfica para no tener que crear una tabla puente por
     * cada tipo de entidad que se quiera auditar.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Filtra por accion.
     */
    public function scopeForAction(Builder $query, AuditAction $action): Builder
    {
        return $query->where('action', $action->value);
    }

    /**
     * Filtra por entidad afectada.
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    /**
     * Filtra por categoria funcional de la accion.
     */
    public function scopeInCategory(Builder $query, string $category): Builder
    {
        $actions = array_values(array_filter(
            AuditAction::cases(),
            fn (AuditAction $action) => $action->category() === $category,
        ));

        return $query->whereIn('action', array_map(
            fn (AuditAction $action) => $action->value,
            $actions,
        ));
    }
}
