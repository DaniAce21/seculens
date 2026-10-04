<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\Alert;
use App\Models\IdsAlert;
use App\Models\Incident;
use App\Models\SecurityEvent;
use App\Support\EventFilters;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Generacion de CSV de incidentes, eventos, alertas y alertas IDS.
 *
 * La usan la descarga web (ExportController) y el informe semanal por
 * correo (comando reports:weekly), para que ambos produzcan exactamente
 * el mismo archivo.
 *
 * Formato pensado para Excel en español: separador ';' y BOM UTF-8.
 */
class CsvExportService
{
    /**
     * Recursos exportables => prefijo del nombre de archivo.
     */
    public const RESOURCES = [
        'incidents' => 'incidentes',
        'events' => 'eventos',
        'alerts' => 'alertas',
        'ids-alerts' => 'alertas_ids',
    ];

    /**
     * Excel en español usa la coma como separador decimal y espera ';'
     * entre columnas al abrir un CSV con doble clic.
     */
    private const DELIMITER = ';';

    /**
     * Nombre de archivo, p. ej. "incidentes_mes_2026-10-01_a_2026-10-31.csv".
     */
    public function filename(string $resource, Period $period): string
    {
        return self::RESOURCES[$resource].'_'.$period->fileSuffix().'.csv';
    }

    /**
     * Numero de filas que tendra la exportacion (para la auditoria).
     *
     * @param  array<string, string>  $filters  solo aplica a 'events'
     */
    public function count(string $resource, Period $period, array $filters = []): int
    {
        return $this->definition($resource, $period, $filters)['query']->count();
    }

    /**
     * Escribe el CSV completo en un recurso de PHP (php://output, archivo...).
     *
     * @param  resource  $handle
     * @param  array<string, string>  $filters
     */
    public function write($handle, string $resource, Period $period, array $filters = []): void
    {
        ['query' => $query, 'headers' => $headers, 'row' => $row] = $this->definition($resource, $period, $filters);

        // BOM UTF-8: sin el, Excel muestra mal las tildes y la ñ.
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, $headers, self::DELIMITER);

        // cursor() recorre los registros de uno en uno (bajo consumo de memoria).
        foreach ($query->cursor() as $model) {
            fputcsv($handle, array_map($this->sanitizeCell(...), $row($model)), self::DELIMITER);
        }
    }

    /**
     * Devuelve el CSV como texto (para adjuntarlo a un correo).
     *
     * @param  array<string, string>  $filters
     */
    public function toString(string $resource, Period $period, array $filters = []): string
    {
        $handle = fopen('php://temp', 'r+');
        $this->write($handle, $resource, $period, $filters);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Consulta, cabeceras y conversion a fila de cada recurso.
     *
     * @param  array<string, string>  $filters
     * @return array{query: Builder, headers: list<string>, row: callable}
     */
    private function definition(string $resource, Period $period, array $filters): array
    {
        $range = [$period->from, $period->to];

        return match ($resource) {
            // Un incidente pertenece al periodo en que se abrio.
            'incidents' => [
                'query' => Incident::query()
                    ->with('assignee:id,name')
                    ->whereBetween('opened_at', $range)
                    ->orderBy('opened_at'),
                'headers' => ['ID', 'Titulo', 'Descripcion', 'Estado', 'Prioridad', 'Asignado a', 'Abierto', 'Resuelto', 'Cerrado'],
                'row' => fn (Incident $i) => [
                    $i->id,
                    $i->title,
                    $i->description,
                    $i->status?->label(),
                    $i->priority?->label(),
                    $i->assignee?->name ?? 'Sin asignar',
                    $i->opened_at?->format('Y-m-d H:i:s'),
                    $i->resolved_at?->format('Y-m-d H:i:s'),
                    $i->closed_at?->format('Y-m-d H:i:s'),
                ],
            ],

            // Los eventos admiten ademas los filtros del explorador.
            'events' => [
                'query' => EventFilters::apply(SecurityEvent::query(), $filters)
                    ->whereBetween('occurred_at', $range)
                    ->orderBy('occurred_at'),
                'headers' => ['ID', 'Tipo', 'Usuario', 'IP origen', 'Resultado', 'User agent', 'Ocurrido'],
                'row' => function (SecurityEvent $e) {
                    // Valor crudo: un tipo desconocido se exporta tal cual en vez de romper la descarga.
                    $rawType = $e->getRawOriginal('event_type');

                    return [
                        $e->id,
                        EventType::tryFrom((string) $rawType)?->label() ?? $rawType,
                        $e->username,
                        $e->source_ip,
                        $e->result,
                        $e->user_agent,
                        $e->occurred_at?->format('Y-m-d H:i:s'),
                    ];
                },
            ],

            // Una alerta pertenece al periodo en que se detecto por primera vez.
            'alerts' => [
                'query' => Alert::query()
                    ->withCount('securityEvents')
                    ->whereBetween('first_seen_at', $range)
                    ->orderBy('first_seen_at'),
                'headers' => ['ID', 'Titulo', 'Severidad', 'Estado', 'IP origen', 'Regla', 'Eventos', 'Primera deteccion', 'Ultima deteccion'],
                'row' => fn (Alert $a) => [
                    $a->id,
                    $a->title,
                    $a->severity?->label(),
                    $a->status?->label(),
                    $a->source_ip,
                    $a->detection_rule,
                    $a->security_events_count,
                    $a->first_seen_at?->format('Y-m-d H:i:s'),
                    $a->last_seen_at?->format('Y-m-d H:i:s'),
                ],
            ],

            'ids-alerts' => [
                'query' => IdsAlert::query()
                    ->with('sensor:id,name')
                    ->whereBetween('created_at', $range)
                    ->orderBy('created_at'),
                'headers' => ['ID', 'Fecha', 'Severidad', 'Tipo', 'IP origen', 'IP destino', 'Firma', 'Estado', 'Sensor'],
                'row' => fn (IdsAlert $a) => [
                    $a->id,
                    $a->created_at?->format('Y-m-d H:i:s'),
                    $a->severity,
                    $a->alert_type,
                    $a->source_ip,
                    $a->destination_ip,
                    $a->signature,
                    $a->status,
                    $a->sensor?->name,
                ],
            ],

            default => throw new InvalidArgumentException("Recurso de exportacion desconocido: {$resource}"),
        };
    }

    /**
     * Neutraliza la "inyeccion de formulas" en CSV.
     *
     * Los eventos contienen datos que controla un atacante (usuario, user
     * agent...). Si una celda empieza por = + - @ Excel la ejecuta como
     * formula al abrir el archivo. Anteponer una comilla simple la
     * convierte en texto plano.
     */
    private function sanitizeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
