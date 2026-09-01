<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuiaRemision extends Model
{
    protected $table = 'guias_remision';

    public $timestamps = false;

    protected $fillable = [
        'numero_guia', 'venta_id', 'numero_venta', 'fecha', 'fecha_traslado',
        'motivo_traslado', 'cliente_nombre', 'cliente_ruc', 'cliente_direccion',
        'cliente_distrito', 'cliente_provincia', 'cliente_departamento',
        'punto_partida', 'punto_llegada', 'empresa_transporte', 'transportista_ruc',
        'placa_vehiculo', 'licencia_conductor', 'conductor_nombre', 'peso_total',
        'bultos', 'observaciones', 'productos', 'estado', 'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_traslado' => 'date',
            'productos' => 'array',
            'bultos' => 'integer',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}
