<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Matriz de productos Kendall: código → factor de galones, presentación,
 * nombre y línea de producto.
 *
 * En el proyecto original vivía en `administrador/matriz_kendall.json` junto a
 * `categorias_kendall.json` y `presentaciones_kendall.json`. Aquí los mismos
 * archivos viven en `storage/app/galonaje/`.
 */
class MatrizGalonaje
{
    /** Disco propio, apuntado a `storage/app/galonaje`. */
    private const DISCO = 'galonaje';

    private const ARCHIVO_MATRIZ = 'matriz_kendall.json';

    private const ARCHIVO_CATEGORIAS = 'categorias_kendall.json';

    private const ARCHIVO_PRESENTACIONES = 'presentaciones_kendall.json';

    private const ARCHIVO_METAS = 'metas_galonaje.json';

    public function productos(): array
    {
        return $this->leer(self::ARCHIVO_MATRIZ);
    }

    public function categorias(): array
    {
        return $this->leer(self::ARCHIVO_CATEGORIAS);
    }

    public function presentaciones(): array
    {
        return $this->leer(self::ARCHIVO_PRESENTACIONES);
    }

    public function metas(): array
    {
        return $this->leer(self::ARCHIVO_METAS);
    }

    /** Datos de galonaje de un código; ceros si el producto no está en la matriz. */
    public function datosDe(string $codigo): array
    {
        $producto = $this->productos()[trim($codigo)] ?? null;

        return [
            'factor_galones' => (float) ($producto['f'] ?? 0),
            'presentacion' => $producto['p'] ?? '',
            'nombre_matriz' => $producto['n'] ?? '',
            'linea' => $producto['l'] ?? '',
        ];
    }

    public function guardarProducto(string $codigo, array $datos): void
    {
        $productos = $this->productos();
        $productos[trim($codigo)] = [
            'f' => (float) ($datos['f'] ?? 0),
            'p' => $datos['p'] ?? '',
            'n' => $datos['n'] ?? '',
            'l' => $datos['l'] ?? '',
        ];

        ksort($productos);
        $this->escribir(self::ARCHIVO_MATRIZ, $productos);
    }

    public function eliminarProducto(string $codigo): void
    {
        $productos = $this->productos();
        unset($productos[trim($codigo)]);
        $this->escribir(self::ARCHIVO_MATRIZ, $productos);
    }

    public function guardarCategoria(string $codigo, string $descripcion, ?string $codigoAnterior = null): void
    {
        $categorias = $this->categorias();
        $codigo = strtoupper(trim($codigo));

        // Si se renombró el código hay que reetiquetar los productos que lo usaban.
        if ($codigoAnterior && strtoupper(trim($codigoAnterior)) !== $codigo) {
            $anterior = strtoupper(trim($codigoAnterior));
            $productos = $this->productos();

            foreach ($productos as &$producto) {
                if (($producto['l'] ?? '') === $anterior) {
                    $producto['l'] = $codigo;
                }
            }
            unset($producto);

            $this->escribir(self::ARCHIVO_MATRIZ, $productos);
            unset($categorias[$anterior]);
        }

        $categorias[$codigo] = ['descripcion' => $descripcion];
        ksort($categorias);

        $this->escribir(self::ARCHIVO_CATEGORIAS, $categorias);
    }

    public function eliminarCategoria(string $codigo): int
    {
        $codigo = strtoupper(trim($codigo));
        $categorias = $this->categorias();

        $enUso = count(array_filter($this->productos(), fn ($p) => ($p['l'] ?? '') === $codigo));

        unset($categorias[$codigo]);
        $this->escribir(self::ARCHIVO_CATEGORIAS, $categorias);

        return $enUso;
    }

    public function guardarPresentacion(string $codigo, array $datos): void
    {
        $presentaciones = $this->presentaciones();
        $presentaciones[strtoupper(trim($codigo))] = $datos;

        ksort($presentaciones);
        $this->escribir(self::ARCHIVO_PRESENTACIONES, $presentaciones);
    }

    public function eliminarPresentacion(string $codigo): void
    {
        $presentaciones = $this->presentaciones();
        unset($presentaciones[strtoupper(trim($codigo))]);
        $this->escribir(self::ARCHIVO_PRESENTACIONES, $presentaciones);
    }

    public function guardarMeta(string $periodo, array $datos): void
    {
        $metas = $this->metas();
        $metas[$periodo] = $datos;

        ksort($metas);
        $this->escribir(self::ARCHIVO_METAS, $metas);
    }

    private function leer(string $archivo): array
    {
        if (! Storage::disk(self::DISCO)->exists($archivo)) {
            return [];
        }

        return json_decode(Storage::disk(self::DISCO)->get($archivo), true) ?: [];
    }

    private function escribir(string $archivo, array $datos): void
    {
        Storage::disk(self::DISCO)->put($archivo, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
