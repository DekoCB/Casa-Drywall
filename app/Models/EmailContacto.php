<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailContacto extends Model
{
    protected $table = 'email_contactos';

    public $timestamps = false;

    protected $fillable = ['nombre', 'correo', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
