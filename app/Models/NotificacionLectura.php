<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marca de "ya lo vi" por usuario sobre una notificación calculada en vivo
 * (ver `CentroNotificaciones`). No guarda el hecho en sí — solo su `clave`.
 */
class NotificacionLectura extends Model
{
    public $timestamps = false;

    protected $fillable = ['usuario_id', 'clave', 'leido_en'];

    protected function casts(): array
    {
        return ['leido_en' => 'datetime'];
    }
}
