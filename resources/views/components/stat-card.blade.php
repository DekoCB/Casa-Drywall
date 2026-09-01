@props(['valor', 'etiqueta', 'icono' => null])

<div class="stat-card">
    @isset($icono)
        <div class="stat-icon">{!! $icono !!}</div>
    @endisset
    <div class="stat-value">{{ $valor }}</div>
    <div class="stat-label">{{ $etiqueta }}</div>
</div>
