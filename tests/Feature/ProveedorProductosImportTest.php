<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

/**
 * Carga masiva de productos de un proveedor por Excel (vincula Producto <-> Proveedor).
 * Mismo patrón de fixture que ProductoController::importar(), sin librerías externas.
 */
class ProveedorProductosImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    /** Arma un .xlsx mínimo válido con una fila de encabezado y las filas dadas. */
    private function xlsxDeFilas(array $encabezado, array $filas): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'oc_test').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($ruta, ZipArchive::CREATE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheets><sheet name="Hoja1" sheetId="1" r:id="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/></sheets>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>');

        $letras = ['A', 'B', 'C', 'D', 'E', 'F'];
        $todasLasFilas = array_merge([$encabezado], $filas);
        $xmlFilas = '';

        foreach ($todasLasFilas as $i => $fila) {
            $numeroFila = $i + 1;
            $celdas = '';

            foreach (array_values($fila) as $col => $valor) {
                $ref = $letras[$col].$numeroFila;
                $texto = htmlspecialchars((string) $valor, ENT_XML1);
                $celdas .= "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>{$texto}</t></is></c>";
            }

            $xmlFilas .= "<row r=\"{$numeroFila}\">{$celdas}</row>";
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            ."<sheetData>{$xmlFilas}</sheetData>"
            .'</worksheet>');

        $zip->close();

        return new UploadedFile($ruta, 'productos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_importar_crea_productos_nuevos_vinculados_al_proveedor(): void
    {
        $admin = $this->admin();
        $proveedor = Proveedor::create([
            'ruc' => '20123456789', 'razon_social' => 'Drywall Import SAC',
            'condiciones_pago' => 'Contado', 'estado' => 'activo',
        ]);

        $archivo = $this->xlsxDeFilas(
            ['Codigo', 'Nombre', 'Costo', 'Precio de venta'],
            [['DRY-100', 'Plancha Drywall 1/2"', '18.50', '25.00']]
        );

        $respuesta = $this->actingAs($admin, 'web')->post(
            route('admin.proveedores.productos.importar', $proveedor),
            ['archivo' => $archivo]
        );

        $respuesta->assertRedirect(route('admin.proveedores.index'));

        $producto = Producto::where('codigo', 'DRY-100')->first();
        $this->assertNotNull($producto);
        $this->assertSame($proveedor->id, $producto->proveedor_id);
        $this->assertEquals(18.50, (float) $producto->precio_compra);
        $this->assertEquals(25.00, (float) $producto->precio_venta);
    }

    public function test_importar_actualiza_y_re_vincula_un_producto_existente(): void
    {
        $admin = $this->admin();
        $proveedorViejo = Proveedor::create(['ruc' => '20111111111', 'razon_social' => 'Proveedor Viejo', 'condiciones_pago' => 'Contado', 'estado' => 'activo']);
        $proveedorNuevo = Proveedor::create(['ruc' => '20222222222', 'razon_social' => 'Proveedor Nuevo', 'condiciones_pago' => 'Contado', 'estado' => 'activo']);

        $producto = Producto::create([
            'codigo' => 'DRY-200', 'nombre' => 'Tornillo 1"', 'proveedor_id' => $proveedorViejo->id,
            'precio_compra' => 1, 'precio_venta' => 2, 'stock' => 0, 'stock_minimo' => 0, 'estado' => 'activo',
        ]);

        $archivo = $this->xlsxDeFilas(
            ['Codigo', 'Nombre', 'Costo', 'Precio de venta'],
            [['DRY-200', 'Tornillo 1" reforzado', '1.20', '2.50']]
        );

        $this->actingAs($admin, 'web')->post(
            route('admin.proveedores.productos.importar', $proveedorNuevo),
            ['archivo' => $archivo]
        );

        $producto->refresh();
        $this->assertSame($proveedorNuevo->id, $producto->proveedor_id);
        $this->assertSame('Tornillo 1" reforzado', $producto->nombre);
        $this->assertEquals(1.20, (float) $producto->precio_compra);
    }

    public function test_buscar_productos_filtra_por_proveedor(): void
    {
        $admin = $this->admin();
        $proveedorA = Proveedor::create(['ruc' => '20111111111', 'razon_social' => 'A', 'condiciones_pago' => 'Contado', 'estado' => 'activo']);
        $proveedorB = Proveedor::create(['ruc' => '20222222222', 'razon_social' => 'B', 'condiciones_pago' => 'Contado', 'estado' => 'activo']);

        Producto::create(['codigo' => 'A-1', 'nombre' => 'Placa de A', 'proveedor_id' => $proveedorA->id, 'precio_compra' => 1, 'precio_venta' => 2, 'stock' => 0, 'stock_minimo' => 0, 'estado' => 'activo']);
        Producto::create(['codigo' => 'B-1', 'nombre' => 'Placa de B', 'proveedor_id' => $proveedorB->id, 'precio_compra' => 1, 'precio_venta' => 2, 'stock' => 0, 'stock_minimo' => 0, 'estado' => 'activo']);

        $respuesta = $this->actingAs($admin, 'web')->getJson(
            'admin/productos/buscar?q=Placa&proveedor_id='.$proveedorA->id
        );

        $respuesta->assertOk();
        $nombres = collect($respuesta->json())->pluck('nombre')->all();
        $this->assertContains('Placa de A', $nombres);
        $this->assertNotContains('Placa de B', $nombres);
    }
}
