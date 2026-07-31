<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraDetalle extends Model
{
    protected $fillable = [
        'compra_id', 'producto_id', 'codigo', 'nombre', 'unidad', 'lote',
        'fecha_vencimiento', 'cantidad', 'precio_unitario', 'total',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date', 'cantidad' => 'decimal:3',
        'precio_unitario' => 'decimal:4', 'total' => 'decimal:2',
    ];
}
