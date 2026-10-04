<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Registro de acciones sensibles en la bitacora de auditoria.
 *
 * Es el unico punto de entrada a la tabla audit_logs. Que exista un solo
 * servicio significa que el filtrado de datos sensibles y la obtencion
 * del contexto de la peticion estan garantizados en todas las rutas de
 * escritura, en lugar de depender de que cada llamada a AuditLog::create
 * recuerde hacerlo.
 */
class AuditService
{
    /**
     * Claves que nunca deben persistirse en el contexto.
     *
     * Se filtran de forma recursiva y se sustituyen por una marca en
     * lugar de eliminarse: saber que el campo se envio sigue siendo
     * informacion de auditoria, aunque su valor no lo sea. En el caso
     * de 'password', un cambio real se detecta justamente porque la
     * marca aparece.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'remember_token',
        'api_token',
        'secret',
        'api_key',
        'authorization',
        '_token',
    ];

    /**
     * Longitud maxima del user agent registrado.
     *
     * Un user agent es una cadena controlada por el cliente y puede
     * medir kilometers. Sin este limite, una sola peticion manipulada
     * inflaria la tabla de auditoria.
     */
    private const USER_AGENT_MAX_LENGTH = 512;

    /**
     * Registra una accion sensible.
     *
     * @param  AuditAction  $action  Accion realizada
     * @param  User|null  $user  Actor. Null cuando no hay sesion, como en un intento fallido.
     * @param  object|null  $subject  Entidad afectada (Alert, Incident, User)
     * @param  array<string, mixed>  $context  Contexto adicional
     */
    public function log(
        AuditAction $action,
        ?User $user = null,
        ?object $subject = null,
        array $context = [],
    ): AuditLog {
        $request = request();

        $auditLog = new AuditLog;
        $auditLog->user_id = $user?->id;
        $auditLog->action = $action->value;
        $auditLog->user_role = $user?->role->value;
        $auditLog->ip_address = $request?->ip();
        $auditLog->user_agent = $this->truncateUserAgent($request?->userAgent());
        $auditLog->context = $this->sanitizeContext($context);

        if ($subject !== null && method_exists($subject, 'getMorphClass')) {
            $auditLog->subject_type = $subject->getMorphClass();
            $auditLog->subject_id = $subject->getKey();
        }

        $auditLog->save();

        return $auditLog;
    }

    /**
     * Elimina claves sensibles del contexto de forma recursiva.
     *
     * Un contexto de auditoria no es el lugar adecuado para una
     * contrasena, aunque venga hasheada: guardarla duplica el material
     * sensible y amplia la superficie de una filtracion de la base de
     * datos.
     *
     * @param  array<mixed>  $context
     * @return array<mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $clean[$key] = '[redactado]';

                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitizeContext($value) : $value;
        }

        return $clean;
    }

    /**
     * Limita la longitud del user agent registrado.
     */
    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return mb_substr($userAgent, 0, self::USER_AGENT_MAX_LENGTH);
    }

    /**
     * Registra una accion sin interrumpir la operacion en curso.
     *
     * Un error al escribir la bitacora no debe tumbar la accion del
     * usuario que ya se esta procesando. Se conserva el fallo en el log
     * de aplicacion y se continua.
     */
    public function tryLog(
        AuditAction $action,
        ?User $user = null,
        ?object $subject = null,
        array $context = [],
    ): ?AuditLog {
        try {
            return $this->log($action, $user, $subject, $context);
        } catch (\Throwable $exception) {
            Log::error('SecuLens: no se pudo escribir el registro de auditoria.', [
                'action' => $action->value,
                'user_id' => $user?->id,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
