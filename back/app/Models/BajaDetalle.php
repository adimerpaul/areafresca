<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BajaDetalle extends Model
{
    protected $table = 'baja_detalles';

    protected $fillable = [
        'baja_id', 'producto_id', 'codigo', 'nombre', 'unidad', 'foto',
        'cantidad', 'precio_compra', 'total', 'observacion',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio_compra' => 'decimal:4',
        'total' => 'decimal:2',
    ];

    public function baja()
    {
        return $this->belongsTo(Baja::class);
    }
}
