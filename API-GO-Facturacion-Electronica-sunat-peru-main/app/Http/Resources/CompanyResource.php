<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Si se solicita la representación completa: ?full=1
        if ($request->query('full')) {
            $model = $this->resource; // Eloquent model

            // Mostrar atributos ocultos si se solicita
            if ($request->query('include_hidden')) {
                $model->makeVisible($model->getHidden());
            }

            $data = $model->toArray();

            // Por defecto ocultamos campos sensibles; para incluirlos use ?include_sensitive=1
            if (! $request->query('include_sensitive')) {
                unset($data['certificado_password'], $data['certificado_pem'], $data['clave_sol'], $data['gre_client_secret_beta'], $data['gre_client_secret_produccion'], $data['gre_clave_sol']);
            }

            // Añadir estadísticas básicas si se solicita
            if ($request->query('include_stats')) {
                $data['estadisticas'] = [
                    'total_facturas' => $this->invoices()->count(),
                    'total_boletas' => $this->boletas()->count(),
                    'sucursales' => $this->branches()->count(),
                ];
            }

            return $data;
        }

        // Comportamiento por defecto (vista resumida)
        return [
            'id' => $this->id,
            'ruc' => $this->ruc,
            'razon_social' => $this->razon_social,
            'nombre_comercial' => $this->nombre_comercial,
            'direccion' => $this->direccion,
            'ubigeo' => $this->ubigeo,
            'distrito' => $this->distrito,
            'provincia' => $this->provincia,
            'departamento' => $this->departamento,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'logo' => $this->logo,

            'configuracion' => [
                'modo' => $this->modo_produccion ? 'PRODUCCIÓN' : 'BETA',
                'activo' => (bool) $this->activo,
                'usuario_sol' => $this->usuario_sol,
                'has_gre_credentials' => $this->hasGreCredentials()
            ],

            'estadisticas' => $this->when($request->input('include_stats'), function() {
                return [
                    'total_facturas' => $this->invoices()->count(),
                    'total_boletas' => $this->boletas()->count(),
                    'sucursales' => $this->branches()->count()
                ];
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
