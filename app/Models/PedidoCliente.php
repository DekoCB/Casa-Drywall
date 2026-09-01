<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoCliente extends Model
{
    protected $table = 'pedidos_clientes';

    public $timestamps = false;

    protected $fillable = ['fecha', 'cliente_nombre', 'ruc', 'telefono', 'destino', 'empresa_transporte', 'productos', 'total_soles', 'estado', 'observaciones', 'archivo_pedido'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total_soles' => 'decimal:2',
        ];
    }
}
