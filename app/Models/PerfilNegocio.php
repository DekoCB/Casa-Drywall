<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Fila única (id=1) con el nombre comercial, título web y logos del
 * negocio. Mismo patrón que EstiloSistema: sin fila guardada, no cambia
 * nada — el sistema sigue usando lo que ya tenía en config/rentaltech.php
 * y los archivos estáticos de public/img/.
 */
class PerfilNegocio extends Model
{
    protected $table = 'perfil_negocio';

    protected $fillable = [
        'razon_social', 'nombre_comercial', 'titulo_web',
        'logo_claro', 'logo_oscuro', 'favicon', 'logo_app',
    ];

    public static function actual(): self
    {
        return static::first() ?? new self([
            'razon_social' => config('rentaltech.empresa.razon_social'),
        ]);
    }

    /** Lo que va en la pestaña del navegador — el más específico que haya. */
    public function tituloWeb(): ?string
    {
        return $this->titulo_web ?: ($this->nombre_comercial ?: $this->razon_social);
    }

    public function logoClaroUrl(): ?string
    {
        return $this->logo_claro ? Storage::disk('public')->url($this->logo_claro) : null;
    }

    public function logoOscuroUrl(): ?string
    {
        return $this->logo_oscuro ? Storage::disk('public')->url($this->logo_oscuro) : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon ? Storage::disk('public')->url($this->favicon) : null;
    }

    public function logoAppUrl(): ?string
    {
        return $this->logo_app ? Storage::disk('public')->url($this->logo_app) : null;
    }
}
