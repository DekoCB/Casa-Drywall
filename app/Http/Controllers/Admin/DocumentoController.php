<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\Venta;
use App\Services\Sunat\ConsultaDocumentoService;
use Illuminate\Http\JsonResponse;

class DocumentoController extends Controller
{
    public function __construct(private readonly ConsultaDocumentoService $consulta) {}

    public function buscar(string $tipo, string $numero): JsonResponse
    {
        return $tipo === 'ruc'
            ? $this->buscarRuc($numero)
            : $this->buscarDni($numero);
    }

    private function buscarRuc(string $ruc): JsonResponse
    {
        if (! preg_match('/^\d{11}$/', $ruc)) {
            return response()->json(['ok' => false, 'error' => 'El RUC debe tener 11 dígitos'], 422);
        }

        $proveedor = Proveedor::where('ruc', $ruc)->first();

        if ($proveedor) {
            return response()->json([
                'ok' => true,
                'origen' => 'local',
                'datos' => [
                    'razon_social' => $proveedor->razon_social,
                    'direccion' => $proveedor->direccion,
                    'distrito' => $proveedor->distrito,
                    'provincia' => $proveedor->provincia,
                    'departamento' => $proveedor->departamento,
                ],
            ]);
        }

        $cliente = Cliente::where('numero_documento', $ruc)->first();

        if ($cliente) {
            return response()->json([
                'ok' => true,
                'origen' => 'local',
                'datos' => [
                    'cliente_id' => $cliente->id,
                    'razon_social' => $cliente->nombre_empresa ?: $cliente->nombres,
                    'direccion' => $cliente->direccion,
                    'distrito' => $cliente->distrito,
                    'provincia' => $cliente->provincia,
                    'departamento' => $cliente->departamento,
                ],
            ]);
        }

        $datos = $this->consulta->consultarRuc($ruc);

        if ($datos === null) {
            return $this->consulta->noEncontrado()
                ? response()->json(['ok' => false, 'error' => 'No se encontró ese RUC en SUNAT. Verifica el número o completa los datos a mano.'], 404)
                : response()->json(['ok' => false, 'error' => 'El servicio de SUNAT no respondió (demasiadas consultas). Espera unos segundos e intenta de nuevo — reintentar varias veces seguidas no ayuda.'], 502);
        }

        return response()->json(['ok' => true, 'origen' => 'sunat', 'datos' => $datos]);
    }

    private function buscarDni(string $dni): JsonResponse
    {
        if (! preg_match('/^\d{8}$/', $dni)) {
            return response()->json(['ok' => false, 'error' => 'El DNI debe tener 8 dígitos'], 422);
        }

        $cliente = Cliente::where('numero_documento', $dni)->first();

        if ($cliente) {
            return response()->json([
                'ok' => true,
                'origen' => 'local',
                'datos' => [
                    'cliente_id' => $cliente->id,
                    'nombre_completo' => $cliente->nombres,
                    'direccion' => $cliente->direccion,
                    'distrito' => $cliente->distrito,
                    'provincia' => $cliente->provincia,
                    'departamento' => $cliente->departamento,
                ],
            ]);
        }

        // Comprobantes emitidos antes de que Ventas registrara al cliente
        // solo: el nombre ya quedó en la venta, no hace falta ir a RENIEC.
        $venta = Venta::where(fn ($q) => $q->where('n_ruc', $dni)->orWhere('cliente_ruc', $dni))
            ->whereNotNull('razonsocial')
            ->where('razonsocial', '!=', '')
            ->latest('id')
            ->first();

        if ($venta) {
            return response()->json([
                'ok' => true,
                'origen' => 'local',
                'datos' => [
                    'nombre_completo' => $venta->razonsocial,
                    'direccion' => $venta->cliente_direccion,
                    'distrito' => $venta->cliente_distrito,
                    'provincia' => $venta->cliente_provincia,
                    'departamento' => $venta->cliente_departamento,
                ],
            ]);
        }

        $datos = $this->consulta->consultarDni($dni);

        if ($datos === null) {
            return $this->consulta->noEncontrado()
                ? response()->json(['ok' => false, 'error' => 'No se encontró ese DNI en RENIEC. Verifica el número o escribe el nombre a mano.'], 404)
                : response()->json(['ok' => false, 'error' => 'RENIEC no respondió (demasiadas consultas). Espera unos segundos e intenta de nuevo — reintentar varias veces seguidas no ayuda.'], 502);
        }

        return response()->json(['ok' => true, 'origen' => 'reniec', 'datos' => $datos]);
    }
}
