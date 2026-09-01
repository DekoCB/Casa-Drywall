<?php

namespace App\Services;

use App\Models\OrdenCompra;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera los dos libros de Excel del original (`generar_excel_oc.php`):
 * el formato oficial RT-PV-F-01 que se manda al proveedor y la hoja interna
 * con costos y márgenes que usa la secretaria. Cada orden va en su hoja.
 */
class ExcelOrdenCompra
{
    /* Paleta del formato original */
    private const NEGRO    = '000000';
    private const ROJO     = 'FF0000';   // teléfono, correo, descuento, REVISADO POR
    private const ROJO_FDO = 'FF5050';   // caja de CONDICION
    private const AZUL     = '0070C0';   // ACTIVO, vendedor, VoBo
    private const VERDE    = '00B050';   // empresa de transporte
    private const NARANJA  = 'C55A11';   // descripción de los productos
    private const GRIS_TIT = 'D9D9D9';   // cabeceras de tabla y DIA/MES/AÑO
    private const GRIS_VAL = 'F2F2F2';   // valor del N° de orden
    private const MORADO   = '5B21B6';   // hoja de secretaria

    /** Devuelve el .xlsx ya escrito en memoria, listo para descargar. */
    public function generar(Collection $ordenes, string $tipo = 'proveedor'): string
    {
        $libro = new Spreadsheet();
        $usados = [];

        foreach ($ordenes->values() as $i => $orden) {
            $hoja = $i === 0 ? $libro->getActiveSheet() : $libro->createSheet();

            $nombre = $this->nombreHoja($orden, $usados);
            $usados[] = $nombre;
            $hoja->setTitle($nombre);

            $tipo === 'secretaria'
                ? $this->hojaSecretaria($hoja, $orden)
                : $this->hojaProveedor($hoja, $orden);
        }

        $libro->setActiveSheetIndex(0);

        $temporal = tempnam(sys_get_temp_dir(), 'oc');
        (new Xlsx($libro))->save($temporal);
        $contenido = (string) file_get_contents($temporal);
        @unlink($temporal);

        return $contenido;
    }

    public function nombreArchivo(Collection $ordenes, string $tipo): string
    {
        $fecha = $ordenes->first()?->fecha?->format('Ymd') ?? now()->format('Ymd');
        $sufijo = $tipo === 'secretaria' ? ' (Secretaria)' : '';

        return "Orden de Compra RENTAL TECH SAC {$fecha}{$sufijo}.xlsx";
    }

    /* ═══════════════════════════════════════════════════════
       Hoja para el proveedor — formato clásico RT-PV-F-01

       Reparto de las 9 columnas:
         A CANT. | B CÓDIGO | C UND | D..F DESCRIPCION | G P.UNIT. | H DSCTO % | I IMPORTE
       En la cabecera se reutilizan A:B como etiqueta, C:D como valor,
       E etiqueta y F valor; el bloque derecho va en G y H:I.
       ═══════════════════════════════════════════════════════ */
    private function hojaProveedor(Worksheet $hoja, OrdenCompra $o): void
    {
        $productos = $o->productos ?? [];
        $numero = $o->numero_orden ?: '000000';
        $fecha = $o->fecha ?? now();
        $dia = $fecha->format('d');
        $mes = $fecha->format('m');
        $anio = $fecha->format('Y');
        $totalUsd = (float) $o->total_usd;

        // La DIRECCION del formato clásico va completa; sólo se añade lo que falte.
        $direccion = trim((string) $o->direccion);
        foreach ([$o->distrito, $o->provincia, $o->departamento] as $parte) {
            $parte = trim((string) $parte);

            if ($parte === '' || mb_stripos($direccion, $parte) !== false) {
                continue;
            }

            $direccion = $direccion === '' ? $parte : rtrim($direccion, ' -').'-'.$parte;
        }

        foreach (['A' => 14, 'B' => 14, 'C' => 8, 'D' => 20, 'E' => 15, 'F' => 40, 'G' => 11, 'H' => 14, 'I' => 17] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }

        $hoja->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $hoja->getPageSetup()->setFitToWidth(1);
        $hoja->getPageSetup()->setFitToHeight(0);
        $hoja->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.4)->setRight(0.4);

