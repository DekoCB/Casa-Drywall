@if ($paginator->hasPages())
    <nav class="paginacion" role="navigation" aria-label="Paginación">
        <div class="pg-resumen">
            Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
            de {{ number_format($paginator->total()) }}
        </div>

        <div class="pg-enlaces">
            {{-- Anterior --}}
            @if ($paginator->onFirstPage())
                <span class="pg-btn pg-off" aria-disabled="true" aria-label="Anterior">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pg-btn" rel="prev" aria-label="Anterior">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
            @endif

            {{-- Números de página --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pg-puntos">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $pagina => $url)
                        @if ($pagina == $paginator->currentPage())
                            <span class="pg-btn pg-activa" aria-current="page">{{ $pagina }}</span>
                        @else
                            <a href="{{ $url }}" class="pg-btn">{{ $pagina }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Siguiente --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pg-btn" rel="next" aria-label="Siguiente">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            @else
                <span class="pg-btn pg-off" aria-disabled="true" aria-label="Siguiente">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
