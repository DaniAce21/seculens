@extends('layouts.app')

@section('title', 'Usuarios')
@section('page-title', 'Gestión de Usuarios')
@section('page-subtitle', 'Administración de usuarios y roles del sistema')

@section('content')
    <div class="card">
        <div class="card-body" style="padding: 0;">
            @if ($users->count() > 0)
                <div class="table-container" style="border: none; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Creado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $u)
                                <tr>
                                    <td>{{ $u->name }}</td>
                                    <td>{{ $u->email }}</td>
                                    <td><span class="badge badge-{{ strtolower($u->role->value) }}">{{ $u->role->label() }}</span></td>
                                    {{-- Sin columnas de estado/ultimo login: no hay login ni bloqueo (modo single-user) --}}
                                    <td>{{ $u->created_at->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $users->links() }}
                </div>
            @else
                <div class="table-empty">
                    <i class="bi bi-people table-empty-icon"></i>
                    <h3 style="margin-bottom: 0.5rem;">No hay usuarios</h3>
                    <p class="mb-0 text-tertiary">No existen usuarios registrados en el sistema</p>
                </div>
            @endif
        </div>
    </div>
@endsection
