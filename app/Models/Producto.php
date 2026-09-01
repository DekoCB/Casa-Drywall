<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'productos';

    public $timestamps = false;

    protected $fillable = [
        'codigo', 'nombre', 'categoria_id', 'marca_id', 'presentacion', 'viscosidad',
        'descripcion', 'especificaciones', 'precio_compra', 'precio_venta',
        'precio_alquiler', 'stock', 'stock_minimo', 'peso', 'imagen', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'precio_compra' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'precio_alquiler' => 'decimal:2',
            'peso' => 'decimal:3',
            'stock' => 'integer',
            'stock_minimo' => 'integer',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function stockPorAlmacen(): HasMany
    {
        return $this->hasMany(StockAlmacen::class, 'producto_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoAlmacen::class, 'producto_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /** Recalcula `stock` como la suma del stock de todos los almacenes. */
    public function recalcularStock(): void
    {
        $this->stock = (int) $this->stockPorAlmacen()->sum('stock');
        $this->save();
    }
}
