<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarifaTransporte extends Model
{
    protected $table = 'tarifas_transporte';

    public $timestamps = false;

    protected $fillable = ['empresa_id', 'destino', 'precio_baldes', 'precio_cajas', 'precio_cilindros', 'estado'];

    protected function casts(): array
    {
        return [
            'precio_baldes' => 'decimal:2',
            'precio_cajas' => 'decimal:2',
            'precio_cilindros' => 'decimal:2',
        ];
    }
}