        $etiqueta   = array_merge($this->fuente(self::NEGRO, 10, true), $this->alineado());
        $etiquetaC  = array_merge($this->fuente(self::NEGRO, 10, true), $this->alineado(Alignment::HORIZONTAL_CENTER));
        $valor      = array_merge($this->fuente(self::NEGRO, 10), $this->alineado());
        $valorNegC  = array_merge($this->fuente(self::NEGRO, 10, true), $this->alineado(Alignment::HORIZONTAL_CENTER));

        /* ── Título y código de formato ── */
        $hoja->getRowDimension(1)->setRowHeight(20);
        $hoja->setCellValue('C1', 'COTIZACION - ORDEN DE COMPRA');
        $this->caja($hoja, 'C1:F1', array_merge($this->fuente(self::NEGRO, 11, true), $this->alineado(Alignment::HORIZONTAL_CENTER)), self::NEGRO);
        $this->caja($hoja, 'G1:H1', [], self::NEGRO);
        $hoja->setCellValue('I1', 'RT-PV-F-01');
        $this->caja($hoja, 'I1', $valorNegC, self::NEGRO);

        $hoja->getRowDimension(2)->setRowHeight(18);
        $hoja->setCellValue('I2', 'Versión: 01');
        $this->caja($hoja, 'I2', array_merge($this->fuente(self::NEGRO, 10), $this->alineado(Alignment::HORIZONTAL_CENTER)), self::NEGRO);

        $hoja->getRowDimension(3)->setRowHeight(18);
        $hoja->setCellValue('A3', '📍 Car. Central Km 100, San Ramón, Junín - Perú');
        $hoja->getStyle('A3')->applyFromArray($etiqueta);

        $hoja->getRowDimension(4)->setRowHeight(18);
        $hoja->setCellValue('B4', '✉ rental.tech2024@gmail.com');
        $hoja->getStyle('B4')->applyFromArray($etiqueta);

        $hoja->getRowDimension(5)->setRowHeight(14);

        /* ── Bloque derecho: N° / GUIA / FACTURA / BOL.VEN / fecha ── */
        $hoja->getRowDimension(6)->setRowHeight(18);
        $hoja->setCellValue('G6', 'N°');
        $this->caja($hoja, 'G6', array_merge($etiquetaC, $this->fondo(self::GRIS_TIT)), self::NEGRO);
        $hoja->setCellValue('H6', $numero);
        $this->caja($hoja, 'H6:I6', array_merge($this->fuente(self::NEGRO, 11, true), $this->fondo(self::GRIS_VAL), $this->alineado(Alignment::HORIZONTAL_CENTER)), self::NEGRO);

        foreach ([7 => ['GUIA:', $o->nro_guia], 8 => ['FACTURA:', $o->nro_factura], 9 => ['BOL.VEN:', '']] as $fila => $par) {
            $hoja->getRowDimension($fila)->setRowHeight(18);
            $hoja->setCellValue("G{$fila}", $par[0]);
            $this->caja($hoja, "G{$fila}", $etiqueta, self::NEGRO);
            $hoja->setCellValue("H{$fila}", $par[1]);
            $this->caja($hoja, "H{$fila}:I{$fila}", $valorNegC, self::NEGRO);
        }

        $hoja->getRowDimension(10)->setRowHeight(18);
        foreach (['G10' => 'DIA', 'H10' => 'MES', 'I10' => 'AÑO'] as $celda => $texto) {
            $hoja->setCellValue($celda, $texto);
            $this->caja($hoja, $celda, array_merge($etiquetaC, $this->fondo(self::GRIS_TIT)), self::NEGRO);
        }

