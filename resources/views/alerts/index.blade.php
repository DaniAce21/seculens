@extends('layouts.app')

@section('title', 'Alertas')
@section('page-title', 'Alertas de Seguridad')
@section('page-subtitle', 'Gestión y seguimiento de amenazas detectadas')

@section('content')
    {{-- Filtros (el controlador ya los admitia, pero no habia formulario) --}}
    <form method="GET" action="{{ route('alerts.index') }}" class="card mb-6">
        <div class="card-body filter-bar">
            <select name="severity" class="form-select" aria-label="Severidad">
                <option value="">Todas las severidades</option>
                @foreach (\App\Enums\AlertSeverity::cases() as $sev)
                    <option value="{{ $sev->value }}" @selected(request('severity') === $sev->value)>{{ $sev->label() }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select" aria-label="Estado">
                <option value="">Todos los estados</option>
                @foreach (\App\Enums\AlertStatus::cases() as $st)
                    <option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill"></i> Filtrar</button>
            @if (request()->filled('severity') || request()->filled('status'))
                <a href="{{ route('alerts.index') }}" class="btn btn-ghost"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </div>
    </form>

    {{-- Exportacion por dia / semana / mes / año --}}
    @include('partials.export-form', ['route' => 'export.alerts', 'label' => 'alertas'])

    <div class="card">
        <div class="card-body" style="padding: 0;">
            @if ($alerts->count() > 0)
                <div class="table-container" style="border: none; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Severidad</th>
                                <th>Estado</th>
                                <th>IP Origen</th>
                                <th>Eventos</th>
                                <th>Primera Detección</th>
                                <th>Última Detección</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($alerts as $alert)
                                <tr>
                                    <td>
                                        <a href="{{ route('alerts.show', $alert) }}" class="table-link">
                                            {{ $alert->title }}
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
                                    <td><x-ip :ip="$alert->source_ip" /></td>
                                    <td>{{ $alert->security_events_count ?? 0 }}</td>
                                    <td>{{ $alert->first_seen_at->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ $alert->last_seen_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $alerts->links() }}
                </div>
            @else
                <div class="table-empty">
                    <i class="bi bi-shield-check table-empty-icon" style="color: var(--color-success);"></i>
                    <h3 style="margin-bottom: 0.5rem;">No hay alertas registradas</h3>
                    <p class="mb-0 text-tertiary">No se han detectado amenazas en este momento</p>
                </div>
            @endif
        </div>
    </div>
@endsection
