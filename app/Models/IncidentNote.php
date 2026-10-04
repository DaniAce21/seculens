<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota escrita durante la investigacion de un incidente.
 *
 * Las notas son append-only: no existe una operacion de edicion. En una
 * investigacion de seguridad, corregir una anotacion pasada solo puede
 * ocurrir anadiendo una nota nueva que la matice. Editar en silencio
 * destruiria la fiabilidad de la cronologia, que es justo el valor de
 * este registro.
 *
 * @property int $id
 * @property int $incident_id
 * @property int|null $user_id
 * @property string $body
 * @property IncidentStatus|null $incident_status_at_time
 */
class IncidentNote extends Model
{
    use HasFactory;

    /**
     * Notas son inmutables: solo se crean y se eliminan en cascada con
     * su incidente. No se expone mass assignment para el cuerpo porque
     * la nota se escribe desde un endpoint dedicado y validado.
     */
    protected $fillable = [
        'body',
        'incident_status_at_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'incident_status_at_time' => IncidentStatus::class,
        ];
    }

    /**
     * Incidente al que pertenece la nota.
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * Autor de la nota.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
