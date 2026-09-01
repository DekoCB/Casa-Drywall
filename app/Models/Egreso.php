<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Egreso extends Model
{
    protected $table = 'egresos';

    public $timestamps = false;

    protected $fillable = ['fecha', 'tipo', 'categoria', 'descripcion', 'monto', 'venta_id', 'numero_venta', 'usuario_id', 'almacen_id', 'origen', 'origen_id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }
}