        $hoja->getRowDimension(11)->setRowHeight(20);
        $hoja->setCellValue('G11', "{$dia}/{$mes}/{$anio}");
        $this->caja($hoja, 'G11:I11', array_merge($this->fuente(self::NEGRO, 11, true), $this->alineado(Alignment::HORIZONTAL_CENTER)), self::NEGRO);

        /* ── Bloque izquierdo: datos del proveedor ── */
        $datos = [
            7  => ['SR (ES):', $o->proveedor],
            8  => ['DIRECCION:', $direccion],
            9  => ['Distrito:', $o->distrito],
            10 => ['Provincia:', $o->provincia],
            11 => ['Departamento:', $o->departamento],
        ];

        foreach ($datos as $fila => $par) {
            $hoja->setCellValue("A{$fila}", $par[0]);
            $hoja->getStyle("A{$fila}:B{$fila}")->applyFromArray($etiqueta);
            $hoja->mergeCells("A{$fila}:B{$fila}");
            $hoja->setCellValue("C{$fila}", $par[1]);
            $this->caja($hoja, "C{$fila}:F{$fila}", $valor, self::NEGRO);
        }

        // R.U.C. y teléfono
        $hoja->getRowDimension(12)->setRowHeight(18);
        $hoja->setCellValue('A12', 'R.U.C.');
        $hoja->getStyle('A12:B12')->applyFromArray($etiqueta);
        $hoja->mergeCells('A12:B12');
        $hoja->setCellValue('C12', $o->ruc);
        $this->caja($hoja, 'C12:D12', $valorNegC, self::NEGRO);
        $hoja->setCellValue('E12', 'TELEFONO:');
        $this->caja($hoja, 'E12', $etiqueta, self::NEGRO);
        $hoja->setCellValue('F12', $o->telefono);
        $this->caja($hoja, 'F12', array_merge($this->fuente(self::ROJO, 10, true), $this->alineado(Alignment::HORIZONTAL_CENTER)), self::NEGRO);

        // Condición de pago y correo
        $hoja->getRowDimension(13)->setRowHeight(18);
        $hoja->setCellValue('A13', 'CONDICION:');
        $hoja->getStyle('A13:B13')->applyFromArray($etiqueta);
        $hoja->mergeCells('A13:B13');
        $hoja->setCellValue('C13', $o->condicion_pago);
        $this->caja($hoja, 'C13:D13', array_merge($this->fuente(self::NEGRO, 10, true), $this->fondo(self::ROJO_FDO), $this->alineado(Alignment::HORIZONTAL_CENTER)), self::NEGRO);
        $hoja->setCellValue('E13', 'Correo:');
        $this->caja($hoja, 'E13', $etiqueta, self::NEGRO);
        $hoja->setCellValue('F13', $o->correo);
        $this->caja($hoja, 'F13', array_merge($this->fuente(self::ROJO, 10, true), $this->alineado(Alignment::HORIZONTAL_CENTER)), self::NEGRO);

        $hoja->getRowDimension(14)->setRowHeight(18);
        $this->caja($hoja, 'C14:D14', $this->fondo(self::ROJO_FDO), self::NEGRO);
        $hoja->getRowDimension(15)->setRowHeight(10);

        // Status del cliente y total facturado
        $hoja->getRowDimension(16)->setRowHeight(18);
        $hoja->setCellValue('A16', 'STATUS DEL CLIENTE:');
        $hoja->getStyle('A16:B16')->applyFromArray($etiqueta);
        $hoja->mergeCells('A16:B16');
        $hoja->setCellValue('C16', 'ACTIVO');
        $this->caja($hoja, 'C16:D16', array_merge($this->fuente(self::AZUL, 10, true), $this->alineado(Alignment::HORIZONTAL_CENTER)), self::NEGRO);
        $hoja->setCellValue('E16', 'TOTAL FACTURADO');
        $this->caja($hoja, 'E16', array_merge($this->fuente(self::ROJO, 10, true), $this->alineado(Alignment::HORIZONTAL_CENTER)), self::ROJO, Border::BORDER_DASHED);
        $hoja->setCellValue('F16', $totalUsd);
        $hoja->getStyle('F16')->getNumberFormat()->setFormatCode('"$"* #,##0.00');
        $this->caja($hoja, 'F16', array_merge($this->fuente(self::ROJO, 10, true), $this->alineado(Alignment::HORIZONTAL_RIGHT)), self::ROJO, Border::BORDER_DASHED);

