<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel genérico para los reportes del Centro de Reportes: una tabla simple
 * (cabecera + filas), sin el formato fijo de `ExcelOrdenCompra` (ese
 * replica un formulario impreso; aquí solo hace falta una hoja tabular).
 */
class ExportadorReportes
{
    public function excel(string $titulo, array $columnas, array $filas): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(mb_substr(preg_replace('/[\[\]\*\/\\\\\?:]/', '', $titulo) ?: 'Reporte', 0, 31));

        $hoja->setCellValue('A1', $titulo);
        $ultimaColumna = $this->columnaExcel(count($columnas));
        $hoja->mergeCells("A1:{$ultimaColumna}1");
        $hoja->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        $filaCabecera = 3;
        foreach ($columnas as $i => $texto) {
            $col = $this->columnaExcel($i + 1);
            $hoja->setCellValue("{$col}{$filaCabecera}", $texto);
        }
        $hoja->getStyle("A{$filaCabecera}:{$ultimaColumna}{$filaCabecera}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '374151']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
        ]);

        $f = $filaCabecera + 1;
        foreach ($filas as $fila) {
            foreach (array_values($fila) as $i => $valor) {
                $hoja->setCellValue($this->columnaExcel($i + 1).$f, $valor);
            }
            $f++;
        }

        foreach (range(1, count($columnas)) as $i) {
            $hoja->getColumnDimension($this->columnaExcel($i))->setAutoSize(true);
        }

        $temporal = tempnam(sys_get_temp_dir(), 'rep');
        (new Xlsx($libro))->save($temporal);
        $contenido = (string) file_get_contents($temporal);
        @unlink($temporal);

        return $contenido;
    }

    private function columnaExcel(int $indiceUnoBasado): string
    {
        $letra = '';
        while ($indiceUnoBasado > 0) {
            $resto = ($indiceUnoBasado - 1) % 26;
            $letra = chr(65 + $resto).$letra;
            $indiceUnoBasado = intdiv($indiceUnoBasado - 1, 26);
        }

        return $letra;
    }
}
