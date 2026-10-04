{{--
    Grafico de columnas para series temporales (una sola serie).

    Uso: <x-column-chart :series="[['day' => '2026-10-01', 'total' => 12], ...]" />
    Cada punto puede traer 'label' (texto del eje, p. ej. "14h" o "10");
    si no, se usa la fecha 'day' en formato dd/mm.

    Cada columna lleva un tooltip nativo (title) con fecha y valor, y debajo
    se rotulan solo algunas fechas para que no se solapen.
--}}
@props(['series' => [], 'unit' => 'eventos'])

@php
    $max = max(1, ...array_map(fn ($p) => (int) $p['total'], $series ?: [['total' => 0]]));
    $count = count($series);
    // Rotular como mucho ~7 fechas en el eje X.
    $labelEvery = max(1, (int) ceil($count / 7));
@endphp

<div class="column-chart" role="img" aria-label="Serie temporal de {{ $unit }}, máximo {{ $max }}">
    <div class="column-chart-max text-tertiary">{{ number_format($max) }}</div>
    <div class="column-chart-plot">
        @foreach ($series as $i => $point)
            <div class="column-chart-col" title="{{ $point['label'] ?? \Carbon\Carbon::parse($point['day'])->format('d/m/Y') }}: {{ number_format($point['total']) }} {{ $unit }}">
                <span class="column-chart-bar" style="height: {{ round($point['total'] / $max * 100, 1) }}%;"></span>
            </div>
        @endforeach
    </div>
    <div class="column-chart-axis">
        @foreach ($series as $i => $point)
            <span>{{ $i % $labelEvery === 0 ? ($point['label'] ?? \Carbon\Carbon::parse($point['day'])->format('d/m')) : '' }}</span>
        @endforeach
    </div>
</div>
