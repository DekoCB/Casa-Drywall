<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    /** Catálogo 09 de SUNAT: motivos de Nota de Crédito. */
    public const MOTIVOS_CREDITO = [
        '01' => 'Anulación de la operación',
        '02' => 'Anulación por error en el RUC',
        '03' => 'Corrección por error en la descripción',
        '04' => 'Descuento global',
        '05' => 'Descuento por ítem',
        '06' => 'Devolución total',
        '07' => 'Devolución por ítem',
        '08' => 'Bonificación',
        '09' => 'Disminución en el valor',
        '10' => 'Otros conceptos',
        '11' => 'Ajustes de operaciones de exportación',
        '12' => 'Ajustes afectos al IVAP',
        '13' => 'Ajustes - montos y/o fechas de pago',
    ];

    /** Catálogo 10 de SUNAT: motivos de Nota de Débito. */
    public const MOTIVOS_DEBITO = [
        '01' => 'Intereses por mora',
        '02' => 'Aumento en el valor',
        '03' => 'Penalidades/otros conceptos',
        '10' => 'Ajustes de operaciones de exportación',
        '11' => 'Ajustes afectos al IVAP',
    ];

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
        // Nota de Crédito/Débito: comprobante que corrige y motivo SUNAT.
        'venta_origen_id', 'cod_motivo',
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

    /** El comprobante (Boleta/Factura) que esta Nota de Crédito/Débito corrige. */
    public function ventaOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'venta_origen_id');
    }

    /** Notas de Crédito/Débito emitidas contra este comprobante. */
    public function notas(): HasMany
    {
        return $this->hasMany(self::class, 'venta_origen_id');
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
