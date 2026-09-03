<?php

namespace App\Services\Pos;

use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Validation\ValidationException;

/**
 * Apertura/cierre de sesiones de caja del Punto de Venta.
 */
class CajaService
{
    public function sesionAbiertaDe(Usuario $usuario): ?SesionCaja
    {
        return SesionCaja::abiertas()->where('usuario_id', $usuario->id)->with('caja')->first();
    }

    public function abrir(Usuario $usuario, int $cajaId, float $montoInicial): SesionCaja
    {
        if ($this->sesionAbiertaDe($usuario)) {
            throw ValidationException::withMessages([
                'caja' => 'Ya tienes una caja abierta. Ciérrala antes de abrir otra.',
            ]);
        }

        if (SesionCaja::abiertas()->where('caja_id', $cajaId)->exists()) {
            throw ValidationException::withMessages([
                'caja' => 'Esa caja ya está abierta por otro usuario.',
            ]);
        }

        return SesionCaja::create([
            'caja_id' => $cajaId,
            'usuario_id' => $usuario->id,
            'monto_inicial' => round($montoInicial, 2),
            'estado' => 'abierta',
            'abierta_en' => now(),
        ]);
    }

    public function cerrar(SesionCaja $sesion, float $montoContado): SesionCaja
    {
        if ($sesion->estado !== 'abierta') {
            throw ValidationException::withMessages([
                'caja' => 'Esta sesión de caja ya está cerrada.',
            ]);
        }

        $esperado = $sesion->calcularEsperado();

        $sesion->update([
            'monto_final_esperado' => $esperado,
            'monto_final_contado' => round($montoContado, 2),
            'diferencia' => round($montoContado - $esperado, 2),
            'estado' => 'cerrada',
            'cerrada_en' => now(),
        ]);

        return $sesion;
    }
}
