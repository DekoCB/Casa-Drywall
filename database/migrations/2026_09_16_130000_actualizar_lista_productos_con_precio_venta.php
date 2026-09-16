<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lista oficial completa que reemplaza a la de la migración anterior
 * (`2026_09_16_120000_...`, 129 productos con solo el costo de compra):
 * el negocio mandó la versión final con 156 productos y AMBOS precios —
 * de compra (para Órdenes de Compra) y de venta (para Cotización, Nota de
 * Venta, Boleta y Factura). Los códigos 00001-00129 se reordenaron por
 * completo en esta versión (ej. el 00002 de antes ya no es el mismo
 * producto que el 00002 de ahora), así que un simple upsert por código
 * ya deja todo correcto: cada código termina con el nombre/precios que
 * dice ESTA lista, sin dejar ningún residuo de la carga anterior — los
 * 129 códigos viejos están todos cubiertos por esta lista de 156.
 *
 * El stock no se toca a propósito: la lista trae una columna STOCK, pero
 * es una foto del 15-09 y para varios códigos ni siquiera es un entero
 * (ej. "33.75", "-0.75" — parece venir de otro criterio, no de unidades
 * enteras como maneja `productos.stock`); pisar el stock real de hoy con
 * esa foto vieja es justo el tipo de sorpresa que no se pidió. Los
 * productos nuevos (130-156) arrancan en 0, igual que los de la carga
 * anterior — se acumula con compras reales.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->productos() as [$codigo, $nombre, $precioCompra, $precioVenta]) {
            $existente = DB::table('productos')->where('codigo', $codigo)->first();

            if ($existente) {
                DB::table('productos')->where('id', $existente->id)->update([
                    'nombre' => $nombre,
                    'precio_compra' => $precioCompra,
                    'precio_venta' => $precioVenta,
                ]);

                continue;
            }

            DB::table('productos')->insert([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'precio_compra' => $precioCompra,
                'precio_venta' => $precioVenta,
                'stock' => 0,
                'stock_minimo' => 0,
                'estado' => 'activo',
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Igual que la migración anterior: es una carga de datos del negocio,
     * no un cambio de esquema — un rollback no debería borrar productos
     * que para entonces ya se hayan usado en ventas u órdenes reales.
     */
    public function down(): void {}

    /** @return array<int, array{0: string, 1: string, 2: float, 3: float}> */
    private function productos(): array
    {
        return [
            ['00001', 'PARANTE GALV. 89 X 38 X 0.45 X 3M', 8.8259, 9.5],
            ['00002', 'PARANTE 89 X 38 X 0.90 X 3M PESADO CTTK', 19.1, 24],
            ['00003', 'PARANTE GALV. 64 X 38 X 0.45 X 3M', 7.3693, 8.5],
            ['00004', 'PARANTE GALV. 64 X 38 X 0.90 X 3M PESADO', 0, 22],
            ['00005', 'PARANTE GALV. 38 X 38 X 0.45 X 3M', 6.9496, 7.5],
            ['00006', 'RIEL GALV. 90 X 25 X 0.45 X 3M', 7.6663, 9],
            ['00007', 'RIEL GALV. 90 X 25 X 0.90 X3M PESADO', 15.6, 22],
            ['00008', 'RIEL GALV. 65 X 25 X 0.45 X 3M', 6.7662, 7.5],
            ['00009', 'RIEL GALV. 65 X 25 X 0.90 X 3M PESADO', 0, 20],
            ['00010', 'RIEL GALV. 39 X 25 X 0.45 X 3M', 4.6788, 6],
            ['00011', 'PERFIL OMEGA CTTK 30 X 25 X 045', 8.3894, 10.5],
            ['00012', 'ESQUINERO CTTK 30 X 30 X 0.30 X 3M', 4.8414, 6.5],
            ['00013', 'ESQUINERO PLASTICO RIGIDO 3.05M', 9.2057, 13],
            ['00014', 'PLACA STD 244X122 X 12.7MM(1/2") GYPLAC', 31, 32],
            ['00015', 'PLACA RESISTENTE A LA HUMEDAD 244X122 X 12.7 MM (1/2") GYPLAC', 42.1112, 44],
            ['00016', 'PLACA INIFUGA (RF) ROJA 244X122 X 12.7MM(1/2") GYPLAC', 37.4532, 46],
            ['00017', 'PLACA STD 244X122 X 9.5MM (3/8") GYPLAC', 29.1243, 31],
            ['00018', 'PLACA SUPERBOARD 122 X 244 X 4MM ETERNIT', 33.9, 39],
            ['00019', 'PLACA SUPERBOARD 122 X 244 X 6MM ETERNIT', 60.0059, 63],
            ['00020', 'PLACA SUPERBOARD122 X 244 X 8MM ETERNIT', 82.5637, 84],
            ['00021', 'MASILLA P/JUNTA CJA X20KG GYPLAC', 34.8407, 37],
            ['00022', 'MASILLA P/JUNTA BOLSA X 5KG CTK', 12.2, 15],
            ['00023', 'CINTA DOBLE CONTACTO PLAS. 3MX15MM', 3, 4],
            ['00024', 'CINTA METALICA. 50MMX30MT', 25.889935, 36],
            ['00025', 'MASILLA CONSTRUTEK CJA 20KG', 28.3081, 36.5],
            ['00026', 'CINTA PAPEL 50MMX75M PWTK', 6.7436, 12],
            ['00027', 'TORNILL ZING COBERTOR 8x3" P/FINA X100', 12.9106, 17],
            ['00028', 'TORNILL FOSFATO NEG. PLACA 6X1" P/FINA CJA X 1000', 22.3428, 28],
            ['00029', 'TECHO FIBRAFORTRE ROJO 1.1 X 3MT ONDAS', 40, 42],
            ['00030', 'TORNILL FOSFATO NEG. PLACA 6 X 1.5" P/FINA CJA X 1000', 35.7, 45],
            ['00031', 'TORNILL FOSFATO NEG. 6X2" P/FINA CJA X 1000', 45.9, 70],
            ['00032', 'TORNILL ZING 6X1" CJA X 1000-P/BROCA', 31.4069, 45],
            ['00033', 'TORNILL ESTRUCTURAL 7X7/16" P/FINA CJA X 1000', 23.4684, 30],
            ['00034', 'TORNILL ZING WAFER 8X1/2" P/BROCA CJA X 1000', 22, 36],
            ['00035', 'TORNILL ZING WAFER 8X1/2" P/FINA CJA X 1000', 20, 35],
            ['00036', 'TORNILL AUTOPERFORANTES 10X1 / CAJA X 100', 11, 18],
            ['00037', 'CLAVOS DE FIJACION 1-1/4" CAJA X 100', 10.5, 15],
            ['00038', 'CLAVOS DE FIJACION 1" CAJA X 100', 7.1198, 12],
            ['00039', 'FULMINANTE MARRON-CALIB.22 CJAX100', 13.921, 20],
            ['00040', 'FULMINANTE VERDE-CALIB.22 CJAX100', 19, 25],
            ['00041', 'CAPUCHA PVC X100', 10, 12],
            ['00042', 'ESQUINERO METALICO 1 1/4" X 3M', 4.3, 5.5],
            ['00043', 'WINCHA TOOL 8M', 4.66, 8],
            ['00044', 'LISTON DE MADERA 2 X1"', 5.5, 8],
            ['00045', 'LISTON DE MADERA 3 X1"', 9.5, 11],
            ['00046', 'TEE PRINCIPAL CTTK 15/16 3.66M e=0.3mm', 11.2, 16],
            ['00047', 'TEE SECUNDARIO CTTK 15/16 1.22M e=0.3mm', 4.2, 5],
            ['00048', 'TEE TERCIARIO CTTK 15/16 0.61M e=0.3mm', 1.85, 2.5],
            ['00049', 'ANGULO PERIMET. CTTK 15/16 3.05 M E=0.4mm', 5.2, 6.5],
            ['00050', 'BALDOSA VINIL 0.61X0.61X7MM CAJAX10 MOD. 154', 37.3164, 48],
            ['00051', 'TECHO PERFIL 4 ETERNIT 1.10 X 3.05 X 4MM', 49, 50],
            ['00052', 'TECHO TERMOACUSTICO 1.10X3.60 X 1.5MMM', 57, 70],
            ['00053', 'ESPATULA ACERO 12"', 39.9988, 58],
            ['00054', 'CINTA MALLA 50MM X20MT', 2.2, 5],
            ['00055', 'CABLE INDECO # 12 X 100 MT', 0, 1],
            ['00056', 'TORNILL AUTOPERFORANTE 10X2.5" CAJA X 100 UND', 15, 25],
            ['00057', 'ESPATULA ACERO 6"', 26.9983, 38],
            ['00058', 'TORNILL FOSFATO NEG PLACA 6X1 BOL X 100UND', 2, 3],
            ['00059', 'TORNILL ESTRUCTURAL 7X7/16 BOL. X 100UND', 2.5, 3.5],
            ['00060', 'CAJA RECTANGULAR PARA LUZ PVC', 1, 2],
            ['00061', 'TUBO PVC 3/4" X 3M GRIS PARA LUZ', 2, 3],
            ['00062', 'CONECTORES DE 3/4 PARA LUZ', 0.4, 0.5],
            ['00063', 'UNIÓN PARA TUBO DE LUZ 3/4"', 0.4, 0.5],
            ['00064', 'TUBO PVC PAVCO 3/4 LUZ X 3MT', 42, 46],
            ['00065', 'TECHO ALUZING 0.22X 1.10 X 3.60MT TR5', 28.5, 35],
            ['00066', 'CURVA 3/4 P/LUZ', 0.4, 0.5],
            ['00067', 'CAJA OCTOGONAL PARALUZ PVC', 1.2, 2],
            ['00068', 'ALAMBRE GALVANIZADO #16 X 1KG', 10, 12],
            ['00069', 'LANA AISLANT TERM. ACUST. " -122X10M -12 M2 -', 65.6267, 85],
            ['00070', 'CABLE INDECO #14 X 100 MT', 112, 145],
            ['00071', 'TORNILL ZING 6X1" P/BROCA SUPERBOARD', 35, 45],
            ['00072', 'SERRUCHO PUNTA DRYWALL STANLEY', 22, 28],
            ['00073', 'TIJERA DE AVIACION CORTE RECTO 14-563 STANLEY', 47, 53],
            ['00074', 'SIKAFLEX -11 FC 300ML', 22, 30],
            ['00075', 'SELLADOR MAJESTAD BLD. X1 GLN', 16, 19],
            ['00076', 'MASILLA P/JUNTA BALDE 27KG GYPLAC', 66.8, 74],
            ['00077', 'TEMPLE MAJESTAD BOL. X 25KG', 27.8, 29],
            ['00078', 'IMPTEK MANTO ASFALTICO APP IMPERPOL 300 ROJO1X10M=3M120GR', 179.1, 349],
            ['00079', 'PARANTE CONSTRUTEK 64X38X0.45X 3MT', 7.2187, 8.5],
            ['00080', 'PARANTE CONSTRUTEK 89X38X0.45X3MT', 9.0168, 9.5],
            ['00081', 'IMPTEK ULTRAPRIMER ASFALTICO 4KG BALDE', 0, 190],
            ['00082', 'TABLERO OSB BOARD 18MM X 1.22 X 2.44', 79, 99],
            ['00083', 'PACK - Puerta_900', 0, 200],
            ['00084', 'TRANSPORTE', 40, 40],
            ['00085', 'PEGAMENTO PVC 118ML', 9, 10],
            ['00086', 'Lija - N120', 2.5, 4],
            ['00087', 'Lija - N80', 2.5, 4],
            ['00088', 'Lija - N220', 2.5, 4],
            ['00089', 'Lija - N100', 2.5, 4],
            ['00090', "TECNOPOR 1''", 11, 19],
            ['00091', 'RIEL CONSTRUTEK 90MM x 0.45 x 3MT', 7.83016, 9.5],
            ['00092', 'PARANTE CONSTRUTEK 38MM X 0.45 X 3MT', 7, 7.8],
            ['00093', 'RIEL CONSTRUTEK 39MM X 0.45 X 3MT', 5.1, 6.5],
            ['00094', 'RIEL CONSTRUTEK 65MM X0.45 X 3MT', 5.90059, 8],
            ['00095', 'TABLERO OSB DE 8MM X 1.22 X 2.44', 28.5, 31],
            ['00096', 'ADAPTADOR-EXTENSIÓN DE IMPACTO 1/4" X 75MM', 10, 15],
            ['00097', 'BALDOSA - FIBRA MINERAL CONSTRUTEK CAJA X 8UND 1.20 x 0.60', 102.7, 185],
            ['00098', 'SIKA BOOM', 0, 30],
            ['00099', 'PLACA SUPERBOARD 122 X 244 X 15MM ETERNIT', 173, 173],
            ['00100', 'PLACA SUPERBOARD 122 X 244 X 12MM ETERNIT', 125, 135],
            ['00101', 'TABLERO FENOLICO 18mm X 1.22 X 2.44', 77, 78],
            ['00102', 'CINTILLO X 100 UN X 25 CEN', 10, 14],
            ['00103', 'IMPRIMANTE PLUS DILUIBLE EN AGUA 5G EDIL BAL', 135.2, 190],
            ['00104', 'PLACA SUPERBOARD 122X244X10MM', 96, 98],
            ['00105', 'MASILLA P/JUNTA ROMERAL EN POLVO SACO X 25 KG GYPLAC', 72.5, 78],
            ['00106', 'MANTO ASFALT GRAVILL. POLIES. ROJO 3MM/10 M2', 161.3, 240],
            ['00107', 'CINTA MALLA 50MM X 75M PWTK', 8.729, 14],
            ['00108', 'CLAVO DE FIJACION X 3/4 CJA X 100 U', 7.1885, 11],
            ['00109', 'PERFIL OMEGA GALV. 30 X 25 X 045', 6.190044, 9],
            ['00110', 'TORNILL ZING 6X1" P/FINA SUPERBOARD', 38, 45],
            ['00111', 'PLACA STD 244X122 X 7MM GYPLAC', 24.7, 28],
            ['00112', 'PUNTERA CON TOPE PH 2X25MM', 4.5, 5],
            ['00113', 'CLAVO CLICK 1 1/4 ANCLAJE X(100)', 32.8, 39],
            ['00114', 'CLAVO PERIMETRAL DE 3/4. X 500', 5, 14],
            ['00115', 'LANA TERMICA GYPLAC 14.4m2', 200, 235],
            ['00116', 'TECHO TERMOACUSTICO KLAR 1.10X3.60 TK5 ROJO', 81.553, 90],
            ['00117', 'LIJA 150 AGUA', 2.5, 4],
            ['00118', 'MALETA LANA STANLE 16" 40CM', 6, 6],
            ['00119', 'ESCOFINA P/DRYWALL', 30, 30],
            ['00120', 'NIVEL 12" ALUM AMARILL', 89, 90],
            ['00121', 'NIVEL IMANTADO DE 80CM', 50, 60],
            ['00122', 'TECHO TERMOACUSTICO TIPO TEJA 1.07 X 5.90MX 2.5MM ROJO', 125, 145],
            ['00123', 'ESCUADRA', 16.5, 17],
            ['00124', 'TECNOPOR 120X240 X 2"', 20, 25],
            ['00125', 'TOMACORRIENTE DOBLE', 15, 16],
            ['00126', 'INTERRUCTOR SIMPLE', 6, 8],
            ['00127', 'ESCUADRA C/TOPE 6" ALUM', 125, 130],
            ['00128', 'TARUGO MADERA 3/8 X 12 U', 4.9, 5],
            ['00129', 'TARUGO P/DRYWALL X 12 U', 4.9, 5],
            ['00130', 'WINCHA', 5.9, 6],
            ['00131', 'KIT CAPUCHA UPVC + TORN 3" P/F X 20 U', 16.5, 20],
            ['00132', 'REPUESTO DE CUCHILLA', 4.8, 5],
            ['00133', 'APLICADOR PARA TUBO SILICONA', 11.9, 12],
            ['00134', 'MADERA CONTRAMARCO 2" X 1" 10P', 6, 6.5],
            ['00135', 'TUBO CORRUGADO X METRO', 2, 2.5],
            ['00136', 'PISTOLA DE IMPACTO', 200, 225],
            ['00137', 'MASILLA PANEL MASTIK CAJA 20KG', 25, 35],
            ['00138', 'TECHO BLANCO 1.1 X 3 ONDAS', 38, 45],
            ['00139', 'TECHO TERMOACUSTICO TRANSLÚCIDO FIB. DE VID. 1.10 X 1.5 X 3.60', 85, 96],
            ['00140', 'PANEL REY NEGRA 1.22X2.44X12.7MM(1/2)', 56.53498, 60],
            ['00141', 'TECHO TERMOACUSTICO DE 1.07x 6M', 100, 135],
            ['00142', 'MASILLA PANEL MASTIK BOLSA X 5KG', 11, 14],
            ['00143', 'CAJA LAMINADA DOBLE TAP.13*8.5 * 5.5 FULL COLOR PLASTIF. BRILL.', 1.17, 1.5],
            ['00144', 'CAJA LAMINADA DOBLE TAP.13*9*8.5 FULL COLOR PLASTIF. BRILL.', 1.22, 1.5],
            ['00145', 'BATIDOR ADAPTADOR', 11.9, 12],
            ['00146', 'CUCHILLA GNI', 4.9, 5],
            ['00147', 'CAJA LLAVE TERMICA EMPOTRAR 12 POLOS PVC', 24, 26],
            ['00148', 'SIKAFLEX -11 FC 300ML BLANCO', 27, 30],
            ['00149', 'TORNILL ZING 6X1-1/2"-25MM CJA X 1000 P/BROCA', 14.1, 50],
            ['00150', 'ESQUINERO PLAST. CURVA', 9.7255, 13],
            ['00151', 'TECHO KLAR BLANCO', 72.8532, 82],
            ['00152', 'PARANTE 64mm X 7mt', 15, 18],
            ['00153', 'CINTA PAPEL 5CM X 38M', 4.7436, 6],
            ['00154', 'TABLA REFUERZO DE MADERA', 37, 37],
            ['00155', 'TORNILL 6X1-1/4 / P. BROCA CAJ. X 1000', 25.65, 55],
            ['00156', 'BANDEJA PLAST PARA PASTA JUNTA 12"(30CM)', 45, 65],
        ];
    }
};
