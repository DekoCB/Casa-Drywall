<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    public $timestamps = false;

    protected $fillable = ['tipo_documento', 'numero_documento', 'nombres', 'nombre_empresa', 'telefono', 'email', 'direccion', 'distrito', 'provincia', 'departamento', 'fecha_cumpleanos', 'estado'];

    protected function casts(): array
    {
        return [
            'fecha_cumpleanos' => 'date',
        ];
    }

    /**
     * Ficha del cliente para un comprobante, creándola si no existe.
     *
     * Ventas registra al cliente en el mismo momento en que le emite la
     * Boleta/Factura/Nota de Venta: así queda guardado para la próxima
     * compra sin tener que darlo de alta aparte en el módulo Clientes.
     * Solo se crea con un DNI (8) o RUC (11) válido y un nombre — "Cliente
     * Varios" o un documento incompleto no generan ficha.
     */
    public static function registrarDesdeComprobante(?string $documento, ?string $nombre, array $ubicacion = []): ?self
    {
        $documento = preg_replace('/\D/', '', (string) $documento);
        $nombre = trim((string) $nombre);

        if (mb_strtolower($nombre) === 'cliente varios') {
            return null;
        }

        if ($documento !== '') {
            $existente = self::whereRaw("REPLACE(REPLACE(REPLACE(TRIM(numero_documento), ' ', ''), '-', ''), '.', '') = ?", [$documento])->first();

            if ($existente) {
                return $existente;
            }
        }

        $esDocumentoValido = strlen($documento) === 8 || strlen($documento) === 11;

        if (! $esDocumentoValido || $nombre === '') {
            return null;
        }

        $esRuc = strlen($documento) === 11;

        return self::create([
            'tipo_documento'   => $esRuc ? 'RUC' : 'DNI',
            'numero_documento' => $documento,
            'nombres'          => $nombre,
            'nombre_empresa'   => $esRuc ? $nombre : null,
            'direccion'        => ($ubicacion['direccion'] ?? null) ?: null,
            'distrito'         => ($ubicacion['distrito'] ?? null) ?: null,
            'provincia'        => ($ubicacion['provincia'] ?? null) ?: null,
            'departamento'     => ($ubicacion['departamento'] ?? null) ?: null,
            'estado'           => 'activo',
        ]);
    }
}
