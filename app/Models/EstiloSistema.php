<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fila única (id=1) con la apariencia elegida para todo el sistema: color
 * de acento y tipografía. `actual()` la crea con los valores de Casa
 * Drywall la primera vez que se pide.
 */
class EstiloSistema extends Model
{
    protected $table = 'estilos_sistema';

    protected $fillable = ['color', 'fuente'];

    public const PALETAS = [
        'amarillo' => ['nombre' => 'Amarillo (actual)', 'brand' => '#D9CB16', 'brand2' => '#B8AC13', 'bg_claro' => '#FBF8D9', 'bg_oscuro' => 'rgba(217,203,22,0.16)', 'on_brand' => '#1A1A18'],
        'azul' => ['nombre' => 'Azul', 'brand' => '#2563EB', 'brand2' => '#1D4ED8', 'bg_claro' => '#DBEAFE', 'bg_oscuro' => 'rgba(37,99,235,0.18)', 'on_brand' => '#FFFFFF'],
        'verde' => ['nombre' => 'Verde', 'brand' => '#16A34A', 'brand2' => '#15803D', 'bg_claro' => '#DCFCE7', 'bg_oscuro' => 'rgba(22,163,74,0.18)', 'on_brand' => '#FFFFFF'],
        'morado' => ['nombre' => 'Morado', 'brand' => '#7C3AED', 'brand2' => '#6D28D9', 'bg_claro' => '#EDE9FE', 'bg_oscuro' => 'rgba(124,58,237,0.18)', 'on_brand' => '#FFFFFF'],
        'naranja' => ['nombre' => 'Naranja', 'brand' => '#EA580C', 'brand2' => '#C2410C', 'bg_claro' => '#FFEDD5', 'bg_oscuro' => 'rgba(234,88,12,0.18)', 'on_brand' => '#FFFFFF'],
        'rosa' => ['nombre' => 'Rosa', 'brand' => '#DB2777', 'brand2' => '#BE185D', 'bg_claro' => '#FCE7F3', 'bg_oscuro' => 'rgba(219,39,119,0.18)', 'on_brand' => '#FFFFFF'],
        'rojo' => ['nombre' => 'Rojo', 'brand' => '#DC2626', 'brand2' => '#B91C1C', 'bg_claro' => '#FEE2E2', 'bg_oscuro' => 'rgba(220,38,38,0.18)', 'on_brand' => '#FFFFFF'],
        'cian' => ['nombre' => 'Cian', 'brand' => '#0D9488', 'brand2' => '#0F766E', 'bg_claro' => '#CCFBF1', 'bg_oscuro' => 'rgba(13,148,136,0.18)', 'on_brand' => '#FFFFFF'],
    ];

    public const FUENTES = [
        'casa-drywall' => ['nombre' => 'Casa Drywall (actual)', 'display' => 'Audiowide', 'sans' => 'Montserrat', 'google' => 'family=Audiowide:wght@400&family=Montserrat:wght@400;500;600;700;800'],
        'elegante' => ['nombre' => 'Elegante', 'display' => 'Playfair Display', 'sans' => 'Inter', 'google' => 'family=Playfair+Display:wght@400;600;700&family=Inter:wght@400;500;600;700'],
        'moderno' => ['nombre' => 'Moderno', 'display' => 'Space Grotesk', 'sans' => 'Inter', 'google' => 'family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700'],
        'corporativo' => ['nombre' => 'Corporativo', 'display' => 'Poppins', 'sans' => 'Roboto', 'google' => 'family=Poppins:wght@400;600;700&family=Roboto:wght@400;500;700'],
        'industrial' => ['nombre' => 'Industrial', 'display' => 'Orbitron', 'sans' => 'Rajdhani', 'google' => 'family=Orbitron:wght@400;600;700&family=Rajdhani:wght@400;500;600;700'],
        'minimalista' => ['nombre' => 'Minimalista', 'display' => 'Work Sans', 'sans' => 'Work Sans', 'google' => 'family=Work+Sans:wght@400;500;600;700'],
    ];

    /**
     * Instancia actual sin forzar su creación en base de datos — así una
     * visita cualquiera nunca "fija" el amarillo por defecto y pisa el
     * color propio de otra empresa (ver `partials.brand-color`). Solo
     * `exists` es true cuando el negocio guardó una elección real.
     */
    public static function actual(): self
    {
        return static::first() ?? new self(['color' => 'amarillo', 'fuente' => 'casa-drywall']);
    }

    public function paleta(): array
    {
        return self::PALETAS[$this->color] ?? self::PALETAS['amarillo'];
    }

    public function tipografia(): array
    {
        return self::FUENTES[$this->fuente] ?? self::FUENTES['casa-drywall'];
    }
}
