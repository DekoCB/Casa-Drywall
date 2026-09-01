<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cada entrada o salida de merch. Las entradas nacen de una orden de compra;
 * las salidas son las entregas a los clientes de Rental Tech.
 */
class MerchMovimiento extends Model
{
    protected $table = 'merch_movimientos';

    public $timestamps = false;

    protected $fillable = [
        'merch_id', 'tipo', 'cantidad', 'costo_unit', 'fecha',
        'orden_compra_id', 'numero_orden', 'cliente_id', 'cliente_nombre',
        'observaciones', 'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cantidad' => 'integer',
            'costo_unit' => 'decimal:2',
        ];
    }

    public function merch(): BelongsTo
    {
        return $this->belongsTo(Merch::class, 'merch_id');
    }

    /** Lo que costó la línea; sólo tiene sentido en las entradas. */
    public function total(): float
    {
        return $this->cantidad * (float) $this->costo_unit;
    }
}
