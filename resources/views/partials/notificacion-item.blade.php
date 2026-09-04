{{-- $n: una notificación de CentroNotificaciones (clave/categoria/titulo/detalle/fecha/url/leido) --}}
<a href="{{ $n['url'] }}" class="notif-item @if(! $n['leido']) no-leido @endif">
    <span class="notif-item-dot" aria-hidden="true"></span>
    <div class="notif-item-body">
        <div class="notif-item-titulo">{{ $n['titulo'] }}</div>
        <div class="notif-item-detalle">{{ $n['detalle'] }}</div>
        <div class="notif-item-fecha">{{ $n['fecha']->diffForHumans() }}</div>
    </div>
</a>
