<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\EventType;
use App\Enums\IncidentStatus;
use App\Models\Alert;
use App\Models\Incident;
use App\Models\SecurityEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consulta agregada para el panel principal.
 *
 * Vive en un servicio y no en el controller porque las agregaciones son
 * reglas de negocio sobre que significa "cuanto actividad hay", no una
 * traduccion de HTTP. El controller solo decide que vista recibe el
 * resultado.
 *
 * Cada metodo devuelve datos ya agregados en SQL. Contar en PHP
 * recorreria la tabla completa, y el dashboard es la pantalla que se
 * carga mas a menudo: debe sostenerse cuando los eventos crezcan a
 * cientos de miles.
 */
class DashboardService
{
    /**
     * Numero de alertas por severidad.
     *
     * Devuelve todos los niveles, incluidos los que tienen cero
     * alertas. La interfaz necesita la serie completa para que el
     * grafico no cambie de escala al filtrar.
     *
     * @return array<string, int>
     */
    public function alertsBySeverity(): array
    {
        $counts = Alert::query()
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $result = [];

        foreach (AlertSeverity::cases() as $severity) {
            $result[$severity->value] = (int) ($counts[$severity->value] ?? 0);
        }

        return $result;
    }

    /**
     * Numero de alertas por estado.
     *
     * @return array<string, int>
     */
    public function alertsByStatus(): array
    {
        $counts = Alert::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_map('intval', $counts->all());
    }

    /**
     * Numero de eventos por tipo.
     *
     * @return array<string, int>
     */
    public function eventsByType(): array
    {
        $counts = SecurityEvent::query()
            ->selectRaw('event_type, COUNT(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        $result = [];

        foreach (EventType::cases() as $type) {
            $result[$type->value] = (int) ($counts[$type->value] ?? 0);
        }

        return $result;
    }

    /**
     * Numero de incidentes por estado.
     *
     * @return array<string, int>
     */
    public function incidentsByStatus(): array
    {
        $counts = Incident::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $result = [];

        foreach (IncidentStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    /**
     * Direcciones IP con mas intentos de autenticacion fallidos.
     *
     * Es la pregunta principal ante un ataque de fuerza bruta, y la
     * unica forma de responderla sin traer cada evento a PHP.
     *
     * @return list<array{source_ip: string|null, total: int, last_attempt_at: Carbon|null}>
     */
    public function topFailedLoginIps(int $limit = 10): array
    {
        return DB::table('security_events')
            ->where('event_type', EventType::LOGIN_FAILED->value)
            ->whereNotNull('source_ip')
            ->groupBy('source_ip')
            ->selectRaw('source_ip, COUNT(*) as total, MAX(occurred_at) as last_attempt_at')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                /*
                 * source_ip es de tipo inet en PostgreSQL. Al leerlo
                 * desde la consulta cruda llega como cadena, y ese es el
                 * formato que la vista necesita para pintar el valor.
                 */
                'source_ip' => (string) $row->source_ip,
                'total' => (int) $row->total,
                'last_attempt_at' => Carbon::parse($row->last_attempt_at),
            ])
            ->all();
    }

    /**
     * Serie diaria de eventos para el grafico de actividad.
     *
     * Se agrega en SQL por dia porque traer un evento por fila y
     * agruparlo en PHP seria inviable a escala. date_trunc trabaja en la
     * zona horaria del servidor de PostgreSQL, que es la misma que usa
     * Eloquent para occurred_at, de modo que no hay desfase entre el
     * grafico y las marcas temporales de la tabla.
     *
     * @return list<array{day: string, total: int}>
     */
    public function dailyEventVolume(int $days = 14): array
    {
        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = DB::table('security_events')
            ->where('occurred_at', '>=', $since)
            ->selectRaw("to_char(date_trunc('day', occurred_at), 'YYYY-MM-DD') as day, COUNT(*) as total")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $byDay = [];

        foreach ($rows as $row) {
            $byDay[(string) $row->day] = (int) $row->total;
        }

        /*
         * Se rellenan los dias sin eventos con cero.
         *
         * Sin esta parte, el grafico uniria directamente el dia 3 con el
         * dia 9 y presentaria un pico donde no hubo actividad. La
         * serie completa hace que un hueco se lea como un hueco.
         */
        $series = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $day = $since->copy()->addDays($offset)->format('Y-m-d');
            $series[] = [
                'day' => $day,
                'total' => $byDay[$day] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Indicadores de resumen del panel.
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        return [
            'alertas_activas' => Alert::query()->active()->count(),
            'alertas_criticas' => Alert::query()
                ->active()
                ->withSeverityAtLeast(AlertSeverity::CRITICAL)
                ->count(),
            'incidentes_abiertos' => Incident::query()
                ->inStatus(IncidentStatus::OPEN, IncidentStatus::IN_PROGRESS)
                ->count(),
            'eventos_24h' => SecurityEvent::query()
                ->where('occurred_at', '>=', Carbon::now()->subDay())
                ->count(),
            'eventos_fallidos_24h' => SecurityEvent::query()
                ->where('event_type', EventType::LOGIN_FAILED->value)
                ->where('occurred_at', '>=', Carbon::now()->subDay())
                ->count(),
        ];
    }
}
