<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostoMensual extends Model
{
    protected $table = 'costos_mensuales';

    public $timestamps = false;

    protected $fillable = ['anio', 'mes', 'costo_productos', 'gastos_operativos'];

    protected function casts(): array
    {
        return [
            'costo_productos' => 'decimal:2',
            'gastos_operativos' => 'decimal:2',
        ];
    }
}
