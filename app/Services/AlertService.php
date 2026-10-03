<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\SecurityEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Servicio de creacion de alertas a partir de reglas de deteccion.
 *
 * Responsabilidades:
 * - Recibir el resultado de una regla de deteccion
 * - Persistir una alerta solo cuando la regla se cumple
 * - Vincular los eventos que sustentan la deteccion
 * - Evitar alertas duplicadas del mismo ataque en curso
 *
 * Este servicio no decide si un evento es sospechoso: esa decision
 * pertenece a DetectionService. Aqui solo se traduce una deteccion
 * confirmada en un registro persistente.
 */
class AlertService
{
    /**
     * Crea o actualiza una alerta a partir de un resultado de deteccion.
     *
     * Devuelve null cuando la regla no se cumplio, de modo que el
     * llamador pueda encadenar la evaluacion de varias reglas sin
     * distinguir el caso negativo.
     *
     * @param  array{detected: bool, rule: string, severity: ?AlertSeverity, source_ip: string, event_count: int, first_seen_at: ?Carbon, last_seen_at: ?Carbon}  $detection
     */
    public function createFromDetection(array $detection): ?Alert
    {
        if (! $detection['detected']) {
            return null;
        }

        /*
         * Toda la operacion ocurre dentro de una transaccion. Si el
         * vinculo de evidencia falla, la alerta tampoco queda
         * persistida: nunca debe existir una alerta sin respaldo.
         */
        return DB::transaction(function () use ($detection) {
            [$alert] = $this->findOrCreateActiveAlert($detection);

            /*
             * Los eventos se vinculan tanto en alertas nuevas como
             * reutilizadas. Omitirlo en el segundo caso dejaria una
             * alerta cuyo event_count announce mas evidencia de la
             * realmente enlazada, y el detalle mostraria una
             * discrepancia entre el conteo y los eventos visibles.
             */
            $this->attachSupportingEvents($alert, $detection);

            $this->escalateSeverity($alert, $detection);

            return $alert->refresh();
        });
    }

    /**
     * Busca una alerta activa equivalente o crea una nueva.
     *
     * Reutilizar la alerta activa en lugar de crear otra mantiene una
     * sola entrada por ataque en curso. Sin esto, un atacante que
     * reintente durante diez minutos generaria diez alertas identicas y
     * el tablero mostraria un volumen de alertas que no corresponde con
     * la cantidad de incidentes reales.
     *
     * lockForUpdate bloquea la fila durante la transaccion para que dos
     * peticiones concurrentes no puedan leer simultaneamente la
     * ausencia de alerta y crear dos. El indice unico parcial sigue
     * siendo la garantia final: este bloqueo solo evita el trabajo
     * duplicado, no la violacion de la restriccion.
     *
     * @param  array{detected: bool, rule: string, severity: ?AlertSeverity, source_ip: string, event_count: int, first_seen_at: ?Carbon, last_seen_at: ?Carbon}  $detection
     * @return array{0: Alert, 1: bool} La alerta y si acaba de crearse
     */
    private function findOrCreateActiveAlert(array $detection): array
    {
        $alert = Alert::query()
            ->where('detection_rule', $detection['rule'])
            ->where('source_ip', $detection['source_ip'])
            ->active()
            ->lockForUpdate()
            ->first();

        if ($alert) {
            return [$alert, false];
        }

        return [$this->createAlert($detection), true];
    }

    /**
     * Crea una alerta nueva a partir de una deteccion confirmada.
     *
     * @param  array{detected: bool, rule: string, severity: ?AlertSeverity, source_ip: string, event_count: int, first_seen_at: ?Carbon, last_seen_at: ?Carbon}  $detection
     */
    private function createAlert(array $detection): Alert
    {
        $severity = $detection['severity'] ?? AlertSeverity::MEDIUM;

        /*
         * Los atributos se asignan uno a uno en lugar de usar create().
         *
         * Alert::$fillable solo admite title, description y status, de
         * forma deliberada: la severidad, la IP de origen, la regla y
         * la ventana temporal los calcula el motor de deteccion y no
         * deben poder llegar desde una peticion del cliente. Usar
         * create() con el conjunto completo descartaria en silencio esos
         * campos y persistiria una alerta sin evidencia.
         */
        $alert = new Alert;
        $alert->title = $this->buildTitle($detection['rule'], $detection['source_ip']);
        $alert->description = $this->buildDescription($detection);
        $alert->severity = $severity;
        $alert->status = AlertStatus::OPEN;
        $alert->source_ip = $detection['source_ip'];
        $alert->detection_rule = $detection['rule'];
        $alert->first_seen_at = $detection['first_seen_at'] ?? now();
        $alert->last_seen_at = $detection['last_seen_at'] ?? now();
        $alert->event_count = $detection['event_count'];
        $alert->save();

        return $alert;
    }

