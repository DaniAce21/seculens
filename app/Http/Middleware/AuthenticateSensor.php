<?php

namespace App\Http\Middleware;

use App\Models\IdsSensor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica a un sensor IDS por token en la cabecera:
 *
 *     Authorization: Bearer ids_xxxxxxxx...
 *
 * Antes la API /api/v1/alerts estaba abierta: cualquiera podia leer las
 * alertas o inyectar alertas falsas. Ahora cada peticion debe traer el
 * token de un sensor registrado y no revocado.
 */
class AuthenticateSensor
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $sensor = $token ? IdsSensor::findActiveByToken($token) : null;

        if ($sensor === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token de sensor ausente, invalido o revocado.',
            ], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        // Se actualiza como mucho una vez por minuto para no escribir en
        // la base de datos en cada peticion de un sensor muy activo.
        if ($sensor->last_used_at === null || $sensor->last_used_at->lt(now()->subMinute())) {
            $sensor->forceFill(['last_used_at' => now()])->save();
        }

        // El controlador puede saber que sensor hizo la peticion.
        $request->attributes->set('ids_sensor', $sensor);

        return $next($request);
    }
}
