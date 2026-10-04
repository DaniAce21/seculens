@extends('layouts.app')

@section('title', 'Incidente #' . $incident->id)
@section('page-title', $incident->title)
@section('page-subtitle', 'Seguimiento y gestión del incidente')

{{--
    Variables (IncidentController@show): $incident (con assignee, alerts y
    notes.author), permisos $canChangeStatus / $canAssign / $canAddNote /
    $canManageAlerts, $analysts (posibles responsables) y $availableAlerts
    (alertas activas aun no vinculadas).
--}}

@section('content')
    {{-- Errores de validacion de cualquiera de los formularios --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle-fill alert-icon"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="grid grid-2 mb-6">
        {{-- ===== Datos generales ===== --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Información General</h3>
            </div>
            <div class="card-body">
                <div class="grid">
                    <div>
                        <span class="text-xs text-tertiary">ID</span>
                        <p>#{{ $incident->id }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Estado</span>
                        <p><span class="badge badge-{{ strtolower($incident->status->value) }}">{{ $incident->status->label() }}</span></p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Prioridad</span>
                        <p><span class="badge badge-{{ strtolower($incident->priority->value) }}">{{ $incident->priority->label() }}</span></p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Responsable</span>
                        <p>{{ $incident->assignee?->name ?? 'Sin asignar' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Cronologia ===== --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Cronología</h3>
            </div>
            <div class="card-body">
                <div class="grid">
                    <div>
                        <span class="text-xs text-tertiary">Abierto</span>
                        <p>{{ $incident->opened_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Resuelto</span>
                        <p>{{ $incident->resolved_at?->format('Y-m-d H:i:s') ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Cerrado</span>
                        <p>{{ $incident->closed_at?->format('Y-m-d H:i:s') ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-tertiary">Última actualización</span>
                        <p>{{ $incident->updated_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Acciones: estado y responsable ===== --}}
    @if ($canChangeStatus || $canAssign)
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-sliders"></i> Gestión</h3>
            </div>
            <div class="card-body filter-bar">
                @if ($canChangeStatus)
                    @php($transitions = $incident->status->allowedTransitions())
                    @if (empty($transitions))
                        <span class="text-tertiary">Incidente cerrado: su estado ya no puede cambiar.</span>
                    @else
                        {{-- Solo se ofrecen las transiciones validas de la maquina de estados --}}
                        <form method="POST" action="{{ route('incidents.status', $incident) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="form-select" aria-label="Nuevo estado">
                                @foreach ($transitions as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-right-circle"></i> Cambiar estado</button>
                        </form>
                    @endif
                @endif

                @if ($canAssign)
                    <form method="POST" action="{{ route('incidents.assign', $incident) }}" class="flex items-center gap-2" style="margin-left: auto;">
                        @csrf
                        @method('PATCH')
                        <select name="assigned_to" class="form-select" aria-label="Responsable">
                            <option value="">Sin asignar</option>
                            @foreach ($analysts as $analyst)
                                <option value="{{ $analyst->id }}" @selected($incident->assigned_to === $analyst->id)>
                                    {{ $analyst->name }} ({{ $analyst->role->label() }})
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-ghost"><i class="bi bi-person-check"></i> Asignar</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @if ($incident->description)
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">Descripción</h3>
            </div>
            <div class="card-body">
                <p style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ $incident->description }}</p>
            </div>
        </div>
    @endif

    {{-- ===== Alertas vinculadas ===== --}}
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-link-45deg"></i> Alertas vinculadas ({{ $incident->alerts->count() }})</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            @if ($incident->alerts->isEmpty())
                <div class="table-empty"><p class="mb-0 text-tertiary">No hay alertas vinculadas a este incidente</p></div>
            @else
                <div class="table-container" style="border: none; border-radius: 0;">
                    <table class="table">
                        <thead><tr><th>Alerta</th><th>Severidad</th><th>IP origen</th><th>Vinculada</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($incident->alerts as $alert)
                                <tr>
                                    <td><a href="{{ route('alerts.show', $alert) }}" class="table-link">{{ $alert->title }}</a></td>
                                    <td><span class="badge badge-{{ strtolower($alert->severity->value) }}">{{ $alert->severity->label() }}</span></td>
                                    <td><x-ip :ip="$alert->source_ip" /></td>
                                    <td>{{ $alert->pivot->linked_at ? \Carbon\Carbon::parse($alert->pivot->linked_at)->format('Y-m-d H:i') : '-' }}</td>
                                    <td>
                                        @if ($canManageAlerts)
                                            <form method="POST" action="{{ route('incidents.alerts', $incident) }}">
                                                @csrf
                                                <input type="hidden" name="alert_id" value="{{ $alert->id }}">
                                                <input type="hidden" name="detach" value="1">
                                                <button type="submit" class="btn btn-ghost btn-sm" title="Desvincular" data-confirm="¿Desvincular esta alerta del incidente?">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if ($canManageAlerts && $availableAlerts->isNotEmpty())
            <div class="card-footer">
                <form method="POST" action="{{ route('incidents.alerts', $incident) }}" class="filter-bar">
                    @csrf
                    <select name="alert_id" class="form-select" style="flex: 1;" aria-label="Alerta a vincular">
                        @foreach ($availableAlerts as $alert)
                            <option value="{{ $alert->id }}">#{{ $alert->id }} · {{ $alert->severity->label() }} · {{ $alert->title }} ({{ $alert->source_ip }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-ghost"><i class="bi bi-link"></i> Vincular alerta</button>
                </form>
            </div>
        @endif
    </div>

    {{-- ===== Notas de la investigacion (orden cronologico) ===== --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-journal-text"></i> Notas de la investigación ({{ $incident->notes->count() }})</h3>
        </div>
        <div class="card-body">
            @forelse ($incident->notes as $note)
                <div class="note">
                    <div class="note-meta text-tertiary text-xs">
                        <strong>{{ $note->author?->name ?? 'Usuario eliminado' }}</strong>
                        · {{ $note->created_at->format('Y-m-d H:i') }}
                        @if ($note->incident_status_at_time)
                            · estado: {{ $note->incident_status_at_time->label() }}
                        @endif
                    </div>
                    <p class="note-body">{{ $note->body }}</p>
                </div>
            @empty
                <p class="text-tertiary mb-4">Todavía no hay notas.</p>
            @endforelse

            @if ($canAddNote)
                <form method="POST" action="{{ route('incidents.notes', $incident) }}" class="mt-note">
                    @csrf
                    <textarea name="body" class="form-textarea" style="width: 100%;" placeholder="Hallazgos, acciones realizadas, próximos pasos..." required minlength="3" maxlength="5000">{{ old('body') }}</textarea>
                    <button type="submit" class="btn btn-primary" style="margin-top: 0.75rem;"><i class="bi bi-plus-lg"></i> Añadir nota</button>
                </form>
            @endif
        </div>
    </div>
@endsection
