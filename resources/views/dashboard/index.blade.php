@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Resumen en tiempo real de la seguridad del sistema')

@section('content')
    <!-- Quick Actions -->
    {{-- El boton principal lleva al panel IDS con datos reales (/ids); antes
         llevaba al simulador estatico, que muestra trafico inventado. --}}
    <div class="flex gap-4 mb-6" style="flex-wrap: wrap;">
        <a href="{{ route('ids.index') }}" class="btn btn-primary">
            <i class="bi bi-broadcast"></i>
            Abrir panel IDS — Alertas en tiempo real
        </a>
        <a href="{{ route('reports.index') }}" class="btn btn-ghost">
            <i class="bi bi-file-earmark-bar-graph"></i>
            Informe de la semana
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid mb-6">
        <div class="stat-card">
            <div class="stat-content">
                <span class="stat-value">{{ $summary['alertas_activas'] ?? 0 }}</span>
                <span class="stat-label">Alertas Activas</span>
                <span class="stat-change">Requieren atención</span>
            </div>
            <div class="stat-icon danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-content">
                <span class="stat-value">{{ $summary['incidentes_abiertos'] ?? 0 }}</span>
                <span class="stat-label">Incidentes Abiertos</span>
                <span class="stat-change">En investigación</span>
            </div>
            <div class="stat-icon warning">
                <i class="bi bi-clipboard-data-fill"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-content">
                <span class="stat-value">{{ $summary['eventos_24h'] ?? 0 }}</span>
                <span class="stat-label">Eventos (Últimas 24h)</span>
                <span class="stat-change">Actividad detectada</span>
            </div>
            <div class="stat-icon info">
                <i class="bi bi-activity"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-content">
                <span class="stat-value">{{ $summary['eventos_totales'] ?? 0 }}</span>
                <span class="stat-label">Eventos Totales</span>
                <span class="stat-change">Histórico</span>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-database-fill"></i>
            </div>
        </div>
    </div>

    {{-- ===== Graficos ===== --}}
    @php
        // Colores de estado reservados para severidad (siempre con su etiqueta).
        $severityColors = [
            'CRITICAL' => 'var(--color-danger)',
            'HIGH' => '#f97316',
            'MEDIUM' => 'var(--color-warning)',
            'LOW' => 'var(--color-info)',
        ];
        $severityItems = collect(\App\Enums\AlertSeverity::cases())
            ->sortByDesc(fn ($s) => $s->level()) // de Critica a Baja
            ->map(fn ($s) => [
                'label' => $s->label(),
                'value' => $alertsBySeverity[$s->value] ?? 0,
                'color' => $severityColors[$s->value] ?? null,
            ])->values()->all();
        $typeItems = collect($eventsByType)
            ->map(fn ($total, $type) => ['label' => \App\Enums\EventType::from($type)->label(), 'value' => $total])
            ->sortByDesc('value')->values()->all();
        $ipItems = array_map(fn ($r) => ['label' => $r['source_ip'], 'value' => $r['total'], 'ip' => true], $topFailedLoginIps);
    @endphp

    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-bar-chart-fill"></i> Eventos por día (últimos 14 días)</h3>
            <a href="{{ route('reports.index') }}" class="btn btn-ghost btn-sm">Informe del periodo <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="card-body">
            <x-column-chart :series="$dailyEventVolume" />
        </div>
    </div>

    <div class="grid grid-3 mb-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-exclamation-diamond-fill"></i> Alertas por severidad</h3></div>
            <div class="card-body"><x-bar-list :items="$severityItems" empty="No hay alertas" /></div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-diagram-3-fill"></i> Eventos por tipo</h3></div>
            <div class="card-body"><x-bar-list :items="$typeItems" empty="No hay eventos" /></div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-geo-alt-fill"></i> Top IPs con logins fallidos</h3></div>
            <div class="card-body"><x-bar-list :items="$ipItems" empty="No hay logins fallidos" /></div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="grid grid-2 mb-6">
        <!-- Alertas Recientes -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-exclamation-triangle-fill"></i> Alertas Recientes
                </h3>
                <a href="{{ route('alerts.index') }}" class="btn btn-ghost btn-sm">
                    Ver todas <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body" style="padding: 0;">
                @php
                    $recentAlerts = App\Models\Alert::withCount('securityEvents')
                        ->latest('last_seen_at')
                        ->take(5)
                        ->get();
                @endphp
                @if ($recentAlerts->count() > 0)
                    <div class="table-container" style="border: none; border-radius: 0;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Severidad</th>
                                    <th>Estado</th>
                                    <th>Eventos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentAlerts as $alert)
                                    <tr>
                                        <td>
                                            <a href="{{ route('alerts.show', $alert) }}" class="table-link">
                                                {{ Str::limit($alert->title, 40) }}
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ strtolower($alert->severity->value) }}">
                                                {{ $alert->severity->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ strtolower($alert->status->value) }}">
                                                {{ $alert->status->label() }}
                                            </span>
                                        </td>
                                        <td>{{ $alert->security_events_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="table-empty">
                        <i class="bi bi-check-circle table-empty-icon" style="color: var(--color-success);"></i>
                        <p class="mb-0">No hay alertas recientes</p>
                        <small class="text-tertiary">El sistema está estable</small>
                    </div>
                @endif
            </div>
        </div>

        <!-- Incidentes Recientes -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-clipboard-data-fill"></i> Incidentes Recientes
                </h3>
                <a href="{{ route('incidents.index') }}" class="btn btn-ghost btn-sm">
                    Ver todos <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body" style="padding: 0;">
                @php
                    $recentIncidents = App\Models\Incident::latest('opened_at')
                        ->take(5)
                        ->get();
                @endphp
                @if ($recentIncidents->count() > 0)
                    <div class="table-container" style="border: none; border-radius: 0;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Estado</th>
                                    <th>Prioridad</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentIncidents as $incident)
                                    <tr>
                                        <td>
                                            <a href="{{ route('incidents.show', $incident) }}" class="table-link">
                                                {{ Str::limit($incident->title, 40) }}
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ strtolower($incident->status->value) }}">
                                                {{ $incident->status->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ strtolower($incident->priority->value) }}">
                                                {{ $incident->priority->label() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="table-empty">
                        <i class="bi bi-clipboard-check table-empty-icon" style="color: var(--color-success);"></i>
                        <p class="mb-0">No hay incidentes recientes</p>
                        <small class="text-tertiary">Todo bajo control</small>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Eventos Recientes -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-activity"></i> Eventos de Seguridad Recientes
            </h3>
            <a href="{{ route('events.index') }}" class="btn btn-ghost btn-sm">
                Ver todos <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="card-body" style="padding: 0;">
            @php
                $recentEvents = App\Models\SecurityEvent::latest('occurred_at')
                    ->take(8)
                    ->get();
            @endphp
            @if ($recentEvents->count() > 0)
                <div class="table-container" style="border: none; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tipo de Evento</th>
                                <th>IP Origen</th>
                                <th>Usuario</th>
                                <th>Resultado</th>
                                <th>Fecha/Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentEvents as $event)
                                <tr>
                                    <td>
                                        <a href="{{ route('events.show', $event) }}" class="table-link">
                                            {{ $event->event_type?->label() ?? $event->getRawOriginal('event_type') }}
                                        </a>
                                    </td>
                                    <td><x-ip :ip="$event->source_ip" /></td>
                                    <td>{{ $event->username ?? '-' }}</td>
                                    <td>{{ $event->result ?? '-' }}</td>
                                    <td>{{ $event->occurred_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="table-empty">
                    <i class="bi bi-activity table-empty-icon"></i>
                    <p class="mb-0">No hay eventos recientes</p>
                    <small class="text-tertiary">Sin actividad registrada</small>
                </div>
            @endif
        </div>
    </div>
@endsection
