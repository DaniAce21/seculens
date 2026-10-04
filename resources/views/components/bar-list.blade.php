{{--
    Grafico de barras horizontales (una sola serie), dibujado con HTML/CSS.

    Uso:
      <x-bar-list :items="[['label' => 'Alta', 'value' => 4, 'color' => 'var(--color-danger)'], ...]" />

    - 'color' es opcional (por defecto el color primario).
    - 'ip' => true muestra la etiqueta con el componente <x-ip> (marca vigiladas).
    - Sin JavaScript: el tooltip es el atributo title nativo del navegador.
--}}
@props(['items' => [], 'empty' => 'Sin datos'])

@php
    // Valor maximo de la serie (minimo 1 para no dividir entre cero).
    $max = max(1, ...array_map(fn ($i) => (int) $i['value'], $items ?: [['value' => 0]]));
@endphp

@if (collect($items)->sum('value') === 0)
    <p class="text-tertiary chart-empty">{{ $empty }}</p>
@else
    <ul class="bar-list" role="list">
        @foreach ($items as $item)
            <li class="bar-list-row" title="{{ strip_tags($item['label']) }}: {{ number_format($item['value']) }}">
                <span class="bar-list-label">
                    @if ($item['ip'] ?? false)
                        <x-ip :ip="$item['label']" />
                    @else
                        {{ $item['label'] }}
                    @endif
                </span>
                <span class="bar-list-track">
                    {{-- El ancho es proporcional al valor maximo de la serie --}}
                    <span class="bar-list-fill" style="width: {{ round($item['value'] / $max * 100, 1) }}%; background: {{ $item['color'] ?? 'var(--color-primary)' }};"></span>
                </span>
                <span class="bar-list-value">{{ number_format($item['value']) }}</span>
            </li>
        @endforeach
    </ul>
@endif
