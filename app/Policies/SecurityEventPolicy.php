<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorizacion sobre eventos de seguridad.
 *
 * Los eventos son inmutables y de solo lectura por diseno: no existen
 * permisos de creacion, edicion ni borrado para el usuario final. La
 * escritura ocurre unicamente desde el sistema que registra la
 * actividad, nunca desde la interfaz.
 *
 * Un VIEWER puede consultarlos porque el Event Explorer es una de las
 * vistas principales del producto.
 */
class SecurityEventPolicy
{
    /**
     * Consultar el listado de eventos.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Ver el detalle de un evento, incluido su metadata.
     */
    public function view(User $user): bool
    {
        return true;
    }
}
