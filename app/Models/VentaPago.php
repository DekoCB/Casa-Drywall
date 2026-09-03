<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaPago extends Model
{
    public $timestamps = false;

    protected $fillable = ['venta_id', 'metodo_pago', 'monto', 'referencia'];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
