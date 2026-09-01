<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaMensual extends Model
{
    protected $table = 'metas_mensuales';

    public $timestamps = false;

    protected $fillable = ['anio', 'mes', 'meta_galones', 'meta_monto'];

    protected function casts(): array
    {
        return [
            'meta_galones' => 'decimal:2',
            'meta_monto' => 'decimal:2',
        ];
    }
}
