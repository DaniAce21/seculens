@extends('layouts.app')

@section('title', 'IPs vigiladas')
@section('page-title', 'Lista de IPs en Vigilancia')
@section('page-subtitle', 'IPs reincidentes o bloqueadas, marcadas en todas las tablas')

@section('content')
    {{-- Errores de validacion del formulario --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle-fill alert-icon"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    {{-- ===== Alta manual ===== --}}
    @if (auth()->user()->canWrite())
        <form method="POST" action="{{ route('watchlist.store') }}" class="card mb-6">
            @csrf
            <div class="card-body flex items-center gap-4" style="flex-wrap: wrap;">
                <input type="text" name="ip" class="form-input" placeholder="Ej. 185.234.219.101" value="{{ old('ip') }}" required aria-label="Dirección IP">
                <select name="level" class="form-select" aria-label="Nivel">
                    @foreach ($levels as $value => $label)
                        <option value="{{ $value }}" @selected(old('level') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="text" name="reason" class="form-input" style="flex: 1; min-width: 200px;" placeholder="Motivo (opcional)" value="{{ old('reason') }}" aria-label="Motivo">
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Añadir</button>
            </div>
        </form>
    @endif

    <div class="grid grid-2 mb-6">
        {{-- ===== IPs vigiladas ===== --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-eye-fill"></i> IPs en la lista ({{ $watched->count() }})</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                @if ($watched->isEmpty())
                    <div class="table-empty">
                        <i class="bi bi-eye-slash table-empty-icon"></i>
                        <p class="mb-0">Todavía no hay IPs vigiladas</p>
                    </div>
                @else
                    <div class="table-container" style="border: none; border-radius: 0;">
                        <table class="table">
                            <thead>
                                <tr><th>IP</th><th>Motivo</th><th>Añadida</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($watched as $w)
                                    <tr>
                                        <td><x-ip :ip="$w->ip" /></td>
                                        <td>{{ $w->reason ?? '-' }}</td>
                                        <td>
                                            {{ $w->created_at->format('Y-m-d') }}
                                            <small class="text-tertiary d-block">{{ $w->author?->name }}</small>
                                        </td>
                                        <td>
                                            @if (auth()->user()->canWrite())
                                                {{-- data-confirm lo gestiona public/js/app.js --}}
                                                <form method="POST" action="{{ route('watchlist.destroy', $w) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-ghost btn-sm" data-confirm="¿Retirar {{ $w->ip }} de la lista?" title="Retirar">
                                                        <i class="bi bi-trash"></i>
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
        </div>

        {{-- ===== Sugerencias automaticas ===== --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-lightbulb-fill"></i> Reincidentes sugeridas</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                @if (empty($suggestions))
                    <div class="table-empty">
                        <i class="bi bi-check-circle table-empty-icon" style="color: var(--color-success);"></i>
                        <p class="mb-0">No hay IPs reincidentes sin vigilar</p>
                        <small class="text-tertiary">Se sugieren IPs con 3 o más logins fallidos / alertas IDS</small>
                    </div>
                @else
                    <div class="table-container" style="border: none; border-radius: 0;">
                        <table class="table">
                            <thead>
                                <tr><th>IP</th><th>Logins fallidos</th><th>Alertas IDS</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($suggestions as $s)
                                    <tr>
                                        <td><code>{{ $s['ip'] }}</code></td>
                                        <td>{{ $s['failed_logins'] }}</td>
                                        <td>{{ $s['ids_alerts'] }}</td>
                                        <td>
                                            @if (auth()->user()->canWrite())
                                                <form method="POST" action="{{ route('watchlist.store') }}">
                                                    @csrf
                                                    <input type="hidden" name="ip" value="{{ $s['ip'] }}">
                                                    <input type="hidden" name="level" value="watch">
                                                    <input type="hidden" name="reason" value="Reincidente: {{ $s['failed_logins'] }} logins fallidos, {{ $s['ids_alerts'] }} alertas IDS">
                                                    <button type="submit" class="btn btn-ghost btn-sm"><i class="bi bi-eye"></i> Vigilar</button>
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
        </div>
    </div>
@endsection
