<?php

namespace Database\Factories;

use App\Models\Boleta;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Boleta>
 */
class BoletaFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Boleta::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $correlativo = str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT);

        return [
            'company_id' => Company::factory(),
            'branch_id' => Branch::factory(),
            'client_id' => Client::factory(),
            'tipo_documento' => '03',
            'serie' => 'B001',
            'correlativo' => $correlativo,
            'numero_completo' => "B001-{$correlativo}",
            'metodo_envio' => 'individual',
            'fecha_emision' => now()->format('Y-m-d'),
            'ubl_version' => '2.1',
            'tipo_operacion' => '0101',
            'moneda' => 'PEN',
            'valor_venta' => 40.68,
            'mto_oper_gravadas' => 40.68,
            'mto_oper_exoneradas' => 0.00,
            'mto_oper_inafectas' => 0.00,
            'mto_oper_gratuitas' => 0.00,
            'mto_igv' => 7.32,
            'mto_isc' => 0.00,
            'mto_icbper' => 0.00,
            'total_impuestos' => 7.32,
            'sub_total' => 48.00,
            'mto_imp_venta' => 48.00,
            'detalles' => [
                [
                    'codigo' => 'PROD001',
                    'descripcion' => 'Producto de prueba',
                    'unidad' => 'NIU',
                    'cantidad' => 2,
                    'mto_valor_unitario' => 20.34,
                    'porcentaje_igv' => 18.00,
                    'tip_afe_igv' => '10',
                ]
            ],
            'leyendas' => [
                [
                    'code' => '1000',
                    'value' => 'CUARENTA Y OCHO CON 00/100 SOLES'
                ]
            ],
            'datos_adicionales' => null,
            'xml_path' => null,
            'cdr_path' => null,
            'pdf_path' => null,
            'estado_sunat' => 'PENDIENTE',
            'respuesta_sunat' => null,
            'codigo_hash' => null,
            'usuario_creacion' => 'SYSTEM',
        ];
    }

    /**
     * Create a boleta with SUNAT accepted status.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_sunat' => 'ACEPTADO',
        ]);
    }

    /**
     * Create a boleta with SUNAT rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_sunat' => 'RECHAZADO',
        ]);
    }
}
