<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe el acceso a usuarios autenticados con uno de los roles
 * indicados.
 *
 * El middleware de rol protege rutas completas, como la gestion de
 * usuarios. Para acciones sobre una entidad concreta se usan las
 * policies: comprobar el rol aqui y el permiso alla divide la regla de
 * autorizacion en dos lugares, y basta con que uno se quede
 * desactualizado para abrir un hueco.
 *
 * Un VIEWER que llega a una ruta restringida recibe un 403, no un
 * redirect al login: ya esta autenticado, y admitir un 401 induciria a
 * la aplicacion a invalidar una sesion valida.
 */
class EnsureUserHasRole
{
    /**
     * Ejecuta el middleware.
     *
     * @param  Closure(Request): Response  $next
     * @param  string  ...$roles  Roles permitidos, en valor de enum
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Se requiere autenticacion.');
        }

        $allowed = array_map(
            fn (UserRole $role) => $role->value,
            array_filter($roles, fn (string $role) => UserRole::tryFrom($role) !== null),
        );

        if (! in_array($user->role->value, $allowed, true)) {
            abort(403, 'No cuenta con permisos para acceder a esta seccion.');
        }

        return $next($request);
    }
}
