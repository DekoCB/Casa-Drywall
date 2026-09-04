{{--
    Campanita de notificaciones (topbar). Datos ya calculados por el
    composer de `layouts.admin` (ver AppServiceProvider) en `$notificaciones`.
--}}
@php
    $categorias = [
        'comprobantes' => 'Comprobantes',
        'pagos' => 'Pagos',
        'inventario' => 'Inventario',
    ];
@endphp

<div class="notif-block" id="notifBlock">
    <button type="button" class="chip notif-trigger" id="notifTrigger" aria-haspopup="true" aria-expanded="false" title="Notificaciones">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        @if ($notificaciones['noLeidas'] > 0)
            <span class="notif-badge">{{ $notificaciones['noLeidas'] > 99 ? '99+' : $notificaciones['noLeidas'] }}</span>
        @endif
    </button>

    <div class="notif-panel" id="notifPanel">
        <div class="notif-panel-head">
            <h3>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                Notificaciones
            </h3>
            @if ($notificaciones['noLeidas'] > 0)
                <form method="POST" action="{{ route('admin.notificaciones.marcar-leidas') }}">
                    @csrf
                    <button type="submit" class="notif-marcar-leidas">Marcar todo como leído</button>
                </form>
            @endif
        </div>

        <div class="notif-tabs">
            <button type="button" class="notif-tab active" data-notif-tab="todas">
                Todas <span class="notif-tab-count">{{ $notificaciones['todas']->count() }}</span>
            </button>
            @foreach ($categorias as $clave => $etiqueta)
                <button type="button" class="notif-tab" data-notif-tab="{{ $clave }}">
                    {{ $etiqueta }}
                    @if (($notificaciones['porCategoria'][$clave] ?? collect())->isNotEmpty())
                        <span class="notif-tab-count">{{ $notificaciones['porCategoria'][$clave]->count() }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="notif-lista" data-notif-panel="todas">
            @forelse ($notificaciones['todas'] as $n)
                @include('partials.notificacion-item', ['n' => $n])
            @empty
                <div class="notif-vacio">Sin notificaciones por ahora</div>
            @endforelse
        </div>

        @foreach ($categorias as $clave => $etiqueta)
            <div class="notif-lista" data-notif-panel="{{ $clave }}" hidden>
                @forelse ($notificaciones['porCategoria'][$clave] ?? [] as $n)
                    @include('partials.notificacion-item', ['n' => $n])
                @empty
                    <div class="notif-vacio">Sin notificaciones por ahora</div>
                @endforelse
            </div>
        @endforeach
    </div>
</div>

<script>
(function () {
    const notifBlock   = document.getElementById('notifBlock');
    const notifTrigger = document.getElementById('notifTrigger');
    if (!notifBlock || !notifTrigger) return;

    notifTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const abierto = notifBlock.classList.toggle('abierto');
        notifTrigger.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });

    document.addEventListener('click', (e) => {
        if (!notifBlock.contains(e.target)) {
            notifBlock.classList.remove('abierto');
            notifTrigger.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            notifBlock.classList.remove('abierto');
            notifTrigger.setAttribute('aria-expanded', 'false');
        }
    });

    notifBlock.querySelectorAll('.notif-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            notifBlock.querySelectorAll('.notif-tab').forEach((t) => t.classList.remove('active'));
            tab.classList.add('active');

            const destino = tab.dataset.notifTab;
            notifBlock.querySelectorAll('.notif-lista').forEach((panel) => {
                panel.hidden = panel.dataset.notifPanel !== destino;
            });
        });
    });
})();
</script>
