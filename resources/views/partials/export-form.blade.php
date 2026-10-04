{{--
    Formulario de exportacion CSV por periodo.

    Uso: @include('partials.export-form', ['route' => 'export.incidents', 'label' => 'incidentes'])
    Opcional: 'extra' => [...] parametros adicionales (p. ej. filtros activos)
    que se envian como campos ocultos para que el CSV respete esos filtros.

    Es un formulario GET normal (sin JavaScript, compatible con la CSP):
    al enviarlo, el navegador descarga el archivo y la pagina no cambia.
--}}
<form method="GET" action="{{ route($route) }}" class="card mb-6 export-form">
    @foreach ($extra ?? [] as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div class="card-body flex items-center gap-4" style="flex-wrap: wrap;">
        <strong style="margin-right: auto;">
            <i class="bi bi-download"></i>
            Exportar {{ $label }} (CSV / Excel)
            @if (! empty($extra))
                <small class="text-tertiary">— con los filtros aplicados</small>
            @endif
        </strong>

        <label class="flex items-center gap-2">
            <span class="text-tertiary">Periodo</span>
            <select name="period" class="form-select" required>
                <option value="day">Día</option>
                <option value="week">Semana</option>
                <option value="month" selected>Mes</option>
                <option value="year">Año</option>
            </select>
        </label>

        {{-- Fecha de referencia: se exporta el día/semana/mes/año que la contiene --}}
        <label class="flex items-center gap-2" title="Se exporta el periodo que contiene esta fecha">
            <span class="text-tertiary">Que incluya</span>
            <input type="date" name="date" class="form-input" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
        </label>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-file-earmark-spreadsheet"></i>
            Descargar
        </button>
    </div>
</form>