        $hoja->getRowDimension(17)->setRowHeight(18);
        $hoja->setCellValue('A17', 'CODIGO DEL VENDEDOR:');
        $hoja->getStyle('A17:B17')->applyFromArray($etiqueta);
        $hoja->mergeCells('A17:B17');
        $hoja->setCellValue('C17', $o->cod_vendedor);
        $this->caja($hoja, 'C17:D17', $valorNegC, self::NEGRO);

        $hoja->getRowDimension(18)->setRowHeight(14);

        /* ── Tabla de productos ── */
        $f = 19;
        $hoja->getRowDimension($f)->setRowHeight(20);
        $cabecera = array_merge($this->fuente(self::NEGRO, 10, true), $this->fondo(self::GRIS_TIT), $this->alineado(Alignment::HORIZONTAL_CENTER));

        foreach (['A' => 'CANT.', 'B' => 'CÓDIGO', 'C' => 'UND', 'G' => 'P.UNIT.', 'H' => 'DSCTO %', 'I' => 'IMPORTE'] as $col => $texto) {
            $hoja->setCellValue("{$col}{$f}", $texto);
            $this->caja($hoja, "{$col}{$f}", $cabecera, self::NEGRO);
        }
        $hoja->setCellValue("D{$f}", 'DESCRIPCION');
        $this->caja($hoja, "D{$f}:F{$f}", $cabecera, self::NEGRO);
        $f++;

        $celdaCant = array_merge($this->fuente(self::NEGRO, 10, true), $this->alineado(Alignment::HORIZONTAL_RIGHT));
        $celdaCent = array_merge($this->fuente(self::NEGRO, 10), $this->alineado(Alignment::HORIZONTAL_CENTER));
        $celdaDesc = array_merge($this->fuente(self::NARANJA, 10, true), $this->alineado());
        $celdaNum  = array_merge($this->fuente(self::NEGRO, 10), $this->alineado(Alignment::HORIZONTAL_RIGHT));
        $celdaDsct = array_merge($this->fuente(self::ROJO, 10, true), $this->alineado(Alignment::HORIZONTAL_RIGHT));

        $escritas = 0;

        foreach ($productos as $p) {
            $escritas++;
            $cantidad = (float) ($p['cantidad'] ?? 0);
            $unitario = (float) ($p['precio_unit_usd'] ?? 0);

            $hoja->getRowDimension($f)->setRowHeight(18);
            $hoja->setCellValue("A{$f}", $cantidad);
            $this->caja($hoja, "A{$f}", $celdaCant, self::NEGRO);
            $hoja->setCellValue("B{$f}", '09-61-02-'.($p['codigo'] ?? ''));
            $this->caja($hoja, "B{$f}", $celdaCent, self::NEGRO);
            $hoja->setCellValue("C{$f}", $p['unidad'] ?? '');
            $this->caja($hoja, "C{$f}", $celdaCent, self::NEGRO);
            $hoja->setCellValue("D{$f}", $p['descripcion'] ?? '');
            $this->caja($hoja, "D{$f}:F{$f}", $celdaDesc, self::NEGRO);
            $hoja->setCellValue("G{$f}", $unitario);
            $hoja->getStyle("G{$f}")->getNumberFormat()->setFormatCode('#,##0.00');
            $this->caja($hoja, "G{$f}", $celdaNum, self::NEGRO);
            $hoja->setCellValue("H{$f}", 0);
            $hoja->getStyle("H{$f}")->getNumberFormat()->setFormatCode('0.0000"%"');
            $this->caja($hoja, "H{$f}", $celdaDsct, self::NEGRO);
            $hoja->setCellValue("I{$f}", round($cantidad * $unitario, 2));
            $hoja->getStyle("I{$f}")->getNumberFormat()->setFormatCode('#,##0.00');
            $this->caja($hoja, "I{$f}", $celdaNum, self::NEGRO);
            $f++;
        }

