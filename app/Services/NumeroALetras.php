<?php

namespace App\Services;

/** Convierte un monto a su representación en letras, en español. */
class NumeroALetras
{
    private const UNIDADES = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
    ];

    /** 21 a 29 tienen forma contraída propia (VEINTIUNO, VEINTIDÓS…), no «VEINTE Y UNO». */
    private const VEINTI = [
        1 => 'VEINTIUNO', 2 => 'VEINTIDÓS', 3 => 'VEINTITRÉS', 4 => 'VEINTICUATRO', 5 => 'VEINTICINCO',
        6 => 'VEINTISÉIS', 7 => 'VEINTISIETE', 8 => 'VEINTIOCHO', 9 => 'VEINTINUEVE',
    ];

    private const DECENAS = [
        2 => 'VEINTE', 3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA',
        6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA',
    ];

    private const CENTENAS = [
        1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS', 5 => 'QUINIENTOS',
        6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS',
    ];

    /** «567.00» → «QUINIENTOS SESENTA Y SIETE CON 00/100 SOLES». */
    public function convertir(float $monto, string $moneda = 'SOLES'): string
    {
        $monto = round(abs($monto), 2);
        $entero = (int) floor($monto);
        $centavos = (int) round(($monto - $entero) * 100);

        $texto = $entero === 0 ? 'CERO' : trim($this->entero($entero));

        return "{$texto} CON ".str_pad((string) $centavos, 2, '0', STR_PAD_LEFT)."/100 {$moneda}";
    }

    private function entero(int $n): string
    {
        if ($n < 20) {
            return self::UNIDADES[$n];
        }

        if ($n < 100) {
            return $this->decenas($n);
        }

        if ($n < 1000) {
            return $this->centenas($n);
        }

        if ($n < 1_000_000) {
            return $this->miles($n);
        }

        return $this->millones($n);
    }

    private function decenas(int $n): string
    {
        $d = intdiv($n, 10);
        $u = $n % 10;

        if ($d === 1) {
            return self::UNIDADES[$n];
        }

        if ($d === 2 && $u > 0) {
            return self::VEINTI[$u];
        }

        return $u === 0 ? self::DECENAS[$d] : self::DECENAS[$d].' Y '.self::UNIDADES[$u];
    }

    private function centenas(int $n): string
    {
        if ($n === 100) {
            return 'CIEN';
        }

        $c = intdiv($n, 100);
        $resto = $n % 100;

        $texto = self::CENTENAS[$c];

        return $resto === 0 ? $texto : $texto.' '.$this->entero($resto);
    }

    private function miles(int $n): string
    {
        $miles = intdiv($n, 1000);
        $resto = $n % 1000;

        $texto = $miles === 1 ? 'MIL' : $this->entero($miles).' MIL';

        return $resto === 0 ? $texto : $texto.' '.$this->entero($resto);
    }

    private function millones(int $n): string
    {
        $millones = intdiv($n, 1_000_000);
        $resto = $n % 1_000_000;

        $texto = $millones === 1 ? 'UN MILLÓN' : $this->entero($millones).' MILLONES';

        return $resto === 0 ? $texto : $texto.' '.$this->entero($resto);
    }
}
