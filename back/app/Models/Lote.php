<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lote extends Model
{
    protected $fillable = [
        'producto_id', 'compra_detalle_id', 'lote', 'fecha_vencimiento',
        'cantidad_inicial', 'cantidad_disponible',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date', 'cantidad_inicial' => 'decimal:3',
        'cantidad_disponible' => 'decimal:3',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
