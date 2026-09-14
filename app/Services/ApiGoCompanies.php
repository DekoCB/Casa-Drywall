<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador hacia el módulo de Empresa (Company) de la API-GO — mismo
 * patrón que `ApiGoSucursales`: nunca lanza excepción, loggea y devuelve
 * null si la API-GO no responde.
 *
 * OJO: el endpoint `GET /companies/{id}` de API-GO devuelve `clave_sol` y
 * `certificado_pem` en texto plano (así está armado ese controlador, no es
 * un descuido de acá). `obtener()` los saca del array apenas llega la
 * respuesta y los cambia por un booleano `_configurado` — ese valor crudo
 * nunca sale de este método, ni al controlador ni a los logs.
 */
class ApiGoCompanies
{
    /** @return array<string,mixed>|null */
    public function obtener(int $companyId): ?array
    {
        $respuesta = $this->peticion('get', "/companies/{$companyId}");
        $empresa = $respuesta['data'] ?? null;

        if (! is_array($empresa)) {
            return null;
        }

        // Nunca dejar pasar el valor crudo de credenciales sensibles —
        // solo si están configuradas o no.
        $empresa['clave_sol_configurada'] = filled($empresa['clave_sol'] ?? null);
        $empresa['certificado_configurado'] = filled($empresa['certificado_pem'] ?? null);
        unset($empresa['clave_sol'], $empresa['certificado_pem']);

        return $empresa;
    }

    private function peticion(string $metodo, string $ruta, array $datos = []): ?array
    {
        try {
            $baseUrl = rtrim(config('services.api_go.base_url'), '/');
            $token = config('services.api_go.token');

            $http = Http::timeout(15)->when($token, fn ($http) => $http->withToken($token));

            $respuesta = match ($metodo) {
                'get' => $http->get("{$baseUrl}{$ruta}", $datos),
                default => $http->post("{$baseUrl}{$ruta}", $datos),
            };

            $cuerpo = $respuesta->json();

            if ($respuesta->failed()) {
                Log::warning('Llamada a API-GO (empresa) devolvió error', [
                    'ruta' => $ruta,
                    'status' => $respuesta->status(),
                ]);
            }

            return $cuerpo;
        } catch (\Throwable $e) {
            Log::warning('Llamada a API-GO (empresa) lanzó excepción', [
                'ruta' => $ruta,
                'mensaje' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
