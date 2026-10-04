<?php

namespace App\Policies;

use App\Enums\AlertStatus;
use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\User;

/**
 * Autorizacion sobre alertas.
 *
 * La autorizacion se resuelve unicamente aqui y en el middleware. Las
 * vistas pueden ocultar botones por comodidad, pero esa ocultacion no
 * es una proteccion: quien llame directamente al endpoint recibe el
 * mismo rechazo.
 *
 * Matriz de permisos:
 *
 *                ADMIN   ANALYST   VIEWER
 *   ver           si      si        si
 *   cambiar estado si     si        no
 */
class AlertPolicy
{
    /**
     * Todos los usuarios autenticados pueden consultar alertas.
     *
     * Un VIEWER existe precisamente para leer el panel: negarle la
     * lectura lo convertiria en un rol inutil.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Ver el detalle de una alerta concreta.
     */
    public function view(User $user, Alert $alert): bool
    {
        return true;
    }

    /**
     * Cualquier rol con permiso de escritura puede cambiar el estado.
     */
    public function update(User $user, Alert $alert): bool
    {
        return $user->role->canWrite();
    }

    /**
     * Reconocer una alerta requiere permiso de escritura y que la
     * alerta aun no este resuelta.
     *
     * La condicion de estado se comprueba aqui y no solo en el
     * servicio: un controller que olvide validarla convertiria un
     * error de logica en una via para reabrir alertas cerradas.
     */
    public function acknowledge(User $user, Alert $alert): bool
    {
        return $user->role->canWrite() && $alert->status !== AlertStatus::RESOLVED;
    }

    /**
     * Resolver una alerta requiere permiso de escritura.
     */
    public function resolve(User $user, Alert $alert): bool
    {
        return $user->role->canWrite() && $alert->status !== AlertStatus::RESOLVED;
    }

    /**
     * Reabrir una alerta resuelta queda restringido a ADMIN.
     *
     * Reabrir significa afirmar que una evaluacion previa fue
     * equivocada. Que cualquiera con permiso de escritura pueda
     * contradecir un cierre debilitaria el valor de la bitacora de
     * alertas.
     */
    public function reopen(User $user, Alert $alert): bool
    {
        return $user->role->isAdmin() && $alert->status === AlertStatus::RESOLVED;
    }

    /**
     * Crear incidentes a partir de una alerta.
     */
    public function createIncident(User $user, Alert $alert): bool
    {
        return $user->role->canHandleIncidents();
    }

    /**
     * El usuario es administrador de la plataforma.
     */
    public function administer(User $user): bool
    {
        return $user->role === UserRole::ADMIN;
    }
}
