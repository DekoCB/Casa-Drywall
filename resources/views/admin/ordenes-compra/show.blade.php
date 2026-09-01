@extends('layouts.admin')

@section('title', 'Orden ' . $orden->numero_orden)
@section('crumb', 'Compras & documentos')

@push('styles')
    @vite(['resources/css/modules/ordenes-compra.css'])
@endpush

@section('content')
@php
    $productos = $orden->productos ?? [];
    $lineasMerch = $orden->merch ?? [];

    // El precio de las líneas vive con dos nombres según de qué formulario
    // salió la orden: el nuevo guarda `precio_unit_usd`, el viejo `precio`.
    $precioLinea = fn (array $p) => (float) ($p['precio_unit_usd'] ?? $p['precio'] ?? 0);

    $totalMerch = collect($lineasMerch)->sum(fn ($l) => ($l['cantidad'] ?? 0) * ($l['costo_unit'] ?? 0));
    $nombresMerch = \App\Models\Merch::whereIn('id', array_column($lineasMerch, 'merch_id'))->pluck('nombre', 'id');

    $estado = \Illuminate\Support\Str::lower(trim((string) $orden->estado));
    $claseEstado = match (true) {
        str_contains($estado, 'recib') => 'recibido',
        str_contains($estado, 'tráns') || str_contains($estado, 'trans') => 'transito',
        str_contains($estado, 'cancel') => 'cancelado',
        default => 'pendiente',
    };
@endphp

