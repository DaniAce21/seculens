<?php

namespace Database\Seeders;

use App\Models\SecurityEvent;
use Illuminate\Database\Seeder;

class SecurityEventSeeder extends Seeder
{
    /**
     * Carga eventos sintéticos para el laboratorio de SecuLens.
     *
     * Se generan eventos normales mediante la Factory y,
     * adicionalmente, un conjunto controlado de intentos fallidos
     * desde una misma IP para probar posteriormente la detección
     * de posibles ataques de fuerza bruta.
     */
    public function run(): void
    {
        // Genera actividad normal y variada para el entorno de laboratorio.
        SecurityEvent::factory()->count(50)->create();

        /*
         * Genera cinco intentos fallidos desde la misma IP
         * dentro de una ventana de cinco minutos.
         *
         * Este escenario representa el patrón que nuestra primera
         * regla de detección deberá identificar posteriormente.
         */
        $bruteForceIp = '192.168.100.50';

        for ($i = 0; $i < 5; $i++) {
            SecurityEvent::create([
                'event_type' => 'LOGIN_FAILED',
                'username' => 'admin',
                'source_ip' => $bruteForceIp,
                'user_agent' => 'SecuLens-Lab-Agent',
                'result' => 'FAILURE',
                'occurred_at' => now()->subMinutes(4 - $i),
                'metadata' => [
                    'application' => 'seculens-lab',
                    'environment' => 'local',
                    'scenario' => 'brute-force-test',
                ],
            ]);
        }
    }
}