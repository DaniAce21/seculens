@extends('layouts.app')

@section('title', 'Incidentes')
@section('page-title', 'Incidentes de Seguridad')
@section('page-subtitle', 'Investigación y respuesta a incidentes')

@section('content')
    {{-- Exportacion por dia / semana / mes / año --}}
    @include('partials.export-form', ['route' => 'export.incidents', 'label' => 'incidentes'])

    <div class="card">
        <div class="card-body" style="padding: 0;">
            @if ($incidents->count() > 0)
                <div class="table-container" style="border: none; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Estado</th>
                                <th>Prioridad</th>
                                <th>Responsable</th>
                                <th>Abierto</th>
                                <th>Cerrado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($incidents as $i)
                                <tr>
                                    <td>
                                        <a href="{{ route('incidents.show', $i) }}" class="table-link">
                                            {{ $i->title }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ strtolower($i->status->value) }}">
                                            {{ $i->status->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ strtolower($i->priority->value) }}">
                                            {{ $i->priority->label() }}
                                        </span>
                                    </td>
                                    {{-- (Antes mostraba una columna 'category' que no existe en la tabla) --}}
                                    <td>{{ $i->assignee?->name ?? 'Sin asignar' }}</td>
                                    <td>{{ $i->opened_at->format('Y-m-d H:i') }}</td>
                                    <td>{{ $i->closed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $incidents->links() }}
                </div>
            @else
                <div class="table-empty">
                    <i class="bi bi-clipboard-check table-empty-icon" style="color: var(--color-success);"></i>
                    <h3 style="margin-bottom: 0.5rem;">No hay incidentes registrados</h3>
                    <p class="mb-0 text-tertiary">No existen incidentes abiertos o registrados</p>
                </div>
            @endif
        </div>
    </div>
@endsection
