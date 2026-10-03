<?php

namespace Database\Seeders;

use App\Models\SecurityEvent;
use Illuminate\Database\Seeder;

/**
 * Carga eventos sinteticos para el laboratorio de SecuLens.
 *
 * El objetivo del seeder es twofold:
 *
 * 1. Generar actividad normal y variada para poder observar el Event
 *    Explorer con contenido realista.
 *
 * 2. Preparar un escenario de ataque de fuerza bruta controlado que la
 *    regla BRUTE_FORCE deba detectar.
 */
class SecurityEventSeeder extends Seeder
{
    /**
     * Direccion IP reservada para el escenario de ataque.
     *
     * Dentro del rango privado 192.168.0.0/16, de modo que nunca
     * apunte a un host real del laboratorio.
     */
    private const BRUTE_FORCE_IP = '192.168.100.50';

    /**
     * Numero de intentos fallidos que deben existir para activar la regla.
     */
    private const BRUTE_FORCE_ATTEMPTS = 5;

    public function run(): void
    {
        $this->generateBaselineActivity();
        $this->generateBruteForceScenario();
    }

    /**
     * Genera actividad sintetica variada.
     */
    private function generateBaselineActivity(): void
    {
        SecurityEvent::factory()->count(50)->create();
    }

    /**
     * Genera un intento de fuerza bruta que la regla debe detectar.
     *
     * Los eventos se anotan hacia adelante desde ahora, con un minuto
     * de separacion, de modo que los cinco caigan dentro de la ventana
     * de cinco minutos que evalua DetectionService.
     *
     * Antes de insertar, se elimina el escenario de una ejecucion
     * anterior. Sin esta limpieza, reejecutar el seeder acumulara
     * bloques de cinco eventos adicionales y la base de datos dejaria
     * de reflejar el escenario de forma reproducible.
     */
    private function generateBruteForceScenario(): void
    {
        SecurityEvent::query()
            ->where('event_type', 'LOGIN_FAILED')
            ->where('source_ip', self::BRUTE_FORCE_IP)
            ->delete();

        for ($i = 0; $i < self::BRUTE_FORCE_ATTEMPTS; $i++) {
            SecurityEvent::create([
                'event_type' => 'LOGIN_FAILED',
                'username' => 'admin',
                'source_ip' => self::BRUTE_FORCE_IP,
                'user_agent' => 'SecuLens-Lab-Agent',
                'result' => 'FAILURE',
                'occurred_at' => now()->subMinutes(self::BRUTE_FORCE_ATTEMPTS - 1 - $i),
                'metadata' => [
                    'application' => 'seculens-lab',
                    'environment' => 'local',
                    'scenario' => 'brute-force-test',
                ],
            ]);
        }
    }
}
