{{--
    Tipografía elegida en Configuración > Estilos y temas (siempre activa,
    con "Casa Drywall" como valor por defecto) y color de acento (solo si
    el negocio guardó uno explícito — si no, se respeta lo que ya haya
    puesto `partials.brand-color` según la empresa activa). Por eso este
    include va DESPUÉS de brand-color: una elección explícita acá gana.
--}}
@php
    $estiloSistema = \App\Models\EstiloSistema::actual();
    $tipografia = $estiloSistema->tipografia();
@endphp
<link href="https://fonts.googleapis.com/css2?{{ $tipografia['google'] }}&display=swap" rel="stylesheet">
<style>
    :root {
        --font-sans: '{{ $tipografia['sans'] }}', ui-sans-serif, system-ui, -apple-system, sans-serif;
        --font-display: '{{ $tipografia['display'] }}', var(--font-sans);
        --font-mono: '{{ $tipografia['sans'] }}', ui-monospace, 'SFMono-Regular', Menlo, monospace;
    }
@if ($estiloSistema->exists)
    @php $paleta = $estiloSistema->paleta(); @endphp
    :root {
        --brand: {{ $paleta['brand'] }};
        --brand-2: {{ $paleta['brand2'] }};
        --brand-bg: {{ $paleta['bg_claro'] }};
        --on-brand: {{ $paleta['on_brand'] }};
        /* --info(-bg) copia a --brand(-bg) en _shell.css (mismo valor
           literal a propósito) — se repite acá para que siga a juego. */
        --info: {{ $paleta['brand'] }};
        --info-bg: {{ $paleta['bg_claro'] }};
    }
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) {
            --brand-bg: {{ $paleta['bg_oscuro'] }};
            --info-bg: {{ $paleta['bg_oscuro'] }};
        }
    }
    :root[data-theme="dark"] {
        --brand-bg: {{ $paleta['bg_oscuro'] }};
        --info-bg: {{ $paleta['bg_oscuro'] }};
    }
@endif
</style>
