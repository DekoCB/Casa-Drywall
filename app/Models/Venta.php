<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $table = 'ventas';

    public $timestamps = false;

    protected $fillable = [
        'numero_venta', 'tipo_comprobante', 'subtotal', 'igv', 'total', 'galones_total',
        'metodo_pago', 'fecha', 'observaciones', 'usuario_id', 'almacen_id', 'estado',
        'cliente_id', 'cliente_nombre', 'cliente_ruc', 'cliente_telefono', 'cliente_correo',
        'cliente_direccion', 'cliente_distrito',
        'condicion_pago', 'empresa_transporte', 'vendedor', 'codigo_vendedor',
        'destino_entrega', 'tipo_envio', 'costo_transporte', 'gasto_gasolina',
        'moneda', 'tipo_cambio', 'tiene_regalo', 'regalo_descripcion', 'regalo_precio',
        'tipcomp', 'n_seri', 'n_comp', 'n_ruc', 'razonsocial',
        'baseimp', 'exonerado', 'inafecto', 'tipcambio',
        // Lado de cobranza del mismo comprobante.
        'fecha_vencimiento', 'monto_pagado', 'monto_pendiente', 'estado_cobro',
        'fecha_pago', 'notas_cobranza', 'cobranza_id',
        // Seguimiento del comprobante ante SUNAT vía API-GO (facturación electrónica).
        'estado_factura', 'numero_sunat', 'nota_contadora',
        'api_go_document_id', 'api_go_document_type', 'api_go_pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_vencimiento' => 'date',
            'fecha_pago' => 'date',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'galones_total' => 'decimal:3',
            'costo_transporte' => 'decimal:2',
            'gasto_gasolina' => 'decimal:2',
            'tipo_cambio' => 'decimal:4',
            'tiene_regalo' => 'boolean',
            'regalo_precio' => 'decimal:2',
            'baseimp' => 'decimal:2',
            'exonerado' => 'decimal:2',
            'inafecto' => 'decimal:2',
            'tipcambio' => 'decimal:4',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    public function guias(): HasMany
    {
        return $this->hasMany(GuiaRemision::class, 'venta_id');
    }

    public function scopeDelMes($query, int $anio, int $mes)
    {
        return $query->whereYear('fecha', $anio)->whereMonth('fecha', $mes);
    }

    public function scopeVigentes($query)
    {
        return $query->where('estado', '!=', 'anulada');
    }
}
