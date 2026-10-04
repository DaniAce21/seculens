<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para modo single-user.
 *
 * Como la aplicación está pensada para ser usada por una sola persona,
 * se autentica automáticamente con un usuario administrador por defecto
 * si no existe una sesión activa. Esto elimina la necesidad de login
 * manteniendo intacta toda la lógica de autorización, policies y auditoría.
 */
class AutoLogin
{
    /**
     * Email del administrador por defecto del modo single-user.
     */
    private const ADMIN_EMAIL = 'admin@seculens.local';

    public function handle(Request $request, Closure $next): Response
    {
        // La API la usan sensores con su propio token (AuthenticateSensor):
        // nunca debe recibir una sesion de administrador automatica.
        if ($request->is('api/*')) {
            return $next($request);
        }

        if (! Auth::check()) {
            $user = User::where('email', self::ADMIN_EMAIL)->first();

            if ($user === null) {
                /*
                 * forceCreate en lugar de create: el modelo User no
                 * declara 'role' (ni 'email_verified_at') como asignables
                 * en masa, asi que create() los descartaba en silencio y
                 * el usuario quedaba con el rol por defecto de la base
                 * (VIEWER). Resultado: 403 en Usuarios/API Docs y menu de
                 * administracion oculto.
                 */
                $user = User::forceCreate([
                    'name' => 'Administrador',
                    'email' => self::ADMIN_EMAIL,
                    'password' => Hash::make('SecuLens2026!'),
                    'role' => UserRole::ADMIN,
                    'email_verified_at' => now(),
                ]);
            } elseif ($user->role !== UserRole::ADMIN) {
                // Repara instalaciones creadas con el error anterior.
                $user->forceFill(['role' => UserRole::ADMIN])->save();
            }

            Auth::login($user);
        }

        return $next($request);
    }
}
