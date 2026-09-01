<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    public $timestamps = false;

    protected $fillable = ['tipo_documento', 'numero_documento', 'nombres', 'nombre_empresa', 'telefono', 'email', 'direccion', 'distrito', 'provincia', 'departamento', 'fecha_cumpleanos', 'estado'];

    protected function casts(): array
    {
        return [
            'fecha_cumpleanos' => 'date',
        ];
    }
}
