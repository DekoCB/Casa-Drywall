{{--
    Un ítem del sidebar (con o sin submenú). Extraído de layouts/admin.blade.php
    para poder reutilizar exactamente el mismo módulo (p. ej. "Ventas") en el
    panel de un rol con acceso parcial (ver resources/views/ventas/index.blade.php),
    sin duplicar el submenú a mano en dos lugares.

    'roles' en un ítem limita quién lo ve; si no se define, se asume que es
    solo de admin — así ningún ítem existente cambia de visibilidad sin que
    alguien lo marque a propósito. Un hijo del submenu hereda el 'roles' de
    su padre si no define el suyo propio (p. ej. "Cajas" lo pisa a propósito
    para excluir a Ventas aunque el resto del grupo POS sí lo vea).
--}}
@if (in_array(auth()->user()->rol, $item['roles'] ?? ['admin']))
    @if (! empty($item['submenu']))
        @php
            $rutasHijas = collect($item['submenu'])->flatMap(fn ($sub) => $sub['active'] ?? [$sub['route'] ?? null])->filter()->all();
            $rutaActual = Route::currentRouteName();
            $padreActivo = in_array($rutaActual, $item['active'] ?? [$item['route']]);
            $grupoActivo = $padreActivo || in_array($rutaActual, $rutasHijas);
        @endphp
        <div class="mi-grupo @if($grupoActivo) abierto @endif">
            <div class="mi-padre-fila">
                <a href="{{ route($item['route']) }}" class="mi @if($padreActivo) active @endif" title="{{ $item['label'] }}">
                    {!! $item['icon'] !!}
                    <span>{{ $item['label'] }}</span>
                </a>
                <button type="button" class="mi-flecha-btn" data-toggle-submenu aria-label="Expandir {{ $item['label'] }}">
                    <svg class="mi-flecha" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            </div>
            <div class="mi-submenu">
                @foreach ($item['submenu'] as $sub)
                    @if (isset($sub['divider']))
                        <div class="mi-divider">{{ $sub['divider'] }}</div>
                    @elseif (in_array(auth()->user()->rol, $sub['roles'] ?? $item['roles'] ?? ['admin']))
                        @php
                            $subActivo = isset($sub['active'])
                                ? in_array($rutaActual, $sub['active'])
                                : $rutaActual === $sub['route']
                                    && collect($sub['query'] ?? [])->every(fn ($v, $k) => (string) request()->query($k, '') === (string) $v);
                        @endphp
                        <div class="mi-hijo-fila">
                            <a href="{{ route($sub['route'], $sub['query'] ?? []) }}" class="mi mi-hijo @if($subActivo) active @endif">
                                <span>{{ $sub['label'] }}</span>
                            </a>
                            @if (! empty($sub['crearRoute']))
                                <a href="{{ route($sub['crearRoute'], $sub['crearQuery'] ?? []) }}" class="mi-crear-pill" title="Crear {{ $sub['label'] }}">+ Crear</a>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @else
        <a href="{{ route($item['route']) }}"
           class="mi @if(in_array(Route::currentRouteName(), $item['active'] ?? [$item['route']])) active @endif"
           title="{{ $item['label'] }}">
            {!! $item['icon'] !!}
            <span>{{ $item['label'] }}</span>
            @isset($item['badge'])
                <span class="badge {{ $item['badge_class'] }}">{{ $item['badge'] }}</span>
            @endisset
        </a>
    @endif
@endif
