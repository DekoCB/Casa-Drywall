<?php

namespace App\Services;

use SimpleXMLElement;
use ZipArchive;

/**
 * Lector de archivos .xlsx sin dependencias externas.
 *
 * Reemplaza a la función `leerExcelClientes()` del proyecto original, que
 * descomprimía el .xlsx y parseaba el XML a mano. Aquí la lectura es genérica:
 * devuelve una matriz de filas indexada por número de columna (base 0).
 */
class LectorExcel
{
    /**
     * @return array<int, array<int, string>>|array{error: string}
     */
    public function leer(string $ruta, ?string $nombreHoja = null): array
    {
        $zip = new ZipArchive;

        if ($zip->open($ruta) !== true) {
            return ['error' => 'No se pudo abrir el archivo.'];
        }

        $cadenas = $this->cadenasCompartidas($zip);
        $hojaPath = $this->rutaHoja($zip, $nombreHoja);

        if (! $hojaPath) {
            $zip->close();

            return ['error' => $nombreHoja
                ? "No se encontró la hoja \"{$nombreHoja}\" en el Excel."
                : 'No se encontró ninguna hoja en el Excel.'];
        }

        $hojaXml = $zip->getFromName($hojaPath);
        $zip->close();

        if (! $hojaXml) {
            return ['error' => 'No se pudo leer la hoja.'];
        }

        return $this->filas(simplexml_load_string($hojaXml), $cadenas);
    }

    /** Tabla de cadenas compartidas (`sharedStrings.xml`). */
    private function cadenasCompartidas(ZipArchive $zip): array
    {
        $xmlCrudo = $zip->getFromName('xl/sharedStrings.xml');

        if (! $xmlCrudo) {
            return [];
        }

        $cadenas = [];
        $xml = simplexml_load_string($xmlCrudo);

        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $cadenas[] = (string) $si->t;

                continue;
            }

            // Texto enriquecido: se concatenan todos los fragmentos.
            $texto = '';
            foreach ($si->r as $fragmento) {
                $texto .= (string) $fragmento->t;
            }
            $cadenas[] = $texto;
        }

        return $cadenas;
    }

    /** Resuelve la ruta interna del XML de la hoja pedida (o la primera). */
    private function rutaHoja(ZipArchive $zip, ?string $nombreHoja): ?string
    {
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $wbXml = $zip->getFromName('xl/workbook.xml');

        if (! $wbXml || ! $relsXml) {
            return null;
        }

        $mapaRels = [];
        foreach (simplexml_load_string($relsXml)->Relationship as $rel) {
            $mapaRels[(string) $rel['Id']] = (string) $rel['Target'];
        }

        // Se parsean las etiquetas <sheet> con regex porque los namespaces de
        // r:id hacen poco fiable a SimpleXML según cómo se generó el archivo.
        preg_match_all('/<sheet\b[^>]*>/i', $wbXml, $etiquetas);

        foreach ($etiquetas[0] as $etiqueta) {
            preg_match('/name="([^"]*)"/i', $etiqueta, $nombre);
            preg_match('/r:id="([^"]*)"/i', $etiqueta, $rid);

            if (! isset($rid[1]) || ! isset($mapaRels[$rid[1]])) {
                continue;
            }

            $coincide = $nombreHoja === null
                || strtolower(trim($nombre[1] ?? '')) === strtolower(trim($nombreHoja));

            if ($coincide) {
                return 'xl/'.ltrim($mapaRels[$rid[1]], '/');
            }
        }

        return null;
    }

    /** Convierte el XML de la hoja en una matriz de filas y columnas. */
    private function filas(SimpleXMLElement $hoja, array $cadenas): array
    {
        $filas = [];

        foreach ($hoja->sheetData->row as $fila) {
            $celdas = [];

            foreach ($fila->c as $celda) {
                $tipo = (string) $celda['t'];

                $valor = match ($tipo) {
                    's' => $cadenas[(int) (string) $celda->v] ?? '',
                    'inlineStr' => (string) $celda->is->t,
                    default => (string) $celda->v,
                };

                $celdas[$this->indiceColumna((string) $celda['r'])] = trim($valor);
            }

            $filas[] = $celdas;
        }

        return $filas;
    }

    /** Traduce una referencia tipo "AC12" al índice de columna base 0. */
    private function indiceColumna(string $referencia): int
    {
        preg_match('/([A-Z]+)\d+/', $referencia, $coincidencias);

        $letras = strtoupper($coincidencias[1] ?? 'A');
        $indice = 0;

        for ($i = 0; $i < strlen($letras); $i++) {
            $indice = $indice * 26 + ord($letras[$i]) - 64;
        }

        return $indice - 1;
    }
}
