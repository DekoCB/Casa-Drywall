<?php

namespace App\Services\Sunat;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta pública de RUC (SUNAT) y DNI (RENIEC) vía apis.net.pe.
 *
 * Vive en su propio namespace porque es el primer paso de un módulo de
 * facturación más amplio: más adelante un adaptador OSE para emisión
 * electrónica (facturas/boletas válidas ante SUNAT) puede convivir aquí
 * sin tocar esta clase.
 */
class ConsultaDocumentoService
{
    private const TTL_CACHE_SEGUNDOS = 86400;

    public function consultarRuc(string $ruc): ?array
    {
        if (! preg_match('/^\d{11}$/', $ruc)) {
            return null;
        }

        return Cache::remember("sunat:ruc:{$ruc}", self::TTL_CACHE_SEGUNDOS, function () use ($ruc) {
            $datos = $this->peticion('https://api.apis.net.pe/v1/ruc', ['numero' => $ruc]);

            if ($datos === null) {
                return null;
            }

            return [
                'razon_social' => $datos['nombre'] ?? '',
                'direccion'    => $datos['direccion'] ?? '',
                'distrito'     => $datos['distrito'] ?? '',
                'provincia'    => $datos['provincia'] ?? '',
                'departamento' => $datos['departamento'] ?? '',
                'estado'       => $datos['estado'] ?? '',
                'condicion'    => $datos['condicion'] ?? '',
            ];
        });
    }

    public function consultarDni(string $dni): ?array
    {
        if (! preg_match('/^\d{8}$/', $dni)) {
            return null;
        }

        return Cache::remember("sunat:dni:{$dni}", self::TTL_CACHE_SEGUNDOS, function () use ($dni) {
            $datos = $this->peticion('https://api.apis.net.pe/v1/dni', ['numero' => $dni]);

            if ($datos === null) {
                return null;
            }

            $nombres = $datos['nombres'] ?? '';
            $apellidoPaterno = $datos['apellidoPaterno'] ?? '';
            $apellidoMaterno = $datos['apellidoMaterno'] ?? '';

            return [
                'nombres'           => $nombres,
                'apellido_paterno'  => $apellidoPaterno,
                'apellido_materno'  => $apellidoMaterno,
                'nombre_completo'   => trim("{$nombres} {$apellidoPaterno} {$apellidoMaterno}"),
            ];
        });
    }

    private function peticion(string $url, array $query): ?array
    {
        try {
            $token = config('services.apisperu.token');

            $respuesta = Http::timeout(8)
                ->when($token, fn ($http) => $http->withToken($token))
                ->get($url, $query);

            if ($respuesta->failed()) {
                Log::warning('Consulta SUNAT/RENIEC falló', ['url' => $url, 'status' => $respuesta->status()]);

                return null;
            }

            return $respuesta->json();
        } catch (\Throwable $e) {
            Log::warning('Consulta SUNAT/RENIEC lanzó excepción', ['url' => $url, 'mensaje' => $e->getMessage()]);

            return null;
        }
    }
}
