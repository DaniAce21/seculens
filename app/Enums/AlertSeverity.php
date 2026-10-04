<?php

namespace App\Enums;

/**
 * Severidad de una alerta de seguridad.
 *
 * Se implementa como enum de PHP respaldado por string en lugar de
 * columnas VARCHAR libres porque el dominio define un conjunto cerrado
 * de valores. Usar el enum permite que el compilador y el analisis estatico
 * detecten comparaciones invalidas, y evita que un typo como 'HIG' llegue
 * a la base de datos.
 *
 * El orden en que se declaran los casos es Cosmetic y no debe
 * interpretarse como jerarquia. Un enum respaldado por string en PHP no
 * admite conversion a entero, por lo que la comparacion de severidades
 * se resuelve de forma explicita en level().
 */
enum AlertSeverity: string
{
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
    case CRITICAL = 'CRITICAL';

    /**
     * Devuelve la etiqueta legible para mostrar en la interfaz.
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Baja',
            self::MEDIUM => 'Media',
            self::HIGH => 'Alta',
            self::CRITICAL => 'Crítica',
        };
    }

    /**
     * Nivel numerico para comparar severidades entre si.
     *
     * No usamos el indice del caso porque podria cambiar si alguien
     * reordena el enum, lo que alteraria silenciosamente la logica de
     * comparacion en produccion. Un valor explicito es mas seguro.
     */
    public function level(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::CRITICAL => 4,
        };
    }

    /**
     * Indica si esta severidad es igual o mas grave que la indicada.
     *
     * Se usa al recalcular la severidad de una alerta existente: una
     * alerta nunca debe perder gravedad, solo puede escalarse.
     */
    public function isAtLeast(self $other): bool
    {
        return $this->level() >= $other->level();
    }

    /**
     * Devuelve la severidad mas grave entre las dos.
     */
    public function max(self $other): self
    {
        return $this->isAtLeast($other) ? $this : $other;
    }
}
