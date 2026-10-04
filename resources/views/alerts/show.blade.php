@extends('layouts.app')

@section('title', 'Alerta #' . $alert->id)
@section('page-title', $alert->title)
@section('page-subtitle', 'Detalle completo de la alerta de seguridad')

@section('content')
    <div class="grid grid-2 mb-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Información General</h3>
            </div>
            <div class="card-body">
                <div class="grid">
                    <div>
                        <span class="text-xs text-tertiary">ID</span>
                        <p>#{{ $alert->id }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Severidad</span>
                        <p><span class="badge badge-{{ strtolower($alert->severity->value) }}">{{ $alert->severity->label() }}</span></p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Estado</span>
                        <p><span class="badge badge-{{ strtolower($alert->status->value) }}">{{ $alert->status->label() }}</span></p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">IP Origen</span>
                        <p><x-ip :ip="$alert->source_ip" /></p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Regla de Detección</span>
                        <p>{{ $alert->detection_rule }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Total de Eventos</span>
                        <p>{{ $events->total() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Cronología</h3>
            </div>
            <div class="card-body">
                <div class="grid">
                    <div>
                        <span class="text-xs text-tertiary">Primera Detección</span>
                        <p>{{ $alert->first_seen_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Última Detección</span>
                        <p>{{ $alert->last_seen_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Creada</span>
                        <p>{{ $alert->created_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Actualizada</span>
                        <p>{{ $alert->updated_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($alert->description)
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">Descripción</h3>
            </div>
            <div class="card-body">
                <p style="color: var(--color-text-secondary);">{{ $alert->description }}</p>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Evidencia - Eventos Asociados</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            @if ($events->count() > 0)
                <div class="table-container" style="border: none; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>IP Origen</th>
                                <th>Usuario</th>
                                <th>Resultado</th>
                                <th>Ocurrido</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $ev)
                                <tr>
                                    <td>{{ $ev->event_type?->label() ?? $ev->getRawOriginal('event_type') }}</td>
                                    <td><x-ip :ip="$ev->source_ip" /></td>
                                    <td>{{ $ev->username ?? '-' }}</td>
                                    <td>{{ $ev->result ?? '-' }}</td>
                                    <td>{{ $ev->occurred_at }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $events->links() }}
                </div>
            @else
                <div class="table-empty">
                    <i class="bi bi-inbox table-empty-icon"></i>
                    <p class="mb-0">No hay eventos asociados a esta alerta</p>
                </div>
            @endif
        </div>
    </div>
@endsection
