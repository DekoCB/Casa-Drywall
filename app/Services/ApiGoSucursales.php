<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador hacia el módulo de Sucursales (Branches) de la API-GO — mismo
 * patrón que `ApiGoEmisionService::peticion()`: nunca lanza excepción,
 * loggea y devuelve null/false si la API-GO no responde.
 */
class ApiGoSucursales
{
    public function listar(): array
    {
        $respuesta = $this->peticion('get', '/branches', ['company_id' => $this->companyId()]);

        return $respuesta['data'] ?? [];
    }

    public function crear(array $datos): ?array
    {
        $respuesta = $this->peticion('post', '/branches', $datos + ['company_id' => $this->companyId()]);

        return $respuesta['success'] ?? false ? $respuesta['data'] : null;
    }

    public function actualizar(int $branchId, array $datos): ?array
    {
        $respuesta = $this->peticion('put', "/branches/{$branchId}", $datos);

        return $respuesta['success'] ?? false ? $respuesta['data'] : null;
    }

    public function desactivar(int $branchId): bool
    {
        $respuesta = $this->peticion('delete', "/branches/{$branchId}");

        return (bool) ($respuesta['success'] ?? false);
    }

    public function activar(int $branchId): bool
    {
        $respuesta = $this->peticion('post', "/branches/{$branchId}/activate");

        return (bool) ($respuesta['success'] ?? false);
    }

    public function actualizarSeries(int $branchId, array $series): ?array
    {
        return $this->actualizar($branchId, $series);
    }

    private function companyId(): int
    {
        return (int) config('services.api_go.company_id');
    }

    private function peticion(string $metodo, string $ruta, array $datos = []): ?array
    {
        try {
            $baseUrl = rtrim(config('services.api_go.base_url'), '/');
            $token = config('services.api_go.token');

            $http = Http::timeout(15)->when($token, fn ($http) => $http->withToken($token));

            $respuesta = match ($metodo) {
                'get' => $http->get("{$baseUrl}{$ruta}", $datos),
                'put' => $http->put("{$baseUrl}{$ruta}", $datos),
                'delete' => $http->delete("{$baseUrl}{$ruta}", $datos),
                default => $http->post("{$baseUrl}{$ruta}", $datos),
            };

            $cuerpo = $respuesta->json();

            if ($respuesta->failed()) {
                Log::warning('Llamada a API-GO (sucursales) devolvió error', [
                    'ruta' => $ruta,
                    'status' => $respuesta->status(),
                    'body' => $cuerpo,
                ]);
            }

            return $cuerpo;
        } catch (\Throwable $e) {
            Log::warning('Llamada a API-GO (sucursales) lanzó excepción', [
                'ruta' => $ruta,
                'mensaje' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
