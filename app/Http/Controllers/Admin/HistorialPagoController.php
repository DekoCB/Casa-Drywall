<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cobranza;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Historial de pagos por cliente y mes.
 *
 * Transcrito de `administrador/historial_pagos.php`. Cada abono queda anotado
 * dentro de `cobranzas.observaciones` con la marca `[Pago YYYY-MM-DD] método`,
 * así que los pagos se extraen de ahí; una cobranza puede tener varios.
 */
class HistorialPagoController extends Controller
{
    /** Marca con la que se anota cada abono dentro de las observaciones. */
    private const PATRON_PAGO = '/\[Pago\s+(\d{4}-\d{2}-\d{2})\]\s*([^\n\[]*)/u';

    public function index(Request $request): View
    {
        $cliente = trim((string) $request->query('cliente', ''));
        $anio    = (int) $request->query('anio', now()->year);
        $mes     = (int) $request->query('mes', 0);

        $cobranzas = Cobranza::whereYear('fecha_emision', '>=', 2000)
            ->where(fn ($q) => $q->whereNotNull('observaciones')->orWhereNotNull('fecha_pago'))
            ->when($cliente !== '', fn ($q) => $q->where('cliente_nombre', 'like', "%{$cliente}%"))
            ->orderBy('cliente_nombre')
            ->orderByDesc('fecha_pago')
            ->orderByDesc('fecha_emision')
            ->limit(1000)
            ->get();

        $pagos = $this->extraerPagos($cobranzas)
            ->when($anio > 0, fn ($c) => $c->where('anio', $anio))
            ->when($mes > 0, fn ($c) => $c->where('mes', $mes))
            ->sortByDesc('fecha')
            ->values();

        // cliente → periodo (YYYY-MM) → pagos de ese mes
        $agrupado = $pagos
            ->groupBy('cliente')
            ->map(fn (Collection $delCliente) => $delCliente->groupBy('periodo'))
            ->sortKeys();

        return view('admin.historial-pagos.index', [
            'agrupado'   => $agrupado,
            'cliente'    => $cliente,
            'anioSel'    => $anio,
            'mesSel'     => $mes,
            'anios'      => $this->aniosConPagos(),
            'nPagos'     => $pagos->count(),
            'nClientes'  => $agrupado->count(),
        ]);
    }

    /**
     * Convierte cada cobranza en sus abonos sueltos. Si no hay notas pero sí
     * una fecha de pago con importe, se cuenta como un único abono.
     */
    private function extraerPagos(Collection $cobranzas): Collection
    {
        $pagos = collect();

        foreach ($cobranzas as $cobranza) {
            $notas = [];
            preg_match_all(self::PATRON_PAGO, (string) $cobranza->observaciones, $notas, PREG_SET_ORDER);

            if ($notas !== []) {
                foreach ($notas as $nota) {
                    $pagos->push($this->armarPago($cobranza, $nota[1], trim($nota[2]) ?: 'No especificado'));
                }

                continue;
            }

            if ($cobranza->fecha_pago && (float) $cobranza->monto_pagado > 0) {
                $pagos->push($this->armarPago(
                    $cobranza,
                    $cobranza->fecha_pago->toDateString(),
                    'No especificado',
                    (float) $cobranza->monto_pagado
                ));
            }
        }

        return $pagos;
    }

    private function armarPago(Cobranza $cobranza, string $fecha, string $metodo, ?float $monto = null): array
    {
        $dia = Carbon::parse($fecha);

        return [
            'cobranza_id' => $cobranza->id,
            'cliente'     => $cobranza->cliente_nombre ?: 'Sin cliente',
            'fecha'       => $fecha,
            'anio'        => $dia->year,
            'mes'         => $dia->month,
            'periodo'     => $dia->format('Y-m'),
            'etiquetaMes' => ucfirst($dia->translatedFormat('F Y')),
            'documento'   => trim($cobranza->tipo.' '.$cobranza->numero),
            'metodo'      => $metodo,
            'monto'       => $monto,
            'estado'      => $cobranza->estado,
        ];
    }

    /** Años en los que hay algún pago registrado. */
    private function aniosConPagos(): array
    {
        $anios = Cobranza::whereNotNull('fecha_pago')
            ->whereYear('fecha_pago', '>=', 2020)
            ->selectRaw('DISTINCT YEAR(fecha_pago) AS anio')
            ->orderByDesc('anio')
            ->pluck('anio')
            ->map(fn ($a) => (int) $a)
            ->all();

        return $anios ?: [now()->year];
    }
}
