<?php

namespace Database\Seeders;

use App\Models\Merch;
use Illuminate\Database\Seeder;

/**
 * Catálogo de merchandising con la lista de precios informada por la empresa.
 *
 * El merch se entrega a los clientes de Rental Tech, no se vende: el precio es
 * sólo el costo unitario del artículo, sin impuestos de por medio.
 */
class MerchSeeder extends Seeder
{
    /** Lista informada: nombre, categoría y costo unitario. */
    private const ARTICULOS = [
        ['nombre' => 'Ambientadores', 'categoria' => 'Merchandising', 'precio' => 0.70],
        ['nombre' => 'Polos', 'categoria' => 'Merchandising', 'precio' => 7.15],
        ['nombre' => 'Franelas', 'categoria' => 'Merchandising', 'precio' => 2.20],
    ];

    public function run(): void
    {
        foreach (self::ARTICULOS as $articulo) {
            Merch::updateOrCreate(
                ['nombre' => $articulo['nombre']],
                [
                    'categoria' => $articulo['categoria'],
                    'descripcion' => 'Artículo promocional para entrega a clientes',
                    'precio' => $articulo['precio'],
                ]
            );
        }
    }
}
