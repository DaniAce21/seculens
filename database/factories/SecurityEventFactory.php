<?php

namespace Database\Factories;

use App\Models\SecurityEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityEvent>
 */
class SecurityEventFactory extends Factory
{
    /**
     * Define los datos por defecto de un evento de seguridad.
     *
     * Los valores generados representan actividad sintética
     * para pruebas locales de SecuLens.
     */
    public function definition(): array
    {
        $eventType = fake()->randomElement([
            'LOGIN_SUCCESS',
            'LOGIN_FAILED',
            'LOGOUT',
        ]);

        return [
            'event_type' => $eventType,

            'username' => fake()->userName(),

            'source_ip' => fake()->ipv4(),

            'user_agent' => fake()->userAgent(),

            'result' => match ($eventType) {
                'LOGIN_SUCCESS' => 'SUCCESS',
                'LOGIN_FAILED' => 'FAILURE',
                'LOGOUT' => 'SUCCESS',
            },

            'occurred_at' => fake()->dateTimeBetween(
                '-24 hours',
                'now'
            ),

            'metadata' => [
                'application' => 'seculens-lab',
                'environment' => 'local',
            ],
        ];
    }
}