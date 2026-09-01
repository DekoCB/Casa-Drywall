<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenToken extends Model
{
    protected $table = 'orden_tokens';

    public $timestamps = false;

    protected $fillable = ['orden_id', 'token', 'expira_at'];

    protected function casts(): array
    {
        return [
            'expira_at' => 'datetime',
        ];
    }
}
