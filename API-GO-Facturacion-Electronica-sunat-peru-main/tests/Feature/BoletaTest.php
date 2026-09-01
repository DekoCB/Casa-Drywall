<?php

use App\Models\Boleta;
use App\Models\Company;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->company = Company::factory()->create();

    $this->branch = Branch::factory()->create([
        'company_id' => $this->company->id,
        'series_boleta' => ['B001'],
    ]);
});

function datosBoletaValida(int $companyId, int $branchId, array $overrides = []): array
{
    return array_merge([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'serie' => 'B001',
        'fecha_emision' => now()->format('Y-m-d'),
        'metodo_envio' => 'individual',
        'client' => [
            'tipo_documento' => '1',
            'numero_documento' => '12345678',
            'razon_social' => 'Cliente de Prueba',
        ],
        'detalles' => [
            [
                'codigo' => 'DW-001',
                'descripcion' => 'Plancha de Drywall 1.20x2.40m',
                'unidad' => 'NIU',
                'cantidad' => 2,
                'mto_valor_unitario' => 20.34,
                'porcentaje_igv' => 18.00,
                'tip_afe_igv' => '10',
            ]
        ],
    ], $overrides);
}

test('rechaza crear una boleta sin autenticación', function () {
    $data = datosBoletaValida($this->company->id, $this->branch->id);

    $response = $this->postJson('/api/v1/boletas', $data);

    $response->assertStatus(401);
});

test('puede crear una boleta básica', function () {
    $data = datosBoletaValida($this->company->id, $this->branch->id);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/boletas', $data);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Boleta creada correctamente',
        ])
        ->assertJsonPath('data.serie', 'B001')
        ->assertJsonPath('data.correlativo', '000001')
        ->assertJsonPath('data.numero_completo', 'B001-000001')
        ->assertJsonPath('data.estado_sunat', null);

    expect(Boleta::count())->toBe(1);

    $boleta = Boleta::first();
    expect((float) $boleta->mto_oper_gravadas)->toBe(40.68);
    expect((float) $boleta->mto_igv)->toBe(7.32);
    expect((float) $boleta->mto_imp_venta)->toBe(48.00);
    expect($boleta->tipo_documento)->toBe('03');
});

test('calcula correctamente el IGV al 18% sobre operaciones gravadas', function () {
    $data = datosBoletaValida($this->company->id, $this->branch->id, [
        'detalles' => [
            [
                'codigo' => 'DW-002',
                'descripcion' => 'Tornillos drywall (caja)',
                'unidad' => 'NIU',
                'cantidad' => 10,
                'mto_valor_unitario' => 10.00,
                'porcentaje_igv' => 18.00,
                'tip_afe_igv' => '10',
            ]
        ],
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/v1/boletas', $data);

    $response->assertStatus(201);

    $totales = $response->json('data.totales');
    expect((float) $totales['gravada'])->toBe(100.0);
    expect((float) $totales['igv'])->toBe(18.0);
    expect((float) $totales['total'])->toBe(118.0);
});

test('incrementa el correlativo automáticamente en boletas sucesivas', function () {
    $primera = $this->actingAs($this->user)
        ->postJson('/api/v1/boletas', datosBoletaValida($this->company->id, $this->branch->id));
    $primera->assertStatus(201)->assertJsonPath('data.numero_completo', 'B001-000001');

    $segunda = $this->actingAs($this->user)
        ->postJson('/api/v1/boletas', datosBoletaValida($this->company->id, $this->branch->id));
    $segunda->assertStatus(201)->assertJsonPath('data.numero_completo', 'B001-000002');

    expect(Boleta::count())->toBe(2);
});

test('rechaza una boleta sin detalles', function () {
    $data = datosBoletaValida($this->company->id, $this->branch->id, ['detalles' => []]);

    $response = $this->actingAs($this->user)->postJson('/api/v1/boletas', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('detalles');

    expect(Boleta::count())->toBe(0);
});

test('rechaza una boleta sin datos del cliente', function () {
    $data = datosBoletaValida($this->company->id, $this->branch->id);
    unset($data['client']);

    $response = $this->actingAs($this->user)->postJson('/api/v1/boletas', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('client');
});

test('exige DNI real (no genérico) para boletas mayores a S/700', function () {
    $data = datosBoletaValida($this->company->id, $this->branch->id, [
        'detalles' => [
            [
                'codigo' => 'DW-003',
                'descripcion' => 'Plancha de Drywall premium',
                'unidad' => 'NIU',
                'cantidad' => 20,
                'mto_valor_unitario' => 40.00,
                'porcentaje_igv' => 18.00,
                'tip_afe_igv' => '10',
            ]
        ],
    ]);
    // client.numero_documento sigue siendo el DNI genérico '12345678'

    $response = $this->actingAs($this->user)->postJson('/api/v1/boletas', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('client.numero_documento');

    expect(Boleta::count())->toBe(0);
});

test('rechaza una sucursal que no pertenece a la empresa indicada', function () {
    $otraEmpresa = Company::factory()->create();
    $otraSucursal = Branch::factory()->create([
        'company_id' => $otraEmpresa->id,
        'series_boleta' => ['B001'],
    ]);

    $data = datosBoletaValida($this->company->id, $otraSucursal->id);

    $response = $this->actingAs($this->user)->postJson('/api/v1/boletas', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('branch_id');
});

test('puede listar boletas con filtro por estado SUNAT', function () {
    Boleta::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'estado_sunat' => 'PENDIENTE',
    ]);

    Boleta::factory()->count(2)->accepted()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/boletas?company_id={$this->company->id}&estado_sunat=PENDIENTE");

    $response->assertStatus(200)->assertJson(['success' => true]);
    expect(count($response->json('data')))->toBe(3);
});

test('puede obtener el detalle de una boleta existente', function () {
    $boleta = Boleta::factory()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/boletas/{$boleta->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $boleta->id,
                'numero_completo' => $boleta->numero_completo,
            ],
        ]);
});

test('devuelve 404 al consultar una boleta inexistente', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/boletas/99999');

    $response->assertStatus(404);
});
