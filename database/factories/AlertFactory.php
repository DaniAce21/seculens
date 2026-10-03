<?php

namespace Database\Factories;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * Datos por defecto de una alerta para pruebas y laboratorio.
     *
     * Por defecto la alerta se genera como ya resuelta para que los
     * tests que no evaluan el estado no interfieran entre si. Los tests
     * que si verifican el flujo de estados deben declarar el estado
     * explicitamente.
     */
    public function definition(): array
    {
        $firstSeen = fake()->dateTimeBetween('-2 hours', '-10 minutes');

        return [
            'title' => 'Posible ataque de fuerza bruta',
            'description' => 'Multiples intentos de autenticacion fallidos desde una misma direccion IP.',
            'severity' => AlertSeverity::HIGH,
            'status' => AlertStatus::RESOLVED,
            'source_ip' => fake()->ipv4(),
            'detection_rule' => 'BRUTE_FORCE',
            'first_seen_at' => $firstSeen,
            'last_seen_at' => fake()->dateTimeBetween($firstSeen, 'now'),
            'event_count' => fake()->numberBetween(5, 40),
        ];
    }

    /**
     * Alerta abierta que requiere atencion.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::OPEN,
        ]);
    }

    /**
     * Alerta que un analista ya reviso.
     */
    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::ACKNOWLEDGED,
        ]);
    }

    /**
     * Alerta con una severidad especifica.
     */
    public function severity(AlertSeverity $severity): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => $severity,
        ]);
    }

    /**
     * Alerta originada por una IP concreta.
     */
    public function fromIp(string $ip): static
    {
        return $this->state(fn (array $attributes) => [
            'source_ip' => $ip,
        ]);
    }
}
