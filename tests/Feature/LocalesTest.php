<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locales/Establecimientos: UI fina sobre el módulo de Sucursales de la
 * API-GO (sin tabla local). Se prueba con `Http::fake()` simulando la
 * forma real de respuesta de `BranchController` — la API-GO local no se
 * puede levantar en este entorno (su .env apunta a la base de datos de
 * producción, inalcanzable desde acá).
 */
class LocalesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_index_lista_los_locales_que_devuelve_la_api_go(): void
    {
        Http::fake([
            '*/branches*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'id' => 1, 'company_id' => 1, 'codigo' => '0000', 'nombre' => 'Casa Drywall',
                        'direccion' => 'Calle Beatita de Humay 693', 'ubigeo' => '110101',
                        'distrito' => 'Pisco', 'provincia' => 'Pisco', 'departamento' => 'Ica',
                        'telefono' => '945228419', 'email' => 'jied977@gmail.com',
                        'series_factura' => ['F001'], 'series_boleta' => ['B001'],
                        'series_nota_credito' => [], 'series_nota_debito' => [], 'series_guia_remision' => [],
                        'activo' => true,
                    ],
                ],
            ], 200),
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.locales.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('Casa Drywall');
        $respuesta->assertSee('0000');
        $respuesta->assertSee('Ica');
    }

    public function test_index_no_rompe_cuando_la_api_go_no_responde(): void
    {
        Http::fake(['*/branches*' => Http::response(null, 500)]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.locales.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('Sin locales registrados');
    }

    public function test_crear_local_envia_los_campos_correctos_a_la_api_go(): void
    {
        Http::fake([
            '*/branches' => Http::response([
                'success' => true,
                'data' => ['id' => 2, 'codigo' => '0001', 'nombre' => 'Sucursal Pisco'],
            ], 201),
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.locales.store'), [
            'codigo' => '0001',
            'nombre' => 'Sucursal Pisco',
            'direccion' => 'Av. Los Pinos 123',
            'ubigeo' => '110101',
            'distrito' => 'Pisco',
            'provincia' => 'Pisco',
            'departamento' => 'Ica',
            'telefono' => '945228419',
            'email' => 'sucursal@casadrywall.pe',
        ])->assertRedirect(route('admin.locales.index'));

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8001/api/v1/branches'
                && $request['codigo'] === '0001'
                && $request['company_id'] === 1;
        });
    }

    public function test_actualizar_series_convierte_el_texto_en_arreglos(): void
    {
        Http::fake(['*/branches/*' => Http::response(['success' => true, 'data' => []], 200)]);

        $this->actingAs($this->admin(), 'web')->put(route('admin.locales.series', 1), [
            'series_factura' => "F001\nF002",
            'series_boleta' => 'B001',
        ])->assertRedirect(route('admin.locales.index'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/branches/1')
                && $request['series_factura'] === ['F001', 'F002']
                && $request['series_boleta'] === ['B001'];
        });
    }
}
