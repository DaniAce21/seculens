<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Models\SecurityEvent;
use Carbon\Carbon;

/**
 * Motor de reglas de deteccion sobre eventos de seguridad.
 *
 * Cada metodo público de esta clase corresponde a una regla concreta.
 * La clase responde una unica pregunta: "este conjunto de eventos
 * cumple un patron conocido?". No crea alertas ni modifica estado; esa
 * responsabilidad es de AlertService.
 *
 * Todos los metodos aceptan una referencia temporal opcional. No es un
 * detalle de implementacion, es una decision de diseno: permite que
 * las pruebas sean deterministas sin necesidad de manipular el reloj
 * del sistema, y permite reevaluar una ventana historica concreta.
 */
class DetectionService
{
    /**
     * Numero de intentos fallidos que activa la regla.
     */
    private const BRUTE_FORCE_THRESHOLD = 5;

    /**
     * Ventana temporal, en minutos, evaluada por la regla.
     */
    private const BRUTE_FORCE_WINDOW_MINUTES = 5;

    /**
     * Detecta posibles ataques de fuerza bruta desde una IP.
     *
     * Regla BRUTE_FORCE:
     * 5 o mas eventos LOGIN_FAILED desde la misma IP dentro de una
     * ventana de 5 minutos.
     *
     * La busqueda se limita a los eventos que pueden cumplir la regla
     * en vez de contar sobre toda la tabla. El indice compuesto
     * (event_type, source_ip, occurred_at) permite que PostgreSQL
     * resuelva la consulta sin recorrer la tabla completa.
     *
     * @return array{detected: bool, rule: string, severity: ?AlertSeverity, source_ip: string, event_count: int, window_minutes: int, first_seen_at: ?Carbon, last_seen_at: ?Carbon}
     */
    public function detectBruteForce(
        string $sourceIp,
        ?Carbon $referenceTime = null
    ): array {
        $windowEnd = $referenceTime ?? now();
        $windowStart = $windowEnd->copy()->subMinutes(self::BRUTE_FORCE_WINDOW_MINUTES);

        $failedAttempts = SecurityEvent::query()
            ->where('event_type', 'LOGIN_FAILED')
            ->where('source_ip', $sourceIp)
            ->whereBetween('occurred_at', [
                $windowStart,
                $windowEnd,
            ])
            ->orderBy('occurred_at')
            ->get();

        $attemptCount = $failedAttempts->count();

        $detected = $attemptCount >= self::BRUTE_FORCE_THRESHOLD;

        return [
            'detected' => $detected,
            'rule' => 'BRUTE_FORCE',
            'severity' => $detected ? AlertSeverity::HIGH : null,
            'source_ip' => $sourceIp,
            'event_count' => $attemptCount,
            'window_minutes' => self::BRUTE_FORCE_WINDOW_MINUTES,
            'first_seen_at' => $failedAttempts->first()?->occurred_at,
            'last_seen_at' => $failedAttempts->last()?->occurred_at,
        ];
    }
}