<div class="oc-wrapper ocm oc-hoja-wrap">

    <div class="oc-hoja">

        {{-- ══ Membrete ══ --}}
        <div class="ocd-membrete">
            <div>
                <span class="ocd-tipo">Orden de compra</span>
                <div class="ocd-emisor-nombre">{{ $orden->proveedor }}</div>
                <div class="ocd-emisor-dato">
                    RUC {{ $orden->ruc ?: '—' }}
                    @if ($orden->distrito) · {{ $orden->distrito }}, {{ $orden->departamento }} @endif
                </div>
            </div>

            <div class="ocd-membrete-der">
                <div class="ocd-numero">{{ $orden->numero_orden }}</div>
                <div class="ocd-fecha">{{ $orden->fecha?->translatedFormat('d \d\e F \d\e Y') }}</div>
            </div>

            <div class="ocd-membrete-pie">
                <div class="ocd-pie-dato">Para el cliente <strong>{{ $orden->cliente_ref ?: 'Sin asignar' }}</strong></div>
                <div class="ocd-pie-dato">Condición de pago <strong>{{ $orden->condicion_pago ?: 'Contado' }}</strong></div>
                <div class="ocd-pie-dato">Estado <strong><span class="ocm-estado {{ $claseEstado }}">{{ $orden->estado ?: 'Pendiente' }}</span></strong></div>
            </div>
        </div>

        <div class="ocd-cuerpo">

            {{-- ══ 1. Datos ══ --}}
            <div class="ocd-seccion">
                <span class="ocd-num">1</span>
                <div>
                    <div class="ocd-tit">Datos de la orden</div>
                    <div class="ocd-sub">Documentos, transporte y referencias</div>
                </div>
            </div>

            <div class="ocm-datos">
                <div>
                    <div class="ocm-dato-lbl">Factura</div>
                    <div class="ocm-dato-val {{ $orden->nro_factura ? '' : 'vacio' }}">{{ $orden->nro_factura ?: 'Pendiente' }}</div>
                </div>
                <div>
                    <div class="ocm-dato-lbl">Guía de remisión</div>
                    <div class="ocm-dato-val {{ $orden->nro_guia ? '' : 'vacio' }}">{{ $orden->nro_guia ?: 'Pendiente' }}</div>
                </div>
                <div>
                    <div class="ocm-dato-lbl">Empresa de transporte</div>
                    <div class="ocm-dato-val {{ $orden->empresa_transporte ? '' : 'vacio' }}">{{ $orden->empresa_transporte ?: 'Sin asignar' }}</div>
                </div>
                <div>
                    <div class="ocm-dato-lbl">Peso y bultos</div>
                    <div class="ocm-dato-val">{{ $orden->peso ?: '—' }} · {{ $orden->bultos ?: 0 }} bulto(s)</div>
                </div>
                <div>
                    <div class="ocm-dato-lbl">Teléfono</div>
                    <div class="ocm-dato-val {{ $orden->telefono ? '' : 'vacio' }}">{{ $orden->telefono ?: '—' }}</div>
                </div>
                <div>
                    <div class="ocm-dato-lbl">Correo</div>
                    <div class="ocm-dato-val {{ $orden->correo ? '' : 'vacio' }}">{{ $orden->correo ?: '—' }}</div>
                </div>
            </div>

            {{-- ══ 2. Productos ══ --}}
            <div class="ocd-seccion">
                <span class="ocd-num">2</span>
                <div>
                    <div class="ocd-tit">Productos</div>
                    <div class="ocd-sub">{{ count($productos) }} línea(s) de la orden</div>
                </div>
                <span class="ocd-etiqueta">Dólares</span>
            </div>

            @if ($productos)
                <div style="overflow-x:auto;">
                    <table class="ocm-tabla">
                        <thead>
                            <tr>
                                <th class="idx">#</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th class="num">Cantidad</th>
                                <th class="num">P. unitario</th>
                                <th class="num">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($productos as $i => $producto)
                            @php $precio = $precioLinea($producto); @endphp
                            <tr>
                                <td class="idx">{{ $i + 1 }}</td>
                                <td class="ocm-mono">{{ $producto['codigo'] ?? '—' }}</td>
                                <td>{{ $producto['descripcion'] ?? ($producto['nombre'] ?? '—') }}</td>
                                <td class="num">{{ number_format($producto['cantidad'] ?? 0) }}</td>
                                <td class="num">$ {{ number_format($precio, 2) }}</td>
                                <td class="num"><strong>$ {{ number_format(($producto['cantidad'] ?? 0) * $precio, 2) }}</strong></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p style="color:var(--ocm-suave);font-size:13px;">Esta orden no tiene líneas de producto.</p>
            @endif

            {{-- ══ 3. Merch ══ --}}
            @if ($lineasMerch)
                <div class="ocd-seccion">
                    <span class="ocd-num">3</span>
                    <div>
                        <div class="ocd-tit">Merch para clientes</div>
                        <div class="ocd-sub">
                            Ingresó al stock de Merch ·
                            <a href="{{ route('admin.merch.movimientos', ['orden' => $orden->id]) }}">ver movimientos</a>
                        </div>
                    </div>
                    <span class="ocd-etiqueta">Soles</span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="ocm-tabla">
                        <thead>
                            <tr>
                                <th class="idx">#</th>
                                <th>Artículo</th>
                                <th class="num">Cantidad</th>
                                <th class="num">Costo unitario</th>
                                <th class="num">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($lineasMerch as $i => $linea)
                            <tr>
                                <td class="idx">{{ $i + 1 }}</td>
                                <td>{{ $nombresMerch[$linea['merch_id'] ?? 0] ?? '— artículo eliminado —' }}</td>
                                <td class="num">{{ number_format($linea['cantidad'] ?? 0) }}</td>
                                <td class="num">S/ {{ number_format($linea['costo_unit'] ?? 0, 2) }}</td>
                                <td class="num"><strong>S/ {{ number_format(($linea['cantidad'] ?? 0) * ($linea['costo_unit'] ?? 0), 2) }}</strong></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- ══ Observaciones ══ --}}
            @if ($orden->observaciones)
                <div class="ocd-seccion">
                    <span class="ocd-num opcional">{{ $lineasMerch ? 4 : 3 }}</span>
                    <div>
                        <div class="ocd-tit">Observaciones</div>
                        <div class="ocd-sub">Notas registradas con la orden</div>
                    </div>
                </div>
                <p style="font-size:13.5px;color:var(--ocm-ink);line-height:1.6;">{{ $orden->observaciones }}</p>
            @endif

            {{-- ══ Cierre ══ --}}
            <div class="ocd-cierre">
                <div>
                    <div class="ocd-lleva-tit">Esta orden lleva</div>

                    <div class="ocd-lleva-item {{ $productos ? 'lleno' : '' }}">
                        <span class="ocd-lleva-punto"></span>
                        <span class="ocd-lleva-nombre">Productos</span>
                        <span class="ocd-lleva-det">
                            <span class="ocd-lleva-monto">$ {{ number_format($orden->total_usd, 2) }}</span>
                            <span class="ocd-lleva-sub">{{ count($productos) }} línea(s)</span>
                        </span>
                    </div>

                    <div class="ocd-lleva-item merch {{ $lineasMerch ? 'lleno' : '' }}">
                        <span class="ocd-lleva-punto"></span>
                        <span class="ocd-lleva-nombre">Merch</span>
                        <span class="ocd-lleva-det">
                            <span class="ocd-lleva-monto">S/ {{ number_format($totalMerch, 2) }}</span>
                            <span class="ocd-lleva-sub">{{ count($lineasMerch) }} artículo(s)</span>
                        </span>
                    </div>
                </div>

                <div class="ocd-totales">
                    <div class="ocd-tfila"><span>Total en dólares</span><strong>$ {{ number_format($orden->total_usd, 2) }}</strong></div>
                    <div class="ocd-tfila"><span>Tipo de cambio</span><strong>{{ number_format($orden->tc, 4) }}</strong></div>
                    <div class="ocd-tgran">
                        <span>Total en soles</span>
                        <b>S/ {{ number_format($orden->total_soles, 2) }}</b>
                    </div>
                    @if ($totalMerch > 0)
                        <div class="ocd-nota">
                            Más S/ {{ number_format($totalMerch, 2) }} de merch, registrado aparte como egreso de promoción.
                        </div>
                    @endif
                </div>
            </div>

        </div>{{-- /ocd-cuerpo --}}

        {{-- ══ Barra fija ══ --}}
        <div class="ocd-barra">
            <div class="ocd-barra-info">
                <div class="ocd-barra-lbl">Total de la orden</div>
                @php
                    $barraSoles = (float) $orden->total_soles + $totalMerch;
                    // El merch se paga en soles: se devuelve a dólares con el mismo tipo de cambio de la orden.
                    $barraUsd   = $orden->tc > 0 ? $barraSoles / $orden->tc : null;
                @endphp
                <div class="ocd-barra-total">S/ {{ number_format($barraSoles, 2) }}<span class="ocd-barra-usd">{{ $barraUsd !== null ? '$ '.number_format($barraUsd, 2) : '$ —' }}</span></div>
                <div class="ocd-barra-det">
                    {{ count($productos) }} producto(s){{ $lineasMerch ? ' · '.count($lineasMerch).' merch' : '' }}
                </div>
            </div>

            <div class="ocd-barra-acciones">
                <a href="{{ route('admin.ordenes-compra.index') }}" class="ocd-cancelar">← Volver al listado</a>
                <a href="{{ route('admin.ordenes-compra.excel', ['ids' => $orden->id]) }}" class="ocm-btn">Excel</a>
                <a href="{{ route('admin.ordenes-compra.edit', $orden) }}" class="ocd-guardar" style="text-decoration:none;display:inline-block;">Editar orden</a>
            </div>
        </div>

    </div>
</div>
@endsection
