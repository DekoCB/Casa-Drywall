<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El alta de Orden de Compra pasó de 3 pasos a 2: "Para quién" (cliente y
 * documentos) ya no es su propia parada — el negocio compra siempre para
 * stock propio, nunca para un cliente puntual — así que su contenido
 * (número, fechas, cliente opcional, documentos, transporte) se fusionó
 * dentro de lo que era el paso 3 ("Confirmar"), que ahora es el paso 2.
 */
class OrdenCompraWizardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_pagina_de_alta_tiene_solo_dos_pasos(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ordenes-compra.create'));

        $respuesta->assertOk();
        $respuesta->assertSee('data-ir="1"', false);
        $respuesta->assertSee('data-ir="2"', false);
        $respuesta->assertDontSee('data-ir="3"', false);
        $respuesta->assertSee('data-paso="1"', false);
        $respuesta->assertSee('data-paso="2"', false);
        $respuesta->assertDontSee('data-paso="3"', false);
        $respuesta->assertDontSee('Para quién');

        // El contenido de lo que era el paso 2 (cabecera del documento) y el
        // paso 3 (costos) ahora conviven dentro del mismo paso 2.
        $respuesta->assertSee('Datos de la orden');
        $respuesta->assertSee('N° Orden');
        $respuesta->assertSee('PARA CLIENTE');
        $respuesta->assertSee('Costos');
        $respuesta->assertSee('Condición de Pago', false);
    }

    public function test_el_asistente_de_javascript_avanza_solo_dos_tramos(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ordenes-compra.create'));

        $respuesta->assertSee('const TRAMOS = 2;', false);
    }
}