        // El cuadro siempre tiene al menos 9 filas, aunque queden vacías.
        for ($i = $escritas; $i < 9; $i++) {
            $hoja->getRowDimension($f)->setRowHeight(18);

            foreach (['A', 'B', 'C', 'G', 'H', 'I'] as $col) {
                $this->caja($hoja, "{$col}{$f}", [], self::NEGRO);
            }

            $this->caja($hoja, "D{$f}:F{$f}", [], self::NEGRO);
            $f++;
        }

        /* ── Pie: transporte, vendedor, total y firmas ── */
        $f++;

        $hoja->getRowDimension($f)->setRowHeight(20);
        $hoja->setCellValue("A{$f}", 'Empresa de Transporte:');
        $hoja->getStyle("A{$f}:B{$f}")->applyFromArray($etiqueta);
        $hoja->mergeCells("A{$f}:B{$f}");
        $hoja->setCellValue("E{$f}", $o->empresa_transporte);
        $hoja->getStyle("E{$f}:F{$f}")->applyFromArray(array_merge($this->fuente(self::VERDE, 10, true), $this->alineado()));
        $hoja->mergeCells("E{$f}:F{$f}");
        $hoja->setCellValue("H{$f}", '$');
        $hoja->getStyle("H{$f}")->applyFromArray($etiqueta);
        $hoja->setCellValue("I{$f}", $totalUsd);
        $hoja->getStyle("I{$f}")->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle("I{$f}")->applyFromArray(array_merge($this->fuente(self::NEGRO, 10, true, true), $this->alineado(Alignment::HORIZONTAL_RIGHT)));
        $hoja->getStyle("H{$f}:I{$f}")->applyFromArray(['borders' => [
            'top'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::NEGRO]],
            'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::NEGRO]],
        ]]);
        $f++;

        $hoja->getRowDimension($f)->setRowHeight(18);
        $hoja->setCellValue("A{$f}", 'VENDEDOR:');
        $hoja->getStyle("A{$f}:B{$f}")->applyFromArray($etiqueta);
        $hoja->mergeCells("A{$f}:B{$f}");
        $hoja->setCellValue("E{$f}", $o->vendedor);
        $hoja->getStyle("E{$f}:F{$f}")->applyFromArray(array_merge($this->fuente(self::AZUL, 10, true), $this->alineado()));
        $hoja->mergeCells("E{$f}:F{$f}");
        $f++;

        // Peso, bultos y observaciones sólo si hay algo que mostrar.
        $detalle = [];
        if (trim((string) $o->peso) !== '')   { $detalle[] = 'PESO: '.$o->peso.' kg'; }
        if ($o->bultos !== null)              { $detalle[] = 'BULTOS: '.$o->bultos; }
        if (trim((string) $o->observaciones) !== '') { $detalle[] = 'OBS: '.$o->observaciones; }

        if ($detalle !== []) {
            $hoja->getRowDimension($f)->setRowHeight(18);
            $hoja->setCellValue("A{$f}", implode('   |   ', $detalle));
            $hoja->getStyle("A{$f}")->applyFromArray(array_merge($this->fuente('6B7280', 9), $this->alineado()));
            $f++;
        }

        $f += 2;

        $hoja->getRowDimension($f)->setRowHeight(18);
        $hoja->setCellValue("A{$f}", 'REVISADO POR');
        $hoja->getStyle("A{$f}")->applyFromArray(array_merge($this->fuente(self::ROJO, 10, true), $this->alineado()));
        $hoja->setCellValue("F{$f}", 'APROBADO POR');
        $hoja->getStyle("F{$f}")->applyFromArray($etiqueta);
        $f++;

        $hoja->getRowDimension($f)->setRowHeight(18);
        $hoja->setCellValue("F{$f}", 'VoBo CREDITOS & COBRANZAS');
        $hoja->getStyle("F{$f}")->applyFromArray(array_merge($this->fuente(self::AZUL, 10, true), $this->alineado()));
        $f++;

        // Línea de firma bajo "REVISADO POR"
        $hoja->getStyle("A{$f}")->applyFromArray(['borders' => [
            'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::NEGRO]],
        ]]);

        $hoja->getStyle("A1:I{$f}")->getFont()->setName('Arial');
        $hoja->setSelectedCell('A1');
    }

    /* ═══════════════════════════════════════════════════════
       Hoja interna para la secretaria: costos y margen
       ═══════════════════════════════════════════════════════ */
    private function hojaSecretaria(Worksheet $hoja, OrdenCompra $o): void
    {
        $productos = $o->productos ?? [];
        $fecha = $o->fecha ?? now();
        $tc = (float) $o->tc ?: 3.75;
        $totalUsd = (float) $o->total_usd;
        $totalSoles = (float) $o->total_soles ?: round($totalUsd * $tc, 2);
        $pventa = (float) $o->precio_venta;

        foreach (['A' => 14, 'B' => 38, 'C' => 10, 'D' => 12, 'E' => 13, 'F' => 13, 'G' => 14] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }

        $etiqueta = array_merge($this->fuente('374151', 10, true), $this->fondo('F3F4F6'), $this->alineado());

        $f = 1;
        $hoja->setCellValue("A{$f}", 'ORDEN DE COMPRA — USO INTERNO');
        $this->caja($hoja, "A{$f}:G{$f}", array_merge($this->fuente('FFFFFF', 13, true), $this->fondo(self::MORADO), $this->alineado(Alignment::HORIZONTAL_CENTER)), self::MORADO, Border::BORDER_MEDIUM);
        $hoja->getRowDimension($f)->setRowHeight(24);
        $f++;

        $hoja->setCellValue("A{$f}", 'N° Orden:');
        $this->caja($hoja, "A{$f}", $etiqueta);
        $hoja->setCellValue("B{$f}", $o->numero_orden ?: '000000');
        $this->caja($hoja, "B{$f}", array_merge($this->fuente(self::MORADO, 11, true), $this->alineado()));
        $hoja->setCellValue("C{$f}", 'Fecha:');
        $this->caja($hoja, "C{$f}", $etiqueta);
        $hoja->setCellValue("D{$f}", $fecha->format('d/m/Y'));
        $this->caja($hoja, "D{$f}", array_merge($this->fuente('111111', 10, true), $this->alineado()));
        $hoja->setCellValue("E{$f}", 'N° Factura:');
        $this->caja($hoja, "E{$f}", $etiqueta);
        $hoja->setCellValue("F{$f}", $o->nro_factura ?: 'Pendiente');
        $this->caja($hoja, "F{$f}", array_merge($this->fuente('111111', 10), $this->alineado()));
        $hoja->setCellValue("G{$f}", $o->condicion_pago);
        $this->caja($hoja, "G{$f}", array_merge($this->fuente('374151', 10, true), $this->fondo('EDE9FE'), $this->alineado(Alignment::HORIZONTAL_CENTER)));
        $hoja->getRowDimension($f)->setRowHeight(16);
        $f++;

        $hoja->setCellValue("A{$f}", 'T/C:');
        $this->caja($hoja, "A{$f}", $etiqueta);
        $hoja->setCellValue("B{$f}", $tc);
        $this->caja($hoja, "B{$f}", array_merge($this->fuente('111111', 10), $this->alineado()));
        $hoja->setCellValue("C{$f}", 'N° Guía:');
        $this->caja($hoja, "C{$f}", $etiqueta);
        $hoja->setCellValue("D{$f}", $o->nro_guia ?: 'Pendiente');
        $this->caja($hoja, "D{$f}:F{$f}", array_merge($this->fuente('111111', 10), $this->alineado()));
        $hoja->getRowDimension($f)->setRowHeight(15);
        $f += 2;

        $cabecera = array_merge($this->fuente('FFFFFF', 10, true), $this->fondo(self::MORADO), $this->alineado(Alignment::HORIZONTAL_CENTER));

        foreach (['A' => 'Código', 'B' => 'Producto', 'C' => 'Unidad', 'D' => 'Cantidad', 'E' => 'Costo USD', 'F' => 'Costo S/', 'G' => 'P.Venta S/'] as $col => $texto) {
            $hoja->setCellValue("{$col}{$f}", $texto);
            $this->caja($hoja, "{$col}{$f}", $cabecera, self::MORADO);
        }
        $hoja->getRowDimension($f)->setRowHeight(15);
        $f++;

        $fondo = 'FAFAFA';
        $cantidadTotal = 0;

        foreach ($productos as $p) {
            $cantidad = (float) ($p['cantidad'] ?? 0);
            $unitario = (float) ($p['precio_unit_usd'] ?? 0);
            $cantidadTotal += $cantidad;

            $hoja->setCellValue("A{$f}", $p['codigo'] ?? '');
            $this->caja($hoja, "A{$f}", array_merge($this->fuente('374151', 9), $this->alineado()), $fondo);
            $hoja->setCellValue("B{$f}", $p['descripcion'] ?? '');
            $this->caja($hoja, "B{$f}", array_merge($this->fuente('111111', 9), $this->alineado()), $fondo);
            $hoja->setCellValue("C{$f}", $p['unidad'] ?? '');
            $this->caja($hoja, "C{$f}", array_merge($this->fuente('374151', 9), $this->alineado(Alignment::HORIZONTAL_CENTER)), $fondo);
            $hoja->setCellValue("D{$f}", $cantidad);
            $this->caja($hoja, "D{$f}", array_merge($this->fuente('111111', 10, true), $this->alineado(Alignment::HORIZONTAL_CENTER)), $fondo);
            $hoja->setCellValue("E{$f}", '$'.number_format($unitario, 4));
            $this->caja($hoja, "E{$f}", array_merge($this->fuente('374151', 9), $this->alineado(Alignment::HORIZONTAL_RIGHT)), $fondo);
            $hoja->setCellValue("F{$f}", 'S/ '.number_format($unitario * $tc, 2));
            $this->caja($hoja, "F{$f}", array_merge($this->fuente('374151', 9), $this->alineado(Alignment::HORIZONTAL_RIGHT)), $fondo);
            $hoja->setCellValue("G{$f}", $pventa > 0 ? 'S/ '.number_format($pventa * $cantidad, 2) : '—');
            $this->caja($hoja, "G{$f}", array_merge($this->fuente('11704A', 9, true), $this->alineado(Alignment::HORIZONTAL_RIGHT)), $fondo);
            $hoja->getRowDimension($f)->setRowHeight(14);
            $f++;
        }

        $f++;
        $hoja->setCellValue("D{$f}", $cantidadTotal);
        $this->caja($hoja, "D{$f}", array_merge($this->fuente(self::MORADO, 11, true), $this->fondo('EDE9FE'), $this->alineado(Alignment::HORIZONTAL_CENTER)), self::MORADO);
        $hoja->setCellValue("E{$f}", '$ '.number_format($totalUsd, 2));
        $this->caja($hoja, "E{$f}", array_merge($this->fuente('111111', 11, true), $this->fondo('EDE9FE'), $this->alineado(Alignment::HORIZONTAL_RIGHT)), self::MORADO);
        $hoja->setCellValue("F{$f}", 'S/ '.number_format($totalSoles, 2));
        $this->caja($hoja, "F{$f}", array_merge($this->fuente('111111', 11, true), $this->fondo('EDE9FE'), $this->alineado(Alignment::HORIZONTAL_RIGHT)), self::MORADO);

        if ($pventa > 0) {
            $ventaTotal = $pventa * $cantidadTotal;
            $hoja->setCellValue("G{$f}", 'S/ '.number_format($ventaTotal, 2));
            $this->caja($hoja, "G{$f}", array_merge($this->fuente('11704A', 11, true), $this->fondo('E6F1EB'), $this->alineado(Alignment::HORIZONTAL_RIGHT)), '11704A');
            $f++;

            $margen = $ventaTotal - $totalSoles;
            $pct = $ventaTotal > 0 ? round($margen / $ventaTotal * 100, 1) : 0;

            $hoja->setCellValue("F{$f}", 'Margen:');
            $this->caja($hoja, "F{$f}", array_merge($this->fuente('374151', 9, true), $this->fondo('F3F4F6'), $this->alineado(Alignment::HORIZONTAL_RIGHT)));
            $hoja->setCellValue("G{$f}", 'S/ '.number_format($margen, 2).' ('.$pct.'%)');
            $color = $pct >= 15 ? '11704A' : ($pct >= 8 ? '8A5A12' : 'A8231F');
            $this->caja($hoja, "G{$f}", array_merge($this->fuente($color, 10, true), $this->alineado(Alignment::HORIZONTAL_RIGHT)));
        }

        if (trim((string) $o->observaciones) !== '') {
            $f += 2;
            $hoja->setCellValue("A{$f}", 'Observaciones: '.$o->observaciones);
            $this->caja($hoja, "A{$f}:G{$f}", array_merge($this->fuente('6B7280', 9), $this->alineado()), 'F9F9F9');
        }

        $hoja->setSelectedCell('A1');
    }

    /** Excel limita el nombre a 31 caracteres y no admite repetidos. */
    private function nombreHoja(OrdenCompra $o, array $usados): string
    {
        $nombre = preg_replace('/[\[\]\*\/\\\\\?:]/', '', 'OC-'.($o->numero_orden ?: '000000'));
        $nombre = mb_substr($nombre ?: 'OC', 0, 31);
        $base = $nombre;
        $n = 2;

        while (in_array($nombre, $usados, true)) {
            $nombre = mb_substr($base, 0, 28).'-'.$n;
            $n++;
        }

        return $nombre;
    }

    /* ── Ayudas de estilo, calcadas de excel_helpers.php ── */

    private function caja(Worksheet $hoja, string $rango, array $extra = [], string $color = 'E2E8F0', string $borde = Border::BORDER_THIN): void
    {
        if (str_contains($rango, ':')) {
            $hoja->mergeCells($rango);
        }

        $hoja->getStyle($rango)->applyFromArray(array_replace_recursive([
            'borders' => ['allBorders' => ['borderStyle' => $borde, 'color' => ['rgb' => $color]]],
        ], $extra));
    }

    private function fondo(string $rgb): array
    {
        return ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]]];
    }

    private function fuente(string $color = '111111', int $tam = 9, bool $negrita = false, bool $cursiva = false): array
    {
        $f = ['size' => $tam, 'color' => ['rgb' => $color]];

        if ($negrita) { $f['bold'] = true; }
        if ($cursiva) { $f['italic'] = true; }

        return ['font' => $f];
    }

    private function alineado(string $h = Alignment::HORIZONTAL_LEFT, string $v = Alignment::VERTICAL_CENTER): array
    {
        return ['alignment' => ['horizontal' => $h, 'vertical' => $v]];
    }
}
