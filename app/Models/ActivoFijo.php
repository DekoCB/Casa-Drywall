<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivoFijo extends Model
{
    protected $table = 'activos_fijos';

    public $timestamps = false;

    protected $fillable = [
        'codigo', 'nombre', 'categoria', 'proveedor_id', 'fecha_compra',
        'costo', 'estado', 'ubicacion', 'observaciones', 'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_compra' => 'date',
            'costo' => 'decimal:2',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
