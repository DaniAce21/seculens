@extends('layouts.app')

@section('title', 'Eventos')
@section('page-title', 'Explorador de Eventos')
@section('page-subtitle', 'Análisis y exploración de eventos de seguridad')

@section('content')
    {{-- ===== Filtros (formulario GET; se combinan entre si) ===== --}}
    <form method="GET" action="{{ route('events.index') }}" class="card mb-6">
        <div class="card-body filter-bar">
            <select name="type" class="form-select" aria-label="Tipo de evento">
                <option value="">Todos los tipos</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <input type="text" name="ip" class="form-input" placeholder="IP (o prefijo)" value="{{ $filters['ip'] ?? '' }}" aria-label="IP origen">
            <input type="text" name="username" class="form-input" placeholder="Usuario" value="{{ $filters['username'] ?? '' }}" aria-label="Usuario">
            <select name="result" class="form-select" aria-label="Resultado">
                <option value="">Cualquier resultado</option>
                @foreach ($results as $r)
                    <option value="{{ $r }}" @selected(($filters['result'] ?? '') === $r)>{{ $r }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2"><span class="text-tertiary">Desde</span>
                <input type="date" name="from" class="form-input" value="{{ $filters['from'] ?? '' }}">
            </label>
            <label class="flex items-center gap-2"><span class="text-tertiary">Hasta</span>
                <input type="date" name="to" class="form-input" value="{{ $filters['to'] ?? '' }}">
            </label>
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill"></i> Filtrar</button>
            @if (! empty($filters))
                <a href="{{ route('events.index') }}" class="btn btn-ghost"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </div>
        @if ($errors->any())
            <div class="card-footer text-tertiary">{{ $errors->first() }}</div>
        @endif
    </form>

    {{-- Exportacion por dia / semana / mes / año; respeta los filtros activos --}}
    @include('partials.export-form', ['route' => 'export.events', 'label' => 'eventos', 'extra' => $filters])

    <div class="card">
        <div class="card-body" style="padding: 0;">
            @if ($events->count() > 0)
                <div class="table-container" style="border: none; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tipo de Evento</th>
                                <th>IP Origen</th>
                                <th>Usuario</th>
                                <th>Resultado</th>
                                <th>Ocurrido</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $ev)
                                <tr>
                                    <td>#{{ $ev->id }}</td>
                                    <td>
                                        <a href="{{ route('events.show', $ev) }}" class="table-link">
                                            {{ $ev->event_type?->label() ?? $ev->getRawOriginal('event_type') }}
                                        </a>
                                    </td>
                                    {{-- <x-ip> marca las IPs de la lista de vigilancia --}}
                                    <td><x-ip :ip="$ev->source_ip" /></td>
                                    <td>{{ $ev->username ?? '-' }}</td>
                                    <td>{{ $ev->result ?? '-' }}</td>
                                    <td>{{ $ev->occurred_at->format('Y-m-d H:i:s') }}</td>
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
                    <i class="bi bi-activity table-empty-icon"></i>
                    <h3 style="margin-bottom: 0.5rem;">No hay eventos registrados</h3>
                    <p class="mb-0 text-tertiary">No se han registrado eventos de seguridad</p>
                </div>
            @endif
        </div>
    </div>
@endsection
