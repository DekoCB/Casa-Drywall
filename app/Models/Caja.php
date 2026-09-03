<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionCaja::class);
    }
}
