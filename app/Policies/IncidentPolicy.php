<?php

namespace App\Policies;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;

/**
 * Autorizacion sobre incidentes.
 *
 * Matriz de permisos:
 *
 *                     ADMIN   ANALYST   VIEWER
 *   ver                si      si        si
 *   crear              si      si        no
 *   editar             si      si        no
 *   cambiar estado     si      si        no
 *   agregar nota       si      si        no
 *   asignar            si      no        no
 *   cerrar             si      si        no
 *
 * La asignacion se restringe a ADMIN de forma deliberada: asignar es
 * una decision de gestion sobre carga de trabajo, no una accion tecnica
 * del analista. Si cualquiera pudiera reasignar, un analista podria
 * transferirse a si mismo el trabajo que no desea atender.
 */
class IncidentPolicy
{
    /**
     * Todos los usuarios autenticados pueden consultar incidentes.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Incident $incident): bool
    {
        return true;
    }

    /**
     * Crear un incidente nuevo requiere perfil de analista o admin.
     */
    public function create(User $user): bool
    {
        return $user->role->canHandleIncidents();
    }

    /**
     * Editar los campos descriptivos del incidente.
     *
     * Un VIEWER no edita, aunque tenga permiso de escritura sobre otras
     * entidades: el permiso se concede por capacidad, no de forma
     * acumulativa entre modulos.
     */
    public function update(User $user, Incident $incident): bool
    {
        return $user->role->canHandleIncidents();
    }

    /**
     * Cambiar el estado del incidente.
     *
     * Un incidente cerrado es terminal y solo ADMIN puede reabrirlo.
     */
    public function changeStatus(User $user, Incident $incident): bool
    {
        if (! $user->role->canHandleIncidents()) {
            return false;
        }

        if ($incident->status === IncidentStatus::CLOSED) {
            return $user->role->isAdmin();
        }

        return true;
    }

    /**
     * Agregar notas a la cronologia de la investigacion.
     */
    public function addNote(User $user, Incident $incident): bool
    {
        return $user->role->canHandleIncidents();
    }

    /**
     * Asignar el incidente a un analista.
     */
    public function assign(User $user, Incident $incident): bool
    {
        return $user->role->isAdmin();
    }

    /**
     * Vincular o desvincular alertas del incidente.
     */
    public function manageAlerts(User $user, Incident $incident): bool
    {
        return $user->role->canHandleIncidents();
    }

    /**
     * Agrupar una alerta concreta a un incidente.
     */
    public function attachAlert(User $user, Incident $incident): bool
    {
        return $user->role->canHandleIncidents();
    }
}
