<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    protected $table = 'personal';

    public $timestamps = false;

    protected $fillable = ['dni', 'nombres', 'apellidos', 'cargo', 'area', 'telefono', 'email', 'direccion', 'fecha_nacimiento', 'fecha_ingreso', 'sueldo', 'tipo_contrato', 'usuario_id', 'estado'];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_ingreso' => 'date',
            'sueldo' => 'decimal:2',
        ];
    }
}