    /**
     * Asocia los eventos que cumplieron la regla con la alerta.
     *
     * @param  array{detected: bool, rule: string, severity: ?AlertSeverity, source_ip: string, event_count: int, first_seen_at: ?Carbon, last_seen_at: ?Carbon}  $detection
     */
    private function attachSupportingEvents(Alert $alert, array $detection): void
    {
        $firstSeen = $detection['first_seen_at'] ?? now()->subMinutes(5);
        $lastSeen = $detection['last_seen_at'] ?? now();

        $eventIds = SecurityEvent::query()
            ->where('event_type', 'LOGIN_FAILED')
            ->where('source_ip', $detection['source_ip'])
            ->whereBetween('occurred_at', [$firstSeen, $lastSeen])
            ->pluck('id');

        if ($eventIds->isEmpty()) {
            /*
             * Una alerta sin eventos asociados impediria reconstruir el
             * hallazgo. Preferimos registrar el incidente en el log y
             * abortar que persistir evidencia incompleta.
             */
            Log::warning('SecuLens: deteccion confirmada sin eventos que la respalden.', [
                'rule' => $detection['rule'],
                'source_ip' => $detection['source_ip'],
                'detected_count' => $detection['event_count'],
            ]);

            throw new RuntimeException(
                'La deteccion no tiene eventos de seguridad asociados que la respalden.'
            );
        }

        /*
         * syncWithoutDetaching evita duplicar vinculos si el mismo evento
         * ya estaba asociado. La clave primaria compuesta de la tabla
         * pivote lo garantiza a nivel de base de datos.
         */
        $alert->securityEvents()->syncWithoutDetaching(
            $eventIds->mapWithKeys(fn (int $id) => [$id => ['linked_at' => now()]])->all()
        );
    }

    /**
     * Sube la severidad de una alerta existente si la nueva deteccion
     * es mas grave, y extiende la ventana temporal observada.
     *
     * Una severidad nunca baja: si un ataque pasa de HIGH a LOW porque
     * el trafico bajo, la alerta sigue siendo HIGH. Bajarla sugeriria
     * que el riesgo se mitigo, algo que el sistema no puede saber.
     *
     * @param  array{detected: bool, rule: string, severity: ?AlertSeverity, source_ip: string, event_count: int, first_seen_at: ?Carbon, last_seen_at: ?Carbon}  $detection
     */
    private function escalateSeverity(Alert $alert, array $detection): void
    {
        $newSeverity = $detection['severity'];

        if ($newSeverity && $newSeverity->isAtLeast($alert->severity)) {
            $alert->severity = $newSeverity;
        }

        $firstSeen = $detection['first_seen_at'];
        $lastSeen = $detection['last_seen_at'];

        if ($firstSeen && $firstSeen->lt($alert->first_seen_at)) {
            $alert->first_seen_at = $firstSeen;
        }

        if ($lastSeen && $lastSeen->gt($alert->last_seen_at)) {
            $alert->last_seen_at = $lastSeen;
        }

        $alert->event_count = $detection['event_count'];
        $alert->save();
    }

    /**
     * Construye el titulo visible de la alerta.
     *
     * @param  array{detected: bool, rule: string, severity: ?AlertSeverity, source_ip: string, event_count: int, first_seen_at: ?Carbon, last_seen_at: ?Carbon}  $detection
     */
    private function buildTitle(string $rule, string $sourceIp): string
    {
        return match ($rule) {
            'BRUTE_FORCE' => "Intento de fuerza bruta desde {$sourceIp}",
            default => "Deteccion {$rule} desde {$sourceIp}",
        };
    }

    /**
     * Redacta la descripcion que vera el analista.
     *
     * @param  array{detected: bool, rule: string, severity: ?AlertSeverity, source_ip: string, event_count: int, first_seen_at: ?Carbon, last_seen_at: ?Carbon}  $detection
     */
    private function buildDescription(array $detection): string
    {
        $count = $detection['event_count'];
        $ip = $detection['source_ip'];

        return match ($detection['rule']) {
            'BRUTE_FORCE' => "Se detectaron {$count} intentos fallidos de autenticacion "
                ."desde la direccion {$ip} dentro de una ventana de 5 minutos. "
                .'La regla BRUTE_FORCE considera sospechoso este patron.',
            default => "La regla {$detection['rule']} se cumplio para {$ip} "
                ."con {$count} eventos observados.",
        };
    }
}
