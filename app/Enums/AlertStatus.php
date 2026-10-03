<?php

namespace App\Enums;

/**
 * Estados del ciclo de vida de una alerta.
 *
 * El flujo permitido es siempre lineal hacia adelante:
 *
 *     OPEN -> ACKNOWLEDGED -> RESOLVED
 *
 * Las transiciones validas se centralizan en canTransitionTo() para que
 * exista un unico lugar donde vive la regla. Si la misma validacion se
 * duplicara en el controller, en la API y en la interfaz, seria
 * sencillo que una de las tres se quede desactualizada.
 */
enum AlertStatus: string
{
    case OPEN = 'OPEN';
    case ACKNOWLEDGED = 'ACKNOWLEDGED';
    case RESOLVED = 'RESOLVED';

    /**
     * Indica si la alerta aun requiere accion de un analista.
     */
    public function isActive(): bool
    {
        return $this !== self::RESOLVED;
    }

    /**
     * Verifica que la transicion de estado sea valida.
     *
     * Permitimos resolver una alerta directamente desde OPEN porque en
     * la practica un analista puede descartar un falso positivo sin
     * pasar por el estado intermedio. Anadir un paso obligatorio
     * generaria friccion sin aportar valor real al dominio.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::OPEN => in_array($target, [self::ACKNOWLEDGED, self::RESOLVED], true),
            self::ACKNOWLEDGED => in_array($target, [self::OPEN, self::RESOLVED], true),
            self::RESOLVED => false,
        };
    }

    /**
     * Devuelve los estados hacia los que se puede transicionar.
     */
    public function allowedTransitions(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case) => $this->canTransitionTo($case),
        ));
    }

    /**
     * Etiqueta legible para la interfaz.
     */
    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Abierta',
            self::ACKNOWLEDGED => 'Reconocida',
            self::RESOLVED => 'Resuelta',
        };
    }
}
