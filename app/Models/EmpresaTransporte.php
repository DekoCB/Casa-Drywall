<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpresaTransporte extends Model
{
    protected $table = 'empresas_transporte';

    public $timestamps = false;

    protected $fillable = ['nombre', 'estado'];
}
