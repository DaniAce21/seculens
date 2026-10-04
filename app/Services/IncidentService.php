<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AuditAction;
use App\Enums\IncidentStatus;
use App\Models\Alert;
use App\Models\Incident;
use App\Models\IncidentNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Servicio del ciclo de vida de incidentes.
 *
 * Centraliza las transiciones de estado y las marcas temporales
 * derivadas. Un controller que cambiara el estado directamente podría
 * dejar un incidente en RESOLVED sin resolved_at, o cerrarlo saltando
 * la investigacion. Aqui esas reglas son imposibles de eludir.
 */
class IncidentService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Crea un incidente y, si se indican, le agrupa alertas.
     *
     * @param  array{title: string, description: string, priority: AlertSeverity}  $data
     * @param  array<int>  $alertIds
     */
    public function create(array $data, User $author, array $alertIds = []): Incident
    {
        $incident = DB::transaction(function () use ($data, $author, $alertIds) {
            $incident = new Incident;
            $incident->title = $data['title'];
            $incident->description = $data['description'];
            $incident->priority = $data['priority'];
            $incident->status = IncidentStatus::OPEN;
            $incident->opened_at = now();
            $incident->save();

            // El autor queda asignado por defecto: nadie abre un
            // incidente que no piensa atender.
            $incident->assigned_to = $author->id;
            $incident->save();

            $this->attachAlerts($incident, $alertIds);

            return $incident;
        });

        $this->audit->tryLog(AuditAction::INCIDENT_CREATED, $author, $incident, [
            'priority' => $incident->priority->value,
            'alerts_linked' => count($alertIds),
        ]);

        return $incident->load(['alerts', 'assignee']);
    }

    /**
     * Cambia el estado del incidente validando la transicion.
     *
     * @throws InvalidArgumentException si la transicion no es valida
     */
    public function changeStatus(Incident $incident, IncidentStatus $target, User $actor): Incident
    {
        if (! $incident->canTransitionTo($target)) {
            throw new InvalidArgumentException(
                "No se puede pasar de {$incident->status->value} a {$target->value}."
            );
        }

        $previous = $incident->status;

        $incident->status = $target;

        /*
         * Las marcas temporales se derivan del estado, no las envia el
         * cliente. Mantenerlas en un unico lugar evita que un
         * formulario pueda registrar un incidente resuelto sin fecha de
         * resolucion.
         */
        if ($target === IncidentStatus::RESOLVED) {
            $incident->resolved_at = now();
        }

        if ($target === IncidentStatus::CLOSED) {
            $incident->closed_at = now();
        }

        $incident->save();

        $this->audit->tryLog(AuditAction::INCIDENT_STATUS_CHANGED, $actor, $incident, [
            'from' => $previous->value,
            'to' => $target->value,
        ]);

        return $incident;
    }

    /**
     * Asigna el incidente a un analista.
     *
     * Aceptar null para desasignar: durante una investigation puede
     * ser necesario devolver el incidente a la cola.
     */
    public function assign(Incident $incident, ?User $assignee, User $actor): Incident
    {
        $previous = $incident->assigned_to;

        $incident->assigned_to = $assignee?->id;
        $incident->save();

        $this->audit->tryLog(AuditAction::INCIDENT_ASSIGNED, $actor, $incident, [
            'previous_assignee_id' => $previous,
            'new_assignee_id' => $assignee?->id,
        ]);

        return $incident;
    }

    /**
     * Agrega una nota a la cronologia del incidente.
     *
     * Guarda el estado del incidente en el momento de la nota para que
     * el historial permita responder en que punto de la investigacion
     * se escribio cada observacion.
     */
    public function addNote(Incident $incident, User $author, string $body): IncidentNote
    {
        $note = new IncidentNote;
        $note->incident_id = $incident->id;
        $note->user_id = $author->id;
        $note->body = $body;
        $note->incident_status_at_time = $incident->status;
        $note->save();

        $this->audit->tryLog(AuditAction::INCIDENT_NOTE_ADDED, $author, $incident, [
            'note_id' => $note->id,
            'characters' => mb_strlen($body),
        ]);

        return $note;
    }

    /**
     * Agrupa alertas a un incidente.
     *
     * syncWithoutDetaching evita duplicar vinculos y, a diferencia de
     * sync, no elimina las alertas que ya estaban asociadas.
     *
     * @param  array<int>  $alertIds
     */
    public function attachAlerts(Incident $incident, array $alertIds): void
    {
        if ($alertIds === []) {
            return;
        }

        $timestamp = now();

        $incident->alerts()->syncWithoutDetaching(
            collect($alertIds)->mapWithKeys(
                fn (int $alertId) => [$alertId => ['linked_at' => $timestamp]],
            )->all()
        );
    }

    /**
     * Desagrupa una alerta del incidente.
     *
     * Se permite quitar alertas de un incidente resuelto para que la
     * agrupacion refleje el analisis real y no el orden en que llegaron
     * los hallazgos.
     */
    public function detachAlert(Incident $incident, Alert $alert): void
    {
        $incident->alerts()->detach($alert->id);
    }

    /**
     * Actualiza los campos editables del incidente.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Incident $incident, array $data, User $actor): Incident
    {
        $changed = array_intersect_key($data, array_flip(['title', 'description', 'priority']));

        if ($changed !== []) {
            $incident->fill($changed);
            $incident->save();
        }

        $this->audit->tryLog(AuditAction::INCIDENT_UPDATED, $actor, $incident, [
            'fields' => array_keys($changed),
        ]);

        return $incident;
    }
}
