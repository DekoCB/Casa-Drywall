<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MetodoPago extends Model
{
    protected $table = 'metodos_pago';

    protected $fillable = ['nombre', 'activo'];

    /**
     * Orden fijo de exhibición, pedido explícito del negocio — no
     * alfabético. Cualquier método que no esté en esta lista (uno nuevo,
     * agregado después desde Configuración) simplemente cae al final.
     */
    public const ORDEN = ['Efectivo', 'Yape', 'Plin', 'Transferencia bancaria', 'Tarjeta', 'Depósito bancario'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /** Los activos, en el orden fijo de ORDEN — no alfabético. */
    public static function activosOrdenados(): Collection
    {
        return static::where('activo', true)->get()
            ->sortBy(function (self $metodo) {
                $posicion = array_search($metodo->nombre, self::ORDEN, true);

                return $posicion === false ? count(self::ORDEN) : $posicion;
            })
            ->values();
    }
}
