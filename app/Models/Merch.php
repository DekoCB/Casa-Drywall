<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merch extends Model
{
    protected $table = 'merch';

    public $timestamps = false;

    protected $fillable = ['nombre', 'categoria', 'descripcion', 'precio', 'stock'];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MerchMovimiento::class, 'merch_id');
    }
}
