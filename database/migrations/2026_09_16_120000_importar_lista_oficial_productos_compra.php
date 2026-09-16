<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lista oficial de los 129 productos que la empresa suele comprar (la
 * misma para cualquier proveedor — ya no hace falta cargarla por Excel
 * proveedor por proveedor, ver `ProveedorController::importarProductos()`,
 * que se deja de usar desde la UI pero no se borra). Código y costo de
 * compra tal como los pasó el negocio (PDF "lista 15-09").
 *
 * Upsert por código, igual criterio que `importarProductos()`: si el
 * producto ya existe se actualiza nombre y precio de compra sin tocar lo
 * demás (stock, precio de venta ya editado a mano, etc.); si no existe se
 * crea con los valores por defecto de siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->productos() as [$codigo, $nombre, $precioCompra]) {
            $existente = DB::table('productos')->where('codigo', $codigo)->first();

            if ($existente) {
                DB::table('productos')->where('id', $existente->id)->update([
                    'nombre' => $nombre,
                    'precio_compra' => $precioCompra,
                ]);

                continue;
            }

            DB::table('productos')->insert([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'precio_compra' => $precioCompra,
                'precio_venta' => 0,
                'stock' => 0,
                'stock_minimo' => 0,
                'estado' => 'activo',
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Es una carga de datos del negocio, no una migración de esquema: un
     * rollback no debería borrar productos que para entonces ya se hayan
     * usado en ventas u órdenes reales, así que `down()` no revierte nada
     * a propósito.
     */
    public function down(): void {}

    /** @return array<int, array{0: string, 1: string, 2: float}> */
    private function productos(): array
    {
        return [
            ['00001', 'PARANTE GALV. 89 X 38 X 0.45 X 3M', 8.8259],
            ['00002', 'PARANTE GALV. 64 X 38 X 0.45 X 3M', 7.3693],
            ['00003', 'PARANTE GALV. 38 X 38 X 0.45 X 3M', 6.9496],
            ['00004', 'RIEL GALV. 90 X 25 X 0.45 X 3M', 7.6663],
            ['00005', 'RIEL GALV. 65 X 25 X 0.45 X 3M', 6.7662],
            ['00006', 'RIEL GALV. 39 X 25 X 0.45 X 3M', 4.6788],
            ['00007', 'PARANTE CONSTRUTEK 89X38X0.45X3MT', 9.0168],
            ['00008', 'PARANTE CONSTRUTEK 64X38X0.45X 3MT', 7.2187],
            ['00009', 'PARANTE CONSTRUTEK 38MM X 0.45 X 3MT', 7],
            ['00010', 'RIEL CONSTRUTEK 90MM x 0.45 x 3MT', 7.83016],
            ['00011', 'RIEL CONSTRUTEK 65MM X0.45 X 3MT', 5.90059],
            ['00012', 'RIEL CONSTRUTEK 39MM X 0.45 X 3MT', 5.1],
            ['00013', 'PERFIL OMEGA CTTK 30 X 25 X 045', 8.3894],
            ['00014', 'ESQUINERO CTTK 30 X 30 X 0.30 X 3M', 4.8414],
            ['00015', 'ESQUINERO PLASTICO RIGIDO 3.05M', 9.2057],
            ['00016', 'MASILLA CONSTRUTEK CJA 20KG', 28.3081],
            ['00017', 'MASILLA P/JUNTA CJA X20KG GYPLAC', 34.8407],
            ['00018', 'MASILLA P/JUNTA BALDE 27KG GYPLAC', 66.8],
            ['00019', 'MASILLA P/JUNTA BOLSA X 5KG CTK', 12.2],
            ['00020', 'PLACA STD 244X122 X 12.7MM(1/2") GYPLAC', 31],
            ['00021', 'PLACA RESISTENTE A LA HUMEDAD 244X122 X 12.7 MM (1/2") GYPLAC', 42.1112],
            ['00022', 'PLACA INIFUGA (RF) ROJA 244X122 X 12.7MM(1/2") GYPLAC', 37.4532],
            ['00023', 'PLACA STD 244X122 X 9.5MM (3/8") GYPLAC', 29.1243],
            ['00024', 'PLACA SUPERBOARD 122 X 244 X 4MM ETERNIT', 33.9],
            ['00025', 'PLACA SUPERBOARD 122 X 244 X 6MM ETERNIT', 60.0059],
            ['00026', 'PLACA SUPERBOARD122 X 244 X 8MM ETERNIT', 82.5637],
            ['00027', 'PLACA SUPERBOARD 122X244X10MM', 96],
            ['00028', 'CINTA DOBLE CONTACTO PLAS. 3MX15MM', 3],
            ['00029', 'CINTA METALICA. 50MMX30MT', 25.889935],
            ['00030', 'CINTA PAPEL 50MMX75M PWTK', 6.7436],
            ['00031', 'TORNILL AUTOPERFORANTE 10X2.5" CAJA X 100 UND', 15],
            ['00032', 'TORNILL ZING COBERTOR 8x3" P/FINA X100', 12.9106],
            ['00033', 'TORNILL FOSFATO NEG. PLACA 6X1" P/FINA CJA X 1000', 22.3428],
            ['00034', 'TECHO FIBRAFORTRE ROJO 1.1 X 3MT ONDAS', 40],
            ['00035', 'TORNILL FOSFATO NEG. PLACA 6 X 1.5" P/FINA CJA X 1000', 35.7],
            ['00036', 'TORNILL FOSFATO NEG. 6X2" P/FINA CJA X 1000', 45.9],
            ['00037', 'TORNILL ZING 6X1" CJA X 1000-P/BROCA', 31.4069],
            ['00038', 'TORNILL ESTRUCTURAL 7X7/16" P/FINA CJA X 1000', 23.4684],
            ['00039', 'TORNILL ZING WAFER 8X1/2" P/BROCA CJA X 1000', 22],
            ['00040', 'TORNILL ZING WAFER 8X1/2" P/FINA CJA X 1000', 20],
            ['00041', 'TORNILL AUTOPERFORANTES 10X1 / CAJA X 100', 11],
            ['00042', 'TORNILL FOSFATO NEG PLACA 6X1 BOL X 100UND', 2],
            ['00043', 'TORNILL ESTRUCTURAL 7X7/16 BOL. X 100UND', 2.5],
            ['00044', 'TORNILL ZING 6X1" P/BROCA SUPERBOARD', 35],
            ['00045', 'CLAVOS DE FIJACION 1-1/4" CAJA X 100', 10.5],
            ['00046', 'CLAVOS DE FIJACION 1" CAJA X 100', 7.1198],
            ['00047', 'FULMINANTE MARRON-CALIB.22 CJAX100', 13.921],
            ['00048', 'FULMINANTE VERDE-CALIB.22 CJAX100', 19],
            ['00049', 'CAPUCHA PVC X100', 10],
            ['00050', 'WINCHA TOOL 8M', 4.66],
            ['00051', 'LISTON DE MADERA 2 X1"', 5.5],
            ['00052', 'LISTON DE MADERA 3 X1"', 9.5],
            ['00053', 'TEE PRINCIPAL CTTK 15/16 3.66M e=0.3mm', 11.2],
            ['00054', 'TEE SECUNDARIO CTTK 15/16 1.22M e=0.3mm', 4.2],
            ['00055', 'TEE TERCIARIO CTTK 15/16 0.61M e=0.3mm', 1.85],
            ['00056', 'ANGULO PERIMET. CTTK 15/16 3.05 M E=0.4mm', 5.2],
            ['00057', 'BALDOSA VINIL 0.61X0.61X7MM CAJAX10 MOD. 154', 37.3164],
            ['00058', 'TECHO PERFIL 4 ETERNIT 1.10 X 3.05 X 4MM', 49],
            ['00059', 'TECHO TERMOACUSTICO 1.10X3.60 X 1.5MMM', 57],
            ['00060', 'ESPATULA ACERO 12"', 39.9988],
            ['00061', 'ESPATULA ACERO 6"', 26.9983],
            ['00062', 'CAJA RECTANGULAR PARA LUZ PVC', 1],
            ['00063', 'CAJA OCTOGONAL PARALUZ PVC', 1.2],
            ['00064', 'TUBO PVC 3/4" X 3M GRIS PARA LUZ', 2],
            ['00065', 'CONECTORES DE 3/4 PARA LUZ', 0.4],
            ['00066', 'UNIÓN PARA TUBO DE LUZ 3/4"', 0.4],
            ['00067', 'TUBO PVC PAVCO 3/4 LUZ X 3MT', 5],
            ['00068', 'CURVA 3/4 P/LUZ', 0.4],
            ['00069', 'ALAMBRE GALVANIZADO #16 X 1KG', 10],
            ['00070', 'LANA AISLANT TERM. ACUST. " -122X10M -12 M2 -', 65.6267],
            ['00071', 'SERRUCHO PUNTA DRYWALL STANLEY', 22],
            ['00072', 'TIJERA DE AVIACION CORTE RECTO 14-563 STANLEY', 47],
            ['00073', 'SIKAFLEX -11 FC 300ML', 22],
            ['00074', 'SELLADOR MAJESTAD BLD. X1 GLN', 16],
            ['00075', 'TEMPLE MAJESTAD BOL. X 25KG', 27.8],
            ['00076', 'PEGAMENTO PVC 118ML', 9],
            ['00077', 'TABLERO OSB DE 8MM X 1.22 X 2.44', 28.5],
            ['00078', 'ADAPTADOR-EXTENSIÓN DE IMPACTO 1/4" X 75MM', 10],
            ['00079', 'CINTILLO X 100 UN X 25 CEN', 12],
            ['00080', 'MASILLA P/JUNTA ROMERAL EN POLVO SACO X 25 KG GYPLAC', 72.5],
            ['00081', 'MANTO ASFALT GRAVILL. POLIES. ROJO 3MM/10 M2', 161.3],
            ['00082', 'CINTA MALLA 50MM X 75M PWTK', 8.729],
            ['00083', 'CLAVO DE FIJACION X 3/4 CJA X 100 U', 7.1885],
            ['00084', 'PERFIL OMEGA GALV. 30 X 25 X 045', 6.190044],
            ['00085', 'TORNILL ZING 6X1" P/FINA SUPERBOARD', 38],
            ['00086', 'PLACA STD 244X122 X 7MM GYPLAC', 24.7],
            ['00087', 'PUNTERA CON TOPE PH 2X25MM', 4.5],
            ['00088', 'CLAVO CLICK 1 1/4 ANCLAJE X(100)', 32.8],
            ['00089', 'CLAVO PERIMETRAL DE 3/4. X 500', 5],
            ['00090', 'LANA TERMICA GYPLAC 14.4m2', 200],
            ['00091', 'TECHO TERMOACUSTICO KLAR 1.10X3.60 TK5 ROJO', 81.553],
            ['00092', 'LIJA 150 AGUA', 2.5],
            ['00093', 'MALETA LANA STANLE 16" 40CM', 90],
            ['00094', 'ESCOFINA P/DRYWALL', 32],
            ['00095', 'NIVEL 12" ALUM AMARILL', 23],
            ['00096', 'NIVEL IMANTADO DE 80CM', 50],
            ['00097', 'TECHO TERMOACUSTICO TIPO TEJA 1.07 X 5.90MX 2.5MM ROJO', 125],
            ['00098', 'ESCUADRA', 16.5],
            ['00099', 'TECNOPOR 120X240 X 2"', 20],
            ['00100', 'TOMACORRIENTE DOBLE', 15],
            ['00101', 'INTERRUCTOR SIMPLE', 6],
            ['00102', 'ESCUADRA C/TOPE 6" ALUM', 10],
            ['00103', 'TARUGO MADERA 3/8 X 12 U', 1.8],
            ['00104', 'TARUGO P/DRYWALL X 12 U', 4.8],
            ['00105', 'WINCHA', 5.9],
            ['00106', 'FULMICLAVOS X 1" X 100 UND', 25],
            ['00107', 'KIT CAPUCHA UPVC + TORN 3" P/F X 20 U', 16.5],
            ['00108', 'REPUESTO DE CUCHILLA', 4.8],
            ['00109', 'APLICADOR PARA TUBO SILICONA', 11.9],
            ['00110', 'MADERA CONTRAMARCO 2" X 1" 10P', 6],
            ['00111', 'TUBO CORRUGADO X METRO', 2],
            ['00112', 'PISTOLA DE IMPACTO', 200],
            ['00113', 'MASILLA PANEL MASTIK CAJA 20KG', 25],
            ['00114', 'TECHO BLANCO 1.1 X 3 ONDAS', 38],
            ['00115', 'TECHO TERMOACUSTICO TRANSLÚCIDO FIB. DE VID. 1.10 X 1.5 X 3.60', 85],
            ['00116', 'PANEL REY NEGRA 1.22X2.44X12.7MM(1/2)', 56.53498],
            ['00117', 'TECHO TERMOACUSTICO DE 1.07x 6M', 100],
            ['00118', 'BATIDOR ADAPTADOR', 11.9],
            ['00119', 'CUCHILLA GNI', 4.9],
            ['00120', 'CAJA LLAVE TERMICA EMPOTRAR 12 POLOS PVC', 24],
            ['00121', 'SIKAFLEX -11 FC 300ML BLANCO', 27],
            ['00122', 'TORNILL ZING 6X1-1/2"-25MM CJA X 1000 P/BROCA', 14.1],
            ['00123', 'ESQUINERO PLAST. CURVA', 9.7255],
            ['00124', 'TECHO KLAR BLANCO', 72.8532],
            ['00125', 'PARANTE 64mm X 7mt', 15],
            ['00126', 'CINTA PAPEL 5CM X 38M', 4.7436],
            ['00127', 'TABLA REFUERZO DE MADERA', 37],
            ['00128', 'TORNILL ZING WAFER 8 X 1.5" P. BROCA CAJ. X 1000', 25.65],
            ['00129', 'BANDEJA PLAST PARA PASTA JUNTA 12"(30CM)', 45],
        ];
    }
};
