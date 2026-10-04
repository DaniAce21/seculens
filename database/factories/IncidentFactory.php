<?php

namespace Database\Factories;

use App\Enums\AlertSeverity;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => IncidentStatus::OPEN,
            'priority' => AlertSeverity::MEDIUM,
            'opened_at' => now()->subHours(fake()->numberBetween(1, 72)),
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncidentStatus::OPEN,
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncidentStatus::IN_PROGRESS,
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Incidente resuelto, con su marca temporal coherente.
     *
     * resolved_at se calcula en lugar de dejar que lo rellene el test
     * porque un RESOLVED sin fecha produce una ficha donde el tiempo
     * total de resolucion aparece negativo o infinito.
     */
    public function resolved(): static
    {
        return $this->state(function (array $attributes) {
            $resolvedAt = now()->subHours(fake()->numberBetween(1, 12));

            return [
                'status' => IncidentStatus::RESOLVED,
                'resolved_at' => $resolvedAt,
                'closed_at' => null,
            ];
        });
    }

    public function closed(): static
    {
        return $this->state(function (array $attributes) {
            $closedAt = now()->subHours(fake()->numberBetween(1, 12));

            return [
                'status' => IncidentStatus::CLOSED,
                'resolved_at' => $closedAt->copy()->subHours(1),
                'closed_at' => $closedAt,
            ];
        });
    }

    /**
     * Incidente asignado a un analista.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $user->id,
        ]);
    }

    public function priority(AlertSeverity $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }
}
