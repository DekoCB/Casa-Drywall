<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Services\LectorExcel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CobranzaController extends Controller
{
    /**
     * Las cobranzas anteriores al año 2000 son basura del importador y quedan
     * fuera de todo el módulo, igual que en `administrador/cobranzas.php`.
     */
    private const ANIO_MINIMO = 2000;

    /** Umbral de mora grave y cuántas se listan en el aviso. */
    private const DIAS_ALERTA = 90;

    private const TOPE_ALERTAS = 20;

    public function index(Request $request): View
    {
        $filtros = [
            'q'      => trim((string) $request->query('q', '')),
            'estado' => (string) $request->query('estado', ''),
            'tipo'   => (string) $request->query('tipo', ''),
            'mes'    => (string) $request->query('mes', ''),    // YYYY-MM
            'desde'  => (string) $request->query('desde', ''),
            'hasta'  => (string) $request->query('hasta', ''),
        ];

        $cobranzas = $this->filtradas($filtros)
            ->orderBy('fecha_vencimiento')
            ->paginate(50)
            ->withQueryString();

        $totalesFiltro = $this->filtradas($filtros)
            ->selectRaw('COUNT(*) AS n')
            ->selectRaw('COALESCE(SUM(monto_total), 0) AS total')
            ->selectRaw('COALESCE(SUM(monto_pagado), 0) AS pagado')
            ->selectRaw('COALESCE(SUM(monto_pendiente), 0) AS pendiente')
            ->first();

        $resumen = $this->vigentes()
            ->selectRaw('COALESCE(SUM(monto_pendiente), 0) AS pendiente')
            ->selectRaw("COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) AS n_pendientes")
            ->selectRaw("COUNT(CASE WHEN estado = 'pagada' THEN 1 END) AS n_pagadas")
            ->selectRaw("COUNT(CASE WHEN estado = 'pendiente' AND fecha_vencimiento < CURDATE()
                                    THEN 1 END) AS n_vencidas")
            ->first();

        $clientes = Cliente::orderBy('nombres')->get(['id', 'nombres', 'numero_documento']);
        $morosas  = $this->morosasGraves();

        return view('admin.cobranzas.index', [
            'cobranzas'        => $cobranzas,
            'filtros'          => $filtros,
            'tipos'            => Cobranza::TIPOS,
            'totalRegistros'   => $cobranzas->total(),
            'totalFiltroMonto'     => (float) $totalesFiltro->total,
            'totalFiltroPagado'    => (float) $totalesFiltro->pagado,
            'totalFiltroPendiente' => (float) $totalesFiltro->pendiente,
            'clienteResumen'   => $this->resumenClienteBuscado($filtros['q']),
            'totalPendiente'   => (float) $resumen->pendiente,
            'nVencidas'        => (int) $resumen->n_vencidas,
            'nPendientes'      => (int) $resumen->n_pendientes,
            'nPagadas'         => (int) $resumen->n_pagadas,
            'clientes'         => $clientes,
            'alertas90'        => $morosas->take(self::TOPE_ALERTAS),
            'nAlertas90'       => $morosas->count(),
        ]);
    }

    // ── Consultas de apoyo ──────────────────────────────────────────────────

    /**
     * Cobranzas pendientes con 90 días o más de atraso, de la más vencida a la
     * menos. Alimenta el aviso que salta al entrar al módulo.
     */
    private function morosasGraves(): Collection
    {
        return $this->vigentes()
            ->where('estado', 'pendiente')
            ->whereRaw('YEAR(fecha_vencimiento) BETWEEN 2000 AND 2100')
            ->whereRaw('DATEDIFF(CURDATE(), fecha_vencimiento) >= ?', [self::DIAS_ALERTA])
            ->selectRaw('id, tipo, numero, cliente_nombre, monto_pendiente')
            ->selectRaw('DATEDIFF(CURDATE(), fecha_vencimiento) AS dias')
            ->orderByDesc('dias')
            ->get();
    }

    /**
     * Cuando la búsqueda apunta a un único cliente (por nombre), arma su
     * tarjeta de resumen: deuda total y facturas pendientes, sin importar el
     * filtro de fecha o estado aplicado en la tabla.
     */
    private function resumenClienteBuscado(string $q): ?array
    {
        if ($q === '') {
            return null;
        }

        $nombres = $this->vigentes()
            ->whereNotNull('cliente_nombre')
            ->where('cliente_nombre', '!=', '')
            ->where('cliente_nombre', 'like', "%{$q}%")
            ->distinct()
            ->pluck('cliente_nombre');

        if ($nombres->count() !== 1) {
            return null;
        }

        $nombre = $nombres->first();

        $fila = $this->vigentes()
            ->where('cliente_nombre', $nombre)
            ->selectRaw('COALESCE(SUM(monto_pendiente), 0) AS deuda')
            ->selectRaw("COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) AS n_pendientes")
            ->first();

        return [
            'nombre'      => $nombre,
            'deuda'       => (float) $fila->deuda,
            'nPendientes' => (int) $fila->n_pendientes,
        ];
    }

    /** Base del módulo: todo lo que no sea basura de fechas del importador. */
    private function vigentes(): Builder
    {
        return Cobranza::whereYear('fecha_emision', '>=', self::ANIO_MINIMO);
    }

    private function filtradas(array $filtros): Builder
    {
        return $this->vigentes()
            ->when($filtros['q'] !== '', function ($query) use ($filtros) {
                $query->where(function ($q) use ($filtros) {
                    $q->where('numero', 'like', "%{$filtros['q']}%")
                        ->orWhere('cliente_nombre', 'like', "%{$filtros['q']}%");
                });
            })
            ->when($filtros['estado'] !== '', fn ($q) => $q->where('estado', $filtros['estado']))
            ->when($filtros['tipo'] !== '', fn ($q) => $q->where('tipo', $filtros['tipo']))
            ->when($filtros['mes'] !== '', fn ($q) => $q->whereRaw("DATE_FORMAT(fecha_emision, '%Y-%m') = ?", [$filtros['mes']]))
            ->when($filtros['desde'] !== '', fn ($q) => $q->whereDate('fecha_emision', '>=', $filtros['desde']))
            ->when($filtros['hasta'] !== '', fn ($q) => $q->whereDate('fecha_emision', '<=', $filtros['hasta']));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $duplicada = Cobranza::where('numero', $datos['numero'])
            ->where('tipo', $datos['tipo'])
            ->exists();

        if ($duplicada) {
            return back()->with('error', "Ya existe una cobranza {$datos['tipo']} con el número {$datos['numero']}.");
        }

        $cobranza = new Cobranza($datos + ['usuario_id' => $request->user()->id]);
        $cobranza->recalcularEstado();
        $cobranza->save();

        return back()->with('mensaje', 'Cobranza registrada correctamente.');
    }

    public function update(Request $request, Cobranza $cobranza): RedirectResponse
    {
        $cobranza->fill($this->validar($request));
        $cobranza->recalcularEstado();
        $cobranza->save();

        return back()->with('mensaje', 'Cobranza actualizada correctamente.');
    }

    public function destroy(Cobranza $cobranza): RedirectResponse
    {
        $cobranza->delete();

        return back()->with('mensaje', 'Cobranza eliminada.');
    }

    /** Abona un pago parcial o total sobre la cobranza. */
    public function registrarPago(Request $request, Cobranza $cobranza): RedirectResponse
    {
        $datos = $request->validate([
            'monto_pago' => ['required', 'numeric', 'min:0.01'],
            'fecha_pago' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ]);

        if ($datos['monto_pago'] > (float) $cobranza->monto_pendiente + 0.009) {
            return back()->with('error', 'El monto del pago supera el saldo pendiente.');
        }

        DB::transaction(function () use ($cobranza, $datos) {
            // El historial de pagos lee esta marca, así que el formato importa:
            // «[Pago YYYY-MM-DD] S/importe - método».
            $nota = sprintf(
                "\n[Pago %s] S/%s - %s",
                Carbon::parse($datos['fecha_pago'])->toDateString(),
                number_format($datos['monto_pago'], 2),
                filled($datos['observaciones']) ? $datos['observaciones'] : 'Sin especificar'
            );

            $cobranza->monto_pagado = (float) $cobranza->monto_pagado + (float) $datos['monto_pago'];
            $cobranza->fecha_pago = $datos['fecha_pago'];
            $cobranza->observaciones = ($cobranza->observaciones ?? '').$nota;
            $cobranza->recalcularEstado();
            $cobranza->save();
        });

        return back()->with('mensaje', 'Pago de S/ '.number_format($datos['monto_pago'], 2).' registrado correctamente.');
    }

    public function formImportar(): View
    {
        return view('admin.cobranzas.importar');
    }

    /**
     * Importa cobranzas desde un .xlsx.
     * Columnas esperadas: tipo, número, emisión, vencimiento, cliente, monto, pagado.
     */
    public function importar(Request $request, LectorExcel $lector): RedirectResponse
    {
        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx']]);

        $filas = $lector->leer($request->file('archivo')->getRealPath());

        if (isset($filas['error'])) {
            return back()->with('error', $filas['error']);
        }

        $importadas = 0;
        $omitidas = 0;

        foreach (array_slice($filas, 1) as $fila) {
            $numero = trim((string) ($fila[1] ?? ''));

            if ($numero === '') {
                continue;
            }

            $tipo = $this->normalizarTipo((string) ($fila[0] ?? ''));

            if (Cobranza::where('numero', $numero)->where('tipo', $tipo)->exists()) {
                $omitidas++;

                continue;
            }

            $cobranza = new Cobranza([
                'tipo' => $tipo,
                'numero' => $numero,
                'fecha_emision' => $this->fechaExcel($fila[2] ?? null) ?? now()->toDateString(),
                'fecha_vencimiento' => $this->fechaExcel($fila[3] ?? null),
                'cliente_nombre' => trim((string) ($fila[4] ?? '')) ?: null,
                'monto_total' => (float) ($fila[5] ?? 0),
                'monto_pagado' => (float) ($fila[6] ?? 0),
                'usuario_id' => $request->user()->id,
            ]);

            $cobranza->recalcularEstado();
            $cobranza->save();

            $importadas++;
        }

        return redirect()->route('admin.cobranzas.index')
            ->with('mensaje', "Importación completada: {$importadas} cobranzas nuevas, {$omitidas} omitidas por duplicado.");
    }

    /**
     * Traduce lo que venga en la columna de tipo del Excel a los códigos que
     * usa el sistema (FT, BV, NC, OT). Lo no reconocido cae en "OT".
     */
    private function normalizarTipo(string $valor): string
    {
        $valor = mb_strtoupper(trim($valor));

        if (isset(Cobranza::TIPOS[$valor])) {
            return $valor;
        }

        return match (true) {
            str_contains($valor, 'FACTURA') => 'FT',
            str_contains($valor, 'BOLETA')  => 'BV',
            str_contains($valor, 'CREDITO'), str_contains($valor, 'CRÉDITO') => 'NC',
            default => 'OT',
        };
    }

    /** Convierte una celda de fecha de Excel (serial o texto) a Y-m-d. */
    private function fechaExcel(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        // Excel guarda las fechas como días desde 1899-12-30.
        if (is_numeric($valor)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $valor)->toDateString();
        }

        try {
            return Carbon::parse($valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', Rule::in(array_keys(Cobranza::TIPOS))],
            'numero' => ['required', 'string', 'max:50'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date', 'after_or_equal:fecha_emision'],
            'cliente_nombre' => ['required', 'string', 'max:255'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'monto_total' => ['required', 'numeric', 'min:0'],
            'monto_pagado' => ['nullable', 'numeric', 'min:0', 'lte:monto_total'],
            'observaciones' => ['nullable', 'string'],
        ]);
    }
}
