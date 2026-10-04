<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorizacion sobre la bitacora de auditoria.
 *
 * La bitacora se restringe a ADMIN. Contiene el detalle de cada accion
 * sensible de todos los usuarios, por lo que exponerla a un ANALYST le
 * permitiria observar la operacion de sus colegas y de los
 * administradores.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isAdmin();
    }

    public function view(User $user): bool
    {
        return $user->role->isAdmin();
    }

    /**
     * La bitacora es append-only.
     *
     * No existe permiso de edicion ni de borrado, y el metodo se
     * declara explicitamente en false para que la ausencia de una
     * accion este documentada en lugar de depender de que no exista
     * ninguna ruta que la invoque.
     */
    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }
}
