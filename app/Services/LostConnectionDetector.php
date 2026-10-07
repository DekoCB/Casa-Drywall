<?php

namespace App\Services;

use Illuminate\Contracts\Database\LostConnectionDetector as LostConnectionDetectorContract;
use Illuminate\Database\LostConnectionDetector as LostConnectionDetectorPorDefecto;
use Illuminate\Support\Str;
use Throwable;

/**
 * Laravel ya reconecta y reintenta una vez cuando una consulta falla por
 * una conexión perdida — pero solo si el mensaje de la excepción coincide
 * con una lista fija de patrones conocidos (`LostConnectionDetector` de
 * Laravel). "SQLSTATE[HY000] [2002] Operation not permitted" — el error
 * real que tiró este hosting compartido el 7 de octubre, cortando el
 * guardado de una Orden de Compra justo al verificar la sesión — no está
 * en esa lista, así que una caída breve de MySQL se mostraba como un error
 * en vez de recuperarse sola. Se extiende el detector por defecto (sin
 * perder ninguno de sus patrones) para que también lo reconozca.
 */
class LostConnectionDetector implements LostConnectionDetectorContract
{
    private const PATRONES_ADICIONALES = [
        'SQLSTATE[HY000] [2002] Operation not permitted',
    ];

    public function __construct(
        private readonly LostConnectionDetectorPorDefecto $porDefecto = new LostConnectionDetectorPorDefecto(),
    ) {}

    public function causedByLostConnection(Throwable $e): bool
    {
        return $this->porDefecto->causedByLostConnection($e)
            || Str::contains($e->getMessage(), self::PATRONES_ADICIONALES);
    }
}
