<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAlmacen extends Model
{
    protected $table = 'stock_almacen';

    public $timestamps = false;

    protected $fillable = ['producto_id', 'almacen_id', 'stock'];

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
        ];
    }
}
