<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Catálogo de lubricantes que alimenta el buscador de la orden de compra.
 *
 * En el original vivía como una constante JS dentro de `ordenes_compra.php`,
 * junto a dos JSON con el factor de galón y el peso por unidad. Aquí es un
 * solo archivo de datos que se sirve al navegador ya resuelto.
 */
class CatalogoKendall
{
    /** Nombre legible de cada línea de producto. */
    public const LINEAS = [
        'PCMO'  => 'Motor Gasolina',
        'HDEO'  => 'Motor Diesel',
        'GEAR'  => 'Engranajes',
        'ATF'   => 'Trans. Auto.',
        'HYDO'  => 'Hidráulico',
        'TRANS' => 'Trans. Especial',
        'GRE'   => 'Grasas',
        '4T'    => 'Motocicleta',
        'REFR'  => 'Refrigerante',
        'RLO'   => 'RLO',
    ];

    /** Tipo de base del lubricante. */
    public const BASES = [
        'SYN' => 'Sintético',
        'SB'  => 'Semi-Sint.',
        'MIN' => 'Mineral',
    ];

    /** Búsquedas de ejemplo que aparecen como pastillas bajo el buscador. */
    public const EJEMPLOS = ['0W16,12/1', '20W50', 'SUPER-D 3', 'GT-1 HP', '15W40,3/1', 'CVT', 'GREASE', '4T'];

    /** @return array<int, array<string, mixed>> */
    public function productos(): array
    {
        return Cache::rememberForever('catalogo_kendall', function () {
            $ruta = storage_path('app/galonaje/catalogo_kendall.json');

            if (! is_file($ruta)) {
                return [];
            }

            return json_decode((string) file_get_contents($ruta), true) ?: [];
        });
    }

    /** Factores de galón por presentación, para productos fuera del catálogo. */
    public function presentaciones(): array
    {
        return Cache::rememberForever('presentaciones_kendall', function () {
            $ruta = storage_path('app/galonaje/presentaciones_kendall.json');

            if (! is_file($ruta)) {
                return [];
            }

            $datos = json_decode((string) file_get_contents($ruta), true) ?: [];

            return array_map(fn ($p) => $p['gl'] ?? 0, $datos);
        });
    }
}
