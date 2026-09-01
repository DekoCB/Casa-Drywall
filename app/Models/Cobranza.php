<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cobranza extends Model
{
    /** Tipos de comprobante que maneja el módulo, con su etiqueta. */
    public const TIPOS = [
        'FT' => 'FT — Factura',
        'BV' => 'BV — Boleta',
        'NC' => 'NC — Nota Crédito',
        'OT' => 'OT — Otro',
    ];

    protected $table = 'cobranzas';

    public $timestamps = false;

    protected $fillable = [
        'tipo', 'numero', 'fecha_emision', 'fecha_vencimiento', 'cliente_nombre',
        'cliente_id', 'monto_total', 'monto_pagado', 'monto_pendiente', 'estado',
        'fecha_pago', 'observaciones', 'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'fecha_pago' => 'date',
            'monto_total' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'monto_pendiente' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    /** Días de atraso respecto al vencimiento; 0 si aún no vence o ya está pagada. */
    public function getDiasVencidoAttribute(): int
    {
        if ($this->estado === 'pagada' || ! $this->fecha_vencimiento) {
            return 0;
        }

        return max(0, $this->fecha_vencimiento->diffInDays(now(), false));
    }

    /** El importador dejó fechas fuera de rango que hay que corregir a mano. */
    public function vencimientoInvalido(): bool
    {
        if (! $this->fecha_vencimiento) {
            return true;
        }

        return $this->fecha_vencimiento->year < 2000 || $this->fecha_vencimiento->year > 2100;
    }

    /**
     * Días transcurridos desde el vencimiento: positivo si ya venció, negativo
     * si aún falta. `null` cuando la fecha no sirve.
     */
    public function diasVencidos(): ?int
    {
        if ($this->vencimientoInvalido()) {
            return null;
        }

        return (int) $this->fecha_vencimiento->startOfDay()->diffInDays(now()->startOfDay(), false);
    }

    /**
     * Recalcula el saldo y el estado.
     *
     * El sistema solo maneja dos estados: una cobranza está pagada cuando no
     * queda saldo, y pendiente en cualquier otro caso. Un pago parcial deja la
     * cobranza pendiente, y el vencimiento se muestra aparte (ver
     * `dias_vencido`) sin cambiar el estado.
     */
    public function recalcularEstado(): void
    {
        $this->monto_pendiente = round(max(0, (float) $this->monto_total - (float) $this->monto_pagado), 2);

        $this->estado = $this->monto_pendiente <= 0.01 ? 'pagada' : 'pendiente';
    }

    // ── Sincronización con el registro de ventas ────────────────────────────

    /**
     * `ventas` es el registro único del comprobante emitido. Cada vez que se
     * guarda o borra una cobranza se refleja allí, para que las dos vistas
     * cuenten siempre lo mismo.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $cobranza) => $cobranza->reflejarEnVentas());
        static::deleted(fn (self $cobranza) => Venta::where('cobranza_id', $cobranza->id)->delete());
    }

    /** Código SUNAT equivalente al tipo de comprobante de cobranzas. */
    private function tipoSunat(): string
    {
        return match (strtoupper(trim((string) $this->tipo))) {
            'BV' => '03',
            'NC' => '07',
            default => '01',
        };
    }

    /** Separa el número en serie y correlativo; van juntos en `numero`. */
    private function partirNumero(): array
    {
        $texto = trim((string) $this->numero);

        return preg_match('/^([A-Za-z]+\d*)\s*[- ]?\s*(\d+)$/', $texto, $partes)
            ? [$partes[1], $partes[2]]
            : ['', $texto];
    }

    /** Crea o actualiza la fila de `ventas` que representa esta cobranza. */
    public function reflejarEnVentas(): void
    {
        [$serie, $correlativo] = $this->partirNumero();

        $datos = [
            'fecha'             => $this->fecha_emision,
            'fecha_vencimiento' => $this->fecha_vencimiento,
            'tipcomp'           => $this->tipoSunat(),
            'n_seri'            => $serie,
            'n_comp'            => $correlativo,
            'razonsocial'       => $this->cliente_nombre,
            'cliente_id'        => $this->cliente_id,
            'cliente_nombre'    => $this->cliente_nombre,
            'total'             => $this->monto_total,
            'monto_pagado'      => $this->monto_pagado,
            'monto_pendiente'   => $this->monto_pendiente,
            'estado_cobro'      => $this->estado,
            'fecha_pago'        => $this->fecha_pago,
            'notas_cobranza'    => $this->observaciones,
        ];

        $venta = Venta::where('cobranza_id', $this->id)->first();

        if ($venta) {
            $venta->update($datos);

            return;
        }

        // Cobranzas no lleva el desglose fiscal; queda en cero por completar.
        Venta::create($datos + [
            'cobranza_id' => $this->id,
            'baseimp'     => 0,
            'exonerado'   => 0,
            'inafecto'    => 0,
            'igv'         => 0,
            'estado'      => 'activa',
            'usuario_id'  => $this->usuario_id,
            'tipcambio'   => 1,
        ]);
    }
}
