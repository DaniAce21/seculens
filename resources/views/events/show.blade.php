@extends('layouts.app')

@section('title', 'Evento #' . $event->id)
@section('page-title', 'Evento #' . $event->id)
@section('page-subtitle', 'Detalle del evento de seguridad')

@section('content')
    <div class="grid grid-2 mb-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Información del Evento</h3>
            </div>
            <div class="card-body">
                <div class="grid">
                    <div>
                        <span class="text-xs text-tertiary">ID</span>
                        <p>#{{ $event->id }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Tipo de Evento</span>
                        <p>{{ $event->event_type?->label() ?? $event->getRawOriginal('event_type') }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">IP Origen</span>
                        <p><x-ip :ip="$event->source_ip" /></p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Usuario</span>
                        <p>{{ $event->username ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Resultado</span>
                        <p>{{ $event->result ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Ocurrido</span>
                        <p>{{ $event->occurred_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Metadatos</h3>
            </div>
            <div class="card-body">
                <div class="grid">
                    <div>
                        <span class="text-xs text-tertiary">Creado</span>
                        <p>{{ $event->created_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Actualizado</span>
                        <p>{{ $event->updated_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <span class="text-xs text-tertiary">User agent</span>
                        <p style="word-break: break-word;">{{ $event->user_agent ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- La columna es 'metadata' (antes la vista leia 'raw_data', que no existe, y nunca se mostraba) --}}
    @if (! empty($event->metadata))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Datos Brutos</h3>
            </div>
            <div class="card-body">
                <pre
                    style="background: var(--color-bg-primary); padding: 1.25rem; border-radius: var(--radius-md); overflow-x: auto; font-size: 0.875rem; border: 1px solid var(--color-border);">{{ json_encode($event->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    @endif
@endsection
