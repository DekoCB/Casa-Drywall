<?php

use App\Models\Cliente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hasta ahora Ventas solo enlazaba el comprobante a una ficha de Clientes que
 * ya existiera: quien compraba con un DNI/RUC nuevo quedaba en la venta pero
 * no en Clientes, y en la siguiente compra el vendedor no lo encontraba.
 * Se registran esos clientes a partir de su comprobante más reciente y se
 * enlazan sus ventas — mismo criterio que `Cliente::registrarDesdeComprobante()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ventas = DB::table('ventas')
            ->whereNull('cliente_id')
            ->whereNotNull('razonsocial')
            ->where('razonsocial', '!=', '')
            ->orderByDesc('id')
            ->get(['id', 'n_ruc', 'cliente_ruc', 'razonsocial', 'cliente_direccion', 'cliente_distrito', 'cliente_provincia', 'cliente_departamento']);

        foreach ($ventas as $venta) {
            $cliente = Cliente::registrarDesdeComprobante($venta->n_ruc ?: $venta->cliente_ruc, $venta->razonsocial, [
                'direccion'    => $venta->cliente_direccion,
                'distrito'     => $venta->cliente_distrito,
                'provincia'    => $venta->cliente_provincia,
                'departamento' => $venta->cliente_departamento,
            ]);

            if ($cliente) {
                DB::table('ventas')->where('id', $venta->id)->update(['cliente_id' => $cliente->id]);
            }
        }
    }

    public function down(): void
    {
        // Solo agrega fichas y enlaces; no hay nada que deshacer con seguridad.
    }
};
