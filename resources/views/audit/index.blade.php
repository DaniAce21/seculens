@extends('layouts.app')

@section('title', 'Auditoría')
@section('page-title', 'Bitácora de Auditoría')
@section('page-subtitle', 'Registro inmutable de todas las acciones del sistema')

@section('content')
    <div class="card">
        <div class="card-body" style="padding: 0;">
            @if ($logs->count() > 0)
                <div class="table-container" style="border: none; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Acción</th>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>IP</th>
                                <th>Recurso</th>
                                <th>ID Recurso</th>
                                <th>Fecha/Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td>
                                        {{--
                                            Color por tipo: acciones que modifican datos en ambar,
                                            lecturas/accesos en azul. (Antes usaba badge-<accion>,
                                            clases que no existian, y la insignia salia sin estilo.)
                                        --}}
                                        <span class="badge {{ $log->action->isMutation() ? 'badge-medium' : 'badge-new' }}">
                                            {{ $log->action->label() }}
                                        </span>
                                    </td>
                                    <td>{{ $log->user?->name ?? '-' }}</td>
                                    {{-- user_role se guarda como texto plano (sin cast); se traduce con el enum --}}
                                    <td>{{ $log->user_role ? (\App\Enums\UserRole::tryFrom($log->user_role)?->label() ?? $log->user_role) : '-' }}</td>
                                    <td><x-ip :ip="$log->ip_address" /></td>
                                    {{-- La tabla usa subject_type/subject_id (relacion polimorfica), no resource_type/resource_id --}}
                                    <td>{{ $log->subject_type ? class_basename($log->subject_type) : '-' }}</td>
                                    <td>{{ $log->subject_id ?? '-' }}</td>
                                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $logs->links() }}
                </div>
            @else
                <div class="table-empty">
                    <i class="bi bi-journal-text table-empty-icon"></i>
                    <h3 style="margin-bottom: 0.5rem;">Sin registros de auditoría</h3>
                    <p class="mb-0 text-tertiary">Aún no se han registrado acciones en el sistema</p>
                </div>
            @endif
        </div>
    </div>
@endsection
