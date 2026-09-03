<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaSuspendida extends Model
{
    protected $table = 'ventas_suspendidas';

    public $timestamps = false;

    protected $fillable = ['usuario_id', 'cliente_etiqueta', 'total_referencial', 'datos'];

    protected function casts(): array
    {
        return [
            'total_referencial' => 'decimal:2',
            'datos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
