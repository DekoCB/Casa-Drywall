<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro interno de una compra a un vendedor informal (sin RUC). NO es
 * un comprobante electrónico SUNAT: el paquete de facturación instalado
 * no soporta emitir Liquidación de Compra — ver comprobante.blade.php.
 */
class LiquidacionCompra extends Model
{
    protected $table = 'liquidaciones_compra';

    public $timestamps = false;

    protected $fillable = [
        'numero', 'fecha', 'vendedor_nombre', 'vendedor_documento',
        'proveedor_id', 'productos', 'total', 'observaciones', 'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'productos' => 'array',
            'total' => 'decimal:2',
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
