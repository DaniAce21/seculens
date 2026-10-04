{{--
    Paginacion de SecuLens (registrada en AppServiceProvider con
    Paginator::defaultView).

    Laravel usa por defecto una plantilla de Tailwind, pero la app no carga
    Tailwind: las flechas salian como SVG gigantes y el texto en ingles.
    Esta plantilla usa las clases .pagination/.page-item/.page-link que ya
    define public/css/app.css.
--}}
@if ($paginator->hasPages())
    <nav class="pagination-wrapper" aria-label="Paginación">
        <span class="pagination-info text-tertiary text-xs">
            Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </span>

        <ul class="pagination">
            {{-- Anterior --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true"><span class="page-link" aria-label="Anterior">‹</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Anterior">‹</a></li>
            @endif

            {{-- Numeros de pagina ("..." cuando hay muchas) --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Siguiente --}}
            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Siguiente">›</a></li>
            @else
                <li class="page-item disabled" aria-disabled="true"><span class="page-link" aria-label="Siguiente">›</span></li>
            @endif
        </ul>
    </nav>
@endif
