@props(['activa'])

@php
    $pestanas = [
        'productos' => ['ruta' => 'admin.productos.index',     'ico' => '📦', 'txt' => 'Productos'],
        'almacenes' => ['ruta' => 'admin.productos.almacenes', 'ico' => '🏠', 'txt' => 'Almacenes'],
    ];
@endphp

<nav class="prod-tabs">
    @foreach ($pestanas as $clave => $tab)
        <a href="{{ route($tab['ruta']) }}"
           class="prod-tab @if($activa === $clave) is-activa @endif"
           @if($activa === $clave) aria-current="page" @endif>
            <span class="prod-tab-ico">{{ $tab['ico'] }}</span>
            <span class="prod-tab-txt">{{ $tab['txt'] }}</span>
        </a>
    @endforeach
</nav>
