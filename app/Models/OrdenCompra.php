<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenCompra extends Model
{
    protected $table = 'ordenes_compra';

    public $timestamps = false;

    protected $fillable = [
        'numero_orden', 'fecha', 'proveedor', 'ruc', 'telefono', 'correo', 'direccion',
        'distrito', 'provincia', 'departamento', 'nro_factura', 'nro_guia', 'ref_fecha',
        'empresa_transporte', 'cliente_ref', 'vendedor', 'cod_vendedor', 'peso', 'bultos',
        'tc', 'precio_venta', 'gasto_unit', 'estado', 'condicion_pago', 'observaciones',
        'total_usd', 'total_soles', 'productos', 'merch',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'productos' => 'array',
            'merch' => 'array',
            'bultos' => 'integer',
            'tc' => 'decimal:4',
            'precio_venta' => 'decimal:2',
            'gasto_unit' => 'decimal:2',
            'total_usd' => 'decimal:2',
            'total_soles' => 'decimal:2',
        ];
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(OrdenToken::class, 'orden_id');
    }
}
