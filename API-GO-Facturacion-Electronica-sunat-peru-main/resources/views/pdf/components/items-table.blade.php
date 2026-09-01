{{-- PDF Items Table Component --}}
{{-- Props: $detalles, $format --}}
@php
    $maxFilas = in_array($format, ['a5', 'A5']) ? 8 : 15;
    $contador = count($detalles);
@endphp

@if (in_array($format, ['a4', 'A4', 'a5', 'A5']))
    {{-- A4/A5 Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th>Nº</th>
                <th>CÓDIGO</th>
                <th>DESCRIPCIÓN</th>
                <th>UNIDAD</th>
                <th>CANT.</th>
                <th>P. UNIT.</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            {{-- Items reales --}}
            @foreach ($detalles as $index => $detalle)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $detalle['codigo'] ?? '' }}</td>
                    <td>{{ $detalle['descripcion'] ?? '' }}</td>
                    <td>{{ $detalle['unidad'] ?? 'NIU' }}</td>
                    <td>{{ number_format($detalle['cantidad'] ?? 0, 2) }}</td>
                    <td>{{ number_format($detalle['mto_precio_unitario'] ?? 0, 2) }}</td>
                    <td>{{ number_format(($detalle['cantidad'] ?? 0) * ($detalle['mto_precio_unitario'] ?? 0), 2) }}
                    </td>
                </tr>
            @endforeach

            {{-- Filas vacías --}}
            @for ($i = $contador; $i < $maxFilas; $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endfor
        </tbody>
    </table>
@else
    {{-- Ticket Items (58mm/80mm) - NUEVO DISEÑO SIMPLE --}}
    <div class="items-section">
        <div style="border-bottom: 1px solid #000; padding-bottom: 2px; margin-bottom: 2px; font-weight: bold; font-size: {{ $format === '80mm' ? '11px' : '10px' }}; text-align: center;">
            --- PRODUCTOS ---
        </div>

        @foreach ($detalles as $index => $detalle)
            <div style="margin-bottom: 3px; padding-bottom: 2px; border-bottom: 1px dashed #999; font-size: {{ $format === '80mm' ? '10px' : '9px' }};">
                {{-- Descripción --}}
                <div style="font-weight: bold; margin-bottom: 1px;">
                    {{ $detalle['descripcion'] ?? '' }}
                </div>

                {{-- Detalles en una línea --}}
                <div>
                    {{ number_format($detalle['cantidad'] ?? 0, 2) }} x {{ number_format($detalle['mto_precio_unitario'] ?? 0, 2) }} = {{ number_format(($detalle['cantidad'] ?? 0) * ($detalle['mto_precio_unitario'] ?? 0), 2) }}
                </div>
            </div>
        @endforeach
    </div>
@endif
