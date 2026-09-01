@props(['titulo', 'subtitulo' => null])

<div class="page-header">
    <div class="page-title">
        <h2>{{ $titulo }}</h2>
        @isset($subtitulo)
            <p>{{ $subtitulo }}</p>
        @endisset
    </div>

    @isset($acciones)
        <div class="header-btns">{{ $acciones }}</div>
    @endisset
</div>
