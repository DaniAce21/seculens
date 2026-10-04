<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\EventType;
use App\Models\Alert;
use App\Models\IdsAlert;
use App\Models\Incident;
use App\Models\SecurityEvent;
use App\Support\Period;
use Illuminate\Support\Facades\DB;

/**
 * Datos agregados de un periodo para el informe de seguridad.
 *
 * Lo consumen el informe imprimible (/reports) y el informe semanal que
 * se envia por correo (comando reports:weekly). Todas las agregaciones
 * se hacen en SQL; ninguna trae filas sueltas a PHP salvo los listados
 * cortos (top N).
 */
class ReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Period $period): array
    {
        $range = [$period->from, $period->to];

        $events = SecurityEvent::query()->whereBetween('occurred_at', $range);
        $ids = IdsAlert::query()->whereBetween('created_at', $range);

        return [
            'period' => $period,

            'totals' => [
                'events' => (clone $events)->count(),
                'failed_logins' => (clone $events)->where('event_type', EventType::LOGIN_FAILED->value)->count(),
                'alerts' => Alert::query()->whereBetween('first_seen_at', $range)->count(),
                'ids_alerts' => (clone $ids)->count(),
                'incidents_opened' => Incident::query()->whereBetween('opened_at', $range)->count(),
                'incidents_closed' => Incident::query()->whereBetween('closed_at', $range)->count(),
            ],

            'events_by_type' => $this->eventsByType($events),
            'alerts_by_severity' => $this->alertsBySeverity($range),
            'ids_by_severity' => (clone $ids)
                ->selectRaw('severity, COUNT(*) as total')
                ->groupBy('severity')
                ->pluck('total', 'severity')
                ->map(fn ($v) => (int) $v)
                ->all(),
            'timeline' => $this->timeline($period),
            'top_ips' => $this->topIps($range),
            'incidents' => Incident::query()
                ->with('assignee:id,name')
                ->whereBetween('opened_at', $range)
                ->orderByDesc('opened_at')
                ->limit(20)
                ->get(),
        ];
    }

    /**
     * @return array<string, int>  tipo => total, de mayor a menor
     */
    private function eventsByType($events): array
    {
        $counts = (clone $events)
            ->selectRaw('event_type, COUNT(*) as total')
            ->groupBy('event_type')
            ->orderByDesc('total')
            ->toBase() // sin casts del modelo: event_type llega como texto
            ->pluck('total', 'event_type');

        return $counts->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @return array<string, int>  severidad => total (todas, de Critica a Baja)
     */
    private function alertsBySeverity(array $range): array
    {
        $counts = Alert::query()
            ->whereBetween('first_seen_at', $range)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->toBase()
            ->pluck('total', 'severity');

        $cases = AlertSeverity::cases();
        usort($cases, fn ($a, $b) => $b->level() <=> $a->level());

        $result = [];
        foreach ($cases as $severity) {
            $result[$severity->value] = (int) ($counts[$severity->value] ?? 0);
        }

        return $result;
    }

    /**
     * Serie temporal de eventos: por hora (dia), por dia (semana/mes) o
     * por mes (año), con los huecos rellenos a cero.
     *
     * @return list<array{day: string, label: string, total: int}>
     */
    private function timeline(Period $period): array
    {
        [$trunc, $format, $step, $labelFormat] = match ($period->unit) {
            'day' => ['hour', 'YYYY-MM-DD HH24:00', 'addHour', 'H\h'],
            'year' => ['month', 'YYYY-MM-01', 'addMonth', 'M'],
            default => ['day', 'YYYY-MM-DD', 'addDay', 'd/m'],
        };

        $rows = DB::table('security_events')
            ->whereBetween('occurred_at', [$period->from, $period->to])
            ->selectRaw("to_char(date_trunc('{$trunc}', occurred_at), '{$format}') as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $series = [];
        $phpFormat = match ($trunc) {
            'hour' => 'Y-m-d H:00',
            'month' => 'Y-m-01',
            default => 'Y-m-d',
        };

        for ($cursor = $period->from; $cursor <= $period->to; $cursor = $cursor->{$step}()) {
            $key = $cursor->format($phpFormat);
            $series[] = [
                'day' => $cursor->format('Y-m-d H:i'),
                'label' => $cursor->format($labelFormat),
                'total' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * IPs origen con mas actividad sospechosa (logins fallidos + alertas IDS).
     *
     * @return list<array{ip: string, failed_logins: int, ids_alerts: int, total: int}>
     */
    private function topIps(array $range, int $limit = 10): array
    {
        $failed = DB::table('security_events')
            ->whereBetween('occurred_at', $range)
            ->where('event_type', EventType::LOGIN_FAILED->value)
            ->whereNotNull('source_ip')
            ->selectRaw('CAST(source_ip AS TEXT) as ip, COUNT(*) as total')
            ->groupBy('source_ip')
            ->pluck('total', 'ip');

        $ids = DB::table('ids_alerts')
            ->whereBetween('created_at', $range)
            ->selectRaw('source_ip as ip, COUNT(*) as total')
            ->groupBy('source_ip')
            ->pluck('total', 'ip');

        $rows = [];
        foreach ($failed->keys()->merge($ids->keys())->unique() as $ip) {
            $f = (int) ($failed[$ip] ?? 0);
            $i = (int) ($ids[$ip] ?? 0);
            // inet puede llegar como "1.2.3.4/32"; se quita la mascara.
            $rows[] = ['ip' => explode('/', (string) $ip)[0], 'failed_logins' => $f, 'ids_alerts' => $i, 'total' => $f + $i];
        }

        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_slice($rows, 0, $limit);
    }
}
