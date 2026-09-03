<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCaja extends Model
{
    protected $table = 'movimientos_caja';

    public $timestamps = false;

    protected $fillable = [
        'sesion_caja_id', 'tipo', 'metodo_pago', 'monto',
        'referencia_tipo', 'referencia_id', 'descripcion', 'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class);
    }
}
