<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fila única (id=1) con la plantilla activa del comprobante en PDF (A4)
 * y del ticket (80mm) — ver resources/views/admin/ventas/comprobante.blade.php,
 * que es el mismo archivo para ambos formatos. Sin fila guardada, ambos
 * quedan en 'pro' (el diseño de siempre, sin personalizar).
 */
class PlantillaImpresion extends Model
{
    protected $table = 'plantilla_impresion';

    protected $fillable = ['pdf', 'ticket'];

    public const PLANTILLAS = [
        'pro' => ['nombre' => 'Plantilla Pro', 'desc' => 'El diseño actual, sin personalizar.'],
        'personalizada' => ['nombre' => 'Personalizada', 'desc' => 'Usa el color de acento y el logo/nombre comercial de Mi Empresa.'],
    ];

    public static function actual(): self
    {
        return static::first() ?? new self(['pdf' => 'pro', 'ticket' => 'pro']);
    }
}
