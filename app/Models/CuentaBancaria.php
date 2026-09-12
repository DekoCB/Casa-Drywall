<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaBancaria extends Model
{
    protected $table = 'cuentas_bancarias';

    protected $fillable = ['banco', 'abrev', 'moneda', 'titular', 'cuenta', 'cci', 'color', 'bg'];
}
