<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingreso extends Model
{
    protected $table = 'ingresos';

    public $timestamps = false;

    protected $fillable = ['fecha', 'tipo', 'descripcion', 'monto', 'metodo_pago'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }
}
