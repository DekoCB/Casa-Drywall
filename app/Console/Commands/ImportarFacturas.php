<?php

namespace App\Console\Commands;

use App\Models\Factura;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Trae a la base las facturas que el original guardaba en JSON:
 * `facturas_extra.json` (los datos), `facturas_canceladas.json` (qué números
 * están anulados) y `facturas_detalle.json` (el desglose de productos).
 */
class ImportarFacturas extends Command
{
    protected $signature = 'rentaltech:importar-facturas {--forzar : Reescribe las facturas ya existentes}';

    protected $description = 'Importa las facturas pendientes desde los JSON del proyecto original';

    public function handle(): int
    {
        $base = storage_path('app/galonaje');

        $facturas = $this->leerJson("{$base}/facturas_extra.json");
        $canceladas = $this->leerJson("{$base}/facturas_canceladas.json");
        $detalles = $this->leerJson("{$base}/facturas_detalle.json");

        if ($facturas === []) {
            $this->error('No se encontró facturas_extra.json o está vacío.');

            return self::FAILURE;
        }

        $nuevas = 0;
        $actualizadas = 0;
        $omitidas = 0;

        foreach ($facturas as $f) {
            $numero = trim((string) ($f['numero'] ?? ''));

            if ($numero === '') {
                continue;
            }

            $existente = Factura::where('numero', $numero)->first();

            if ($existente && ! $this->option('forzar')) {
                $omitidas++;

                continue;
            }

            // El detalle se guarda con una clave sin guiones ni puntos.
            $clave = 'det_'.preg_replace('/[^a-zA-Z0-9]/', '', $numero);

            $datos = [
                'numero'          => $numero,
                'doc'             => trim((string) ($f['doc'] ?? '')),
                'guia_remision'   => trim((string) ($f['guia_remision'] ?? '')),
                'emision'         => $this->fecha($f['emision'] ?? ''),
                'vencimiento'     => $this->fecha($f['vencimiento'] ?? ''),
                'importe'         => (float) ($f['importe'] ?? 0),
                'tc'              => (float) ($f['tc'] ?? 0),
                'galones'         => (float) ($f['galones'] ?? 0),
                'producto'        => trim((string) ($f['producto'] ?? '')),
                'cliente'         => trim((string) ($f['cliente'] ?? '')),
                'cancelado'       => in_array($numero, $canceladas, true),
                'estado_manual'   => in_array($f['estado_manual'] ?? '', Factura::ESTADOS, true) ? ($f['estado_manual'] ?? '') : '',
                'productos_lista' => $detalles[$clave] ?? ($f['productos_lista'] ?? []),
            ];

            if ($existente) {
                $existente->update($datos);
                $actualizadas++;
            } else {
                Factura::create($datos);
                $nuevas++;
            }
        }

        $this->info("Facturas importadas: {$nuevas} nuevas, {$actualizadas} actualizadas, {$omitidas} ya existían.");

        $total = Factura::count();
        $activas = Factura::where('cancelado', false)->where('estado_manual', '!=', 'pagada')->get();

        // El total en soles se redondea al final, no factura por factura.
        $this->line(sprintf(
            'En la base: %d facturas · $ %s · S/ %s · %s GL',
            $total,
            number_format((float) $activas->sum('importe'), 2),
            number_format($activas->sum(fn (Factura $f) => (float) $f->importe * (float) $f->tc), 2),
            number_format((float) Factura::sum('galones'), 2)
        ));

        return self::SUCCESS;
    }

    /** @return array<mixed> */
    private function leerJson(string $ruta): array
    {
        if (! is_file($ruta)) {
            return [];
        }

        return json_decode((string) file_get_contents($ruta), true) ?: [];
    }

    /** El original guarda las fechas como dd/mm/aaaa. */
    private function fecha(string $valor): string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return now()->format('Y-m-d');
        }

        return str_contains($valor, '/')
            ? Carbon::createFromFormat('d/m/Y', $valor)->format('Y-m-d')
            : Carbon::parse($valor)->format('Y-m-d');
    }
}
