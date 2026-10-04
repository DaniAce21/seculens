<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorizacion sobre usuarios y roles.
 *
 * Toda la gestion de usuarios queda restringida a ADMIN. Un ANALYST
 * investiga incidentes y administra alertas, pero no decide quien puede
 * entrar a la plataforma.
 *
 * Matriz de permisos:
 *
 *                     ADMIN   ANALYST   VIEWER
 *   listar usuarios   si      no        no
 *   ver usuario       si      no        no
 *   crear usuario     si      no        no
 *   editar usuario    si      no        no
 *   cambiar rol       si      no        no
 *   ver documentacion si      no        no
 */
class UserPolicy
{
    /**
     * Listar usuarios de la plataforma.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->role->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->role->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->role->isAdmin();
    }

    /**
     * Impedir que un administrador se degrade a si mismo.
     *
     * Sin esta proteccion, un ADMIN podia quitarse su propio rol y
     * dejar la plataforma sin ninguna cuenta capaz de administrar
     * usuarios, sin mecanismo de recuperacion.
     */
    public function changeRole(User $user, User $target): bool
    {
        if (! $user->role->isAdmin()) {
            return false;
        }

        // Impedir que un administrador se degrade a si mismo.
        if ($target->is($user) && $user->role->isAdmin()) {
            return false;
        }

        return true;
    }

    /**
     * Ver la documentacion de la API.
     *
     * Se restringe a ADMIN porque el esquema expone la superficie
     * completa de la aplicacion, incluidos endpoints de administracion.
     */
    public function viewApiDocumentation(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Consultar la bitacora de auditoria.
     */
    public function viewAuditLog(User $user): bool
    {
        return $user->isAdmin();
    }
}
