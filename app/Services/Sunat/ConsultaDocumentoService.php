<?php

namespace App\Services\Sunat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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
            $datos = $this->primeraRespuesta([
                'https://api.apis.net.pe/v2/sunat/ruc',
                'https://api.apis.net.pe/v1/ruc',
            ], ['numero' => $ruc]);

            if ($datos === null) {
                return null;
            }

            return [
                'razon_social' => $datos['razonSocial'] ?? $datos['nombre'] ?? '',
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
            $datos = $this->primeraRespuesta([
                'https://api.apis.net.pe/v2/reniec/dni',
                'https://api.apis.net.pe/v1/dni',
            ], ['numero' => $dni]);

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

    /**
     * v2 tiene el padrón más actualizado (v1 no encuentra varios DNI
     * recientes, ej. los que empiezan en 7) y no limita tan rápido; v1 queda
     * de respaldo por si v2 se cae o tampoco tiene el documento.
     */
    private function primeraRespuesta(array $urls, array $query): ?array
    {
        foreach ($urls as $url) {
            $datos = $this->peticion($url, $query);

            if (! empty($datos)) {
                return $datos;
            }
        }

        return null;
    }

    private function peticion(string $url, array $query): ?array
    {
        try {
            $token = config('services.apisperu.token');

            // Sin token, apis.net.pe limita las consultas por minuto y
            // responde 429 seguido: se reintenta en vez de dejar al vendedor
            // con el error a la primera.
            $respuesta = Http::timeout(8)
                ->when($token, fn ($http) => $http->withToken($token))
                ->retry(3, 1200, function (\Throwable $e) {
                    return $e instanceof ConnectionException
                        || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 504], true));
                }, throw: false)
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
