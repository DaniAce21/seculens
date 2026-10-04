<?php

namespace App\Support;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Filtros del explorador de eventos.
 *
 * Se comparten entre el listado (EventController) y la exportacion CSV
 * (ExportController) para que el archivo descargado contenga exactamente
 * lo que el analista esta viendo en pantalla.
 */
class EventFilters
{
    /**
     * Nombres de los parametros de filtro admitidos en la URL.
     */
    public const KEYS = ['type', 'ip', 'username', 'result', 'from', 'to'];

    /**
     * Valida y devuelve los filtros presentes en la peticion.
     *
     * @return array<string, string>
     */
    public static function fromRequest(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::enum(EventType::class)],
            'ip' => ['nullable', 'string', 'max:45', 'regex:/^[0-9a-fA-F:.]+$/'],
            'username' => ['nullable', 'string', 'max:150'],
            'result' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        // Se descartan los vacios para no ensuciar la URL ni la consulta.
        return array_filter($validated, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Aplica los filtros a una consulta de SecurityEvent.
     *
     * @param  array<string, string>  $filters
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('event_type', $type))
            // source_ip es inet en PostgreSQL: se compara como texto para
            // permitir busquedas parciales ("185.234." encuentra la subred).
            ->when($filters['ip'] ?? null, fn ($q, $ip) => $q->whereRaw('CAST(source_ip AS TEXT) LIKE ?', [$ip.'%']))
            // ILIKE = busqueda sin distinguir mayusculas (PostgreSQL). addcslashes
            // escapa % y _ para que el texto del usuario no actue como comodin.
            ->when($filters['username'] ?? null, fn ($q, $u) => $q->where('username', 'ILIKE', '%'.addcslashes($u, '%_\\').'%'))
            ->when($filters['result'] ?? null, fn ($q, $r) => $q->where('result', $r))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('occurred_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('occurred_at', '<=', $to.' 23:59:59'));
    }
}
