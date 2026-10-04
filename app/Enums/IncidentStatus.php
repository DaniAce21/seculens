<?php

namespace App\Enums;

/**
 * Estados del ciclo de vida de un incidente.
 *
 * A diferencia de una alerta, un incidente tiene un flujo mas largo
 * porque representa trabajo humano sostenido en el tiempo: alguien
 * investiga, documenta y finalmente cierra.
 *
 *     OPEN -> IN_PROGRESS -> RESOLVED -> CLOSED
 *
 * Se permite reabrir un incidente resuelto mientras no este cerrado,
 * porque una resolucion prematura es un error comun en la operacion real
 * y forzar la creacion de un incidente nuevo perderia el historial.
 */
enum IncidentStatus: string
{
    case OPEN = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case RESOLVED = 'RESOLVED';
    case CLOSED = 'CLOSED';

    /**
     * Indica si el incidente sigue abierto a trabajo.
     */
    public function isActive(): bool
    {
        return $this !== self::CLOSED;
    }

    /**
     * Verifica que la transicion de estado sea valida.
     *
     * Un incidente resuelto puede cerrarse o volver a estar en progreso
     * si aparece nueva evidencia. Uno cerrado es terminal: es el
     * registro final de la investigacion y reabrirlo destruiria la
     * garantia de que los incidentes cerrados son historiales
     * definitivos.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::OPEN => in_array($target, [self::IN_PROGRESS, self::RESOLVED], true),
            self::IN_PROGRESS => in_array($target, [self::RESOLVED, self::OPEN], true),
            self::RESOLVED => in_array($target, [self::CLOSED, self::IN_PROGRESS], true),
            self::CLOSED => false,
        };
    }

    /**
     * Estados alcanzables desde el actual.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case) => $this->canTransitionTo($case),
        ));
    }

    /**
     * Indica si el estado es terminal.
     */
    public function isTerminal(): bool
    {
        return $this === self::CLOSED;
    }

    /**
     * Etiqueta legible para la interfaz.
     */
    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Abierto',
            self::IN_PROGRESS => 'En investigación',
            self::RESOLVED => 'Resuelto',
            self::CLOSED => 'Cerrado',
        };
    }
}
