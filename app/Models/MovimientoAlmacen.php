<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoAlmacen extends Model
{
    protected $table = 'movimientos_almacen';

    public $timestamps = false;

    protected $fillable = ['producto_id', 'almacen_id', 'tipo', 'cantidad', 'stock_anterior', 'stock_nuevo', 'motivo', 'referencia', 'usuario_id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }
}
