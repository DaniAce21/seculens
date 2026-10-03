<?php

namespace App\Services;

use App\Models\SecurityEvent;
use Carbon\Carbon;

class DetectionService
{
    /**
     * Detecta posibles ataques de fuerza bruta desde una IP.
     *
     * Regla:
     * 5 o más intentos LOGIN_FAILED desde la misma IP
     * dentro de una ventana de 5 minutos.
     *
     * La fecha de referencia puede ser proporcionada explícitamente
     * para permitir pruebas deterministas sin depender de la hora actual.
     */
    public function detectBruteForce(
        string $sourceIp,
        ?Carbon $referenceTime = null
    ): array {
        $windowEnd = $referenceTime ?? now();
        $windowStart = $windowEnd->copy()->subMinutes(5);

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

        return [
            'detected' => $attemptCount >= 5,
            'rule' => 'BRUTE_FORCE',
            'severity' => $attemptCount >= 5 ? 'HIGH' : null,
            'source_ip' => $sourceIp,
            'event_count' => $attemptCount,
            'window_minutes' => 5,
            'first_seen_at' => $failedAttempts->first()?->occurred_at,
            'last_seen_at' => $failedAttempts->last()?->occurred_at,
        ];
    }
}