<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesionCaja extends Model
{
    protected $table = 'sesiones_caja';

    public $timestamps = false;

    protected $fillable = [
        'caja_id', 'usuario_id', 'monto_inicial', 'monto_final_esperado',
        'monto_final_contado', 'diferencia', 'estado', 'observaciones',
        'abierta_en', 'cerrada_en',
    ];

    protected function casts(): array
    {
        return [
            'monto_inicial' => 'decimal:2',
            'monto_final_esperado' => 'decimal:2',
            'monto_final_contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'abierta_en' => 'datetime',
            'cerrada_en' => 'datetime',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class);
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->where('estado', 'abierta');
    }

    /**
     * Efectivo esperado en el cajón: el inicial más solo los movimientos en
     * efectivo (Yape/Plin/tarjeta nunca deben "faltar" físicamente).
     */
    public function calcularEsperado(): float
    {
        $movimientosEfectivo = (float) $this->movimientos()
            ->where('metodo_pago', 'Efectivo')
            ->sum('monto');

        return round((float) $this->monto_inicial + $movimientosEfectivo, 2);
    }
}
