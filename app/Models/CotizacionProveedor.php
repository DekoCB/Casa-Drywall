<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Solicitud de precio a un proveedor — no confundir con la Cotización de venta (`Venta::tipcomp = 'COT'`). */
class CotizacionProveedor extends Model
{
    protected $table = 'cotizaciones_proveedor';

    public $timestamps = false;

    protected $fillable = [
        'numero', 'fecha', 'proveedor_id', 'productos', 'estado', 'observaciones', 'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'productos' => 'array',
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
