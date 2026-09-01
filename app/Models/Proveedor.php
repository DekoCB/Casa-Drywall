<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    public $timestamps = false;

    protected $fillable = ['ruc', 'razon_social', 'contacto', 'telefono', 'email', 'direccion', 'distrito', 'provincia', 'departamento', 'fecha_cumpleanos', 'productos_suministra', 'condiciones_pago', 'dias_credito', 'estado'];

    protected function casts(): array
    {
        return [
            'fecha_cumpleanos' => 'date',
            'dias_credito' => 'integer',
        ];
    }
}
