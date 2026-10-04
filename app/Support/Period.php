<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Periodo de tiempo (dia, semana, mes o año) que contiene una fecha.
 *
 * Lo usan la exportacion CSV, el informe imprimible y el informe semanal
 * por correo, para que todos calculen los limites exactamente igual.
 * La semana va de lunes a domingo (criterio por defecto de Carbon).
 */
final class Period
{
    /**
     * Periodos admitidos => nombre para archivos (sin tildes ni ñ).
     */
    public const SLUGS = [
        'day' => 'dia',
        'week' => 'semana',
        'month' => 'mes',
        'year' => 'anio',
    ];

    /**
     * Nombre legible de cada periodo.
     */
    public const LABELS = [
        'day' => 'Día',
        'week' => 'Semana',
        'month' => 'Mes',
        'year' => 'Año',
    ];

    public function __construct(
        public readonly string $unit,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    /**
     * Periodo que contiene la fecha dada.
     */
    public static function containing(string $unit, CarbonImmutable $date): self
    {
        return new self($unit, $date->startOf($unit), $date->endOf($unit));
    }

    /**
     * Lee ?period= y ?date= de la peticion (con validacion).
     *
     * @param  string|null  $defaultUnit  si se indica, period deja de ser obligatorio
     */
    public static function fromRequest(Request $request, ?string $defaultUnit = null): self
    {
        $validated = $request->validate([
            'period' => [$defaultUnit ? 'nullable' : 'required', 'in:'.implode(',', array_keys(self::SLUGS))],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'])
            : CarbonImmutable::now();

        return self::containing($validated['period'] ?? $defaultUnit, $date);
    }

    /**
     * Texto para titulos, p. ej. "Semana del 28/09/2026 al 04/10/2026".
     */
    public function description(): string
    {
        return match ($this->unit) {
            'day' => 'Día '.$this->from->format('d/m/Y'),
            'week' => 'Semana del '.$this->from->format('d/m/Y').' al '.$this->to->format('d/m/Y'),
            'month' => 'Mes '.$this->from->format('m/Y'),
            'year' => 'Año '.$this->from->format('Y'),
        };
    }

    /**
     * Sufijo para nombres de archivo, p. ej. "semana_2026-09-28_a_2026-10-04".
     */
    public function fileSuffix(): string
    {
        return self::SLUGS[$this->unit].'_'.$this->from->format('Y-m-d').'_a_'.$this->to->format('Y-m-d');
    }
}
