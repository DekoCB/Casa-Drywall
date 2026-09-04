<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoAlmacen extends Model
{
    protected $table = 'movimientos_almacen';

    public $timestamps = false;

    protected $fillable = ['producto_id', 'almacen_id', 'tipo', 'cantidad', 'stock_anterior', 'stock_nuevo', 'motivo', 'referencia', 'usuario_id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'stock_anterior' => 'integer',
            'stock_nuevo' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
