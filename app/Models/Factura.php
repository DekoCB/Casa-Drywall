<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Factura pendiente por pagar a GP Maquinarias SAC.
 *
 * El estado sale de la fecha de vencimiento, salvo que se haya fijado a mano
 * (`estado_manual`), y las canceladas quedan al margen de todos los totales.
 */
class Factura extends Model
{
    protected $table = 'facturas';

    protected $fillable = [
        'numero', 'doc', 'guia_remision', 'emision', 'vencimiento',
        'importe', 'tc', 'galones', 'producto', 'cliente',
        'cancelado', 'estado_manual', 'productos_lista', 'pdf',
    ];

    /** Estados que el usuario puede fijar desde el modal de edición. */
    public const ESTADOS = ['', 'vigente', 'por_vencer', 'vencida', 'pagada'];

    protected function casts(): array
    {
        return [
            'emision'         => 'date',
            'vencimiento'     => 'date',
            'importe'         => 'decimal:2',
            'tc'              => 'decimal:2',
            'galones'         => 'decimal:2',
            'cancelado'       => 'boolean',
            'productos_lista' => 'array',
        ];
    }

    /** Importe convertido a soles con el tipo de cambio de la propia factura. */
    public function importeSoles(): float
    {
        return round((float) $this->importe * (float) $this->tc, 2);
    }

    /** Días que faltan para vencer; negativo si ya venció. */
    public function diasParaVencer(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->vencimiento->startOfDay(), false);
    }

    /**
     * Estado mostrado en la tabla. Una factura cancelada lo es por encima de
     * todo; después manda el estado fijado a mano y, si no hay, la fecha.
     */
    public function estado(): string
    {
        if ($this->cancelado) {
            return 'cancelado';
        }

        if ($this->estado_manual !== '') {
            return $this->estado_manual;
        }

        $dias = $this->diasParaVencer();

        return match (true) {
            $dias < 0   => 'vencida',
            $dias <= 15 => 'por_vencer',
            default     => 'vigente',
        };
    }

    /**
     * Mora: días transcurridos desde el vencimiento. El original la clasifica
     * en alta (≥50), media (≥30) y baja.
     */
    public function diasMora(): int
    {
        return max(0, -$this->diasParaVencer());
    }

    public function nivelMora(): string
    {
        $dias = $this->diasMora();

        return match (true) {
            $dias >= 50 => 'alta',
            $dias >= 30 => 'media',
            default     => 'baja',
        };
    }

    /** Cuenta para los totales sólo si no está cancelada ni pagada. */
    public function estaActiva(): bool
    {
        return ! $this->cancelado && $this->estado_manual !== 'pagada';
    }

    /** Nombre con el que se guarda el PDF adjunto. */
    public function archivoPdf(): string
    {
        return preg_replace('/[^a-zA-Z0-9\-]/', '_', $this->numero).'.pdf';
    }
}
