@extends('layouts.app')

@section('title', 'Informe')
@section('page-title', 'Informe de Seguridad')
@section('page-subtitle', $period->description())

@section('content')
    {{-- ===== Selector de periodo (no se imprime) ===== --}}
    <form method="GET" action="{{ route('reports.index') }}" class="card mb-6 no-print">
        <div class="card-body filter-bar">
            <select name="period" class="form-select" aria-label="Periodo">
                @foreach ($periodLabels as $value => $label)
                    <option value="{{ $value }}" @selected($period->unit === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2">
                <span class="text-tertiary">Que incluya</span>
                <input type="date" name="date" class="form-input" value="{{ $date }}">
            </label>
            <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Generar</button>
            <button type="button" class="btn btn-ghost" id="printReport" style="margin-left: auto;">
                <i class="bi bi-printer-fill"></i> Imprimir / Guardar PDF
            </button>
        </div>
    </form>

    {{-- Cabecera solo visible al imprimir --}}
    <div class="print-only report-print-header">
        <h1>SecuLens — Informe de Seguridad</h1>
        <p>{{ $period->description() }} · Generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    {{-- ===== Cifras clave ===== --}}
    <div class="stats-grid mb-6">
        @foreach ([
            ['Eventos', $totals['events'], 'bi-activity', 'info'],
            ['Logins fallidos', $totals['failed_logins'], 'bi-person-x-fill', 'warning'],
            ['Alertas', $totals['alerts'], 'bi-exclamation-triangle-fill', 'danger'],
            ['Alertas IDS', $totals['ids_alerts'], 'bi-broadcast', 'danger'],
            ['Incidentes abiertos', $totals['incidents_opened'], 'bi-clipboard-data-fill', 'warning'],
            ['Incidentes cerrados', $totals['incidents_closed'], 'bi-clipboard-check-fill', 'primary'],
        ] as [$label, $value, $icon, $tone])
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-value">{{ number_format($value) }}</span>
                    <span class="stat-label">{{ $label }}</span>
                </div>
                <div class="stat-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></div>
            </div>
        @endforeach
    </div>

    {{-- ===== Evolucion temporal ===== --}}
    <div class="card mb-6 avoid-break">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-bar-chart-fill"></i>
                Eventos por {{ ['day' => 'hora', 'year' => 'mes'][$period->unit] ?? 'día' }}
            </h3>
        </div>
        <div class="card-body">
            <x-column-chart :series="$timeline" />
        </div>
    </div>

    @php
        $severityColors = ['CRITICAL' => 'var(--color-danger)', 'HIGH' => '#f97316', 'MEDIUM' => 'var(--color-warning)', 'LOW' => 'var(--color-info)'];
        $severityItems = collect($alerts_by_severity)->map(fn ($total, $sev) => [
            'label' => \App\Enums\AlertSeverity::from($sev)->label(),
            'value' => $total,
            'color' => $severityColors[$sev],
        ])->values()->all();

        $idsColors = ['critical' => 'var(--color-danger)', 'high' => '#f97316', 'medium' => 'var(--color-warning)', 'low' => 'var(--color-info)'];
        $idsLabels = ['critical' => 'Crítica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
        $idsItems = collect($idsLabels)->map(fn ($label, $sev) => [
            'label' => $label,
            'value' => $ids_by_severity[$sev] ?? 0,
            'color' => $idsColors[$sev],
        ])->values()->all();

        $typeItems = collect($events_by_type)->map(fn ($total, $type) => [
            // tryFrom: un tipo desconocido se muestra tal cual en vez de fallar.
            'label' => \App\Enums\EventType::tryFrom($type)?->label() ?? $type,
            'value' => $total,
        ])->values()->all();
    @endphp

    <div class="grid grid-3 mb-6">
        <div class="card avoid-break">
            <div class="card-header"><h3 class="card-title">Alertas por severidad</h3></div>
            <div class="card-body"><x-bar-list :items="$severityItems" empty="Sin alertas en el periodo" /></div>
        </div>
        <div class="card avoid-break">
            <div class="card-header"><h3 class="card-title">Alertas IDS por severidad</h3></div>
            <div class="card-body"><x-bar-list :items="$idsItems" empty="Sin alertas IDS en el periodo" /></div>
        </div>
        <div class="card avoid-break">
            <div class="card-header"><h3 class="card-title">Eventos por tipo</h3></div>
            <div class="card-body"><x-bar-list :items="$typeItems" empty="Sin eventos en el periodo" /></div>
        </div>
    </div>

    {{-- ===== IPs mas activas ===== --}}
    <div class="card mb-6 avoid-break">
        <div class="card-header"><h3 class="card-title"><i class="bi bi-geo-alt-fill"></i> IPs atacantes más activas</h3></div>
        <div class="card-body" style="padding: 0;">
            @if (empty($top_ips))
                <div class="table-empty"><p class="mb-0">Sin actividad sospechosa en el periodo</p></div>
            @else
                <div class="table-container" style="border: none; border-radius: 0;">
                    <table class="table">
                        <thead><tr><th>#</th><th>IP</th><th>Logins fallidos</th><th>Alertas IDS</th><th>Total</th></tr></thead>
                        <tbody>
                            @foreach ($top_ips as $i => $row)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td><x-ip :ip="$row['ip']" /></td>
                                    <td>{{ $row['failed_logins'] }}</td>
                                    <td>{{ $row['ids_alerts'] }}</td>
                                    <td><strong>{{ $row['total'] }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== Incidentes del periodo ===== --}}
    <div class="card avoid-break">
        <div class="card-header"><h3 class="card-title"><i class="bi bi-clipboard-data-fill"></i> Incidentes abiertos en el periodo</h3></div>
        <div class="card-body" style="padding: 0;">
            @if ($incidents->isEmpty())
                <div class="table-empty"><p class="mb-0">No se abrieron incidentes en el periodo</p></div>
            @else
                <div class="table-container" style="border: none; border-radius: 0;">
                    <table class="table">
                        <thead><tr><th>Título</th><th>Prioridad</th><th>Estado</th><th>Responsable</th><th>Abierto</th></tr></thead>
                        <tbody>
                            @foreach ($incidents as $incident)
                                <tr>
                                    <td><a href="{{ route('incidents.show', $incident) }}" class="table-link">{{ $incident->title }}</a></td>
                                    <td><span class="badge badge-{{ strtolower($incident->priority->value) }}">{{ $incident->priority->label() }}</span></td>
                                    <td><span class="badge badge-{{ strtolower($incident->status->value) }}">{{ $incident->status->label() }}</span></td>
                                    <td>{{ $incident->assignee?->name ?? 'Sin asignar' }}</td>
                                    <td>{{ $incident->opened_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/report.js') }}"></script>
@endpush
