<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Proveedor;
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
            return response()->json(['ok' => false, 'error' => 'No se encontró el RUC o el servicio no respondió'], 502);
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
                'datos' => ['nombre_completo' => $cliente->nombres],
            ]);
        }

        $datos = $this->consulta->consultarDni($dni);

        if ($datos === null) {
            return response()->json(['ok' => false, 'error' => 'No se encontró el DNI o el servicio no respondió'], 502);
        }

        return response()->json(['ok' => true, 'origen' => 'reniec', 'datos' => $datos]);
    }
}
