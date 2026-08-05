<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlmacenDetalle extends Model
{
    protected $table = 'almacen_detalles';

    protected $fillable = [
        'almacen_id', 'producto_id', 'user_id', 'usuario_nombre',
        'codigo', 'nombre', 'unidad', 'foto', 'lote', 'fecha_vencimiento',
        'stock_sistema', 'cantidad', 'stock_anterior', 'stock_nuevo', 'diferencia',
        'precio_compra', 'total', 'observacion',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'stock_sistema' => 'decimal:3',
        'stock_anterior' => 'decimal:3',
        'stock_nuevo' => 'decimal:3',
        'diferencia' => 'decimal:3',
        'cantidad' => 'decimal:3',
        'precio_compra' => 'decimal:4',
        'total' => 'decimal:2',
    ];

    public function almacen()
    {
        return $this->belongsTo(Almacen::class);
    }

    public function conteos()
    {
        return $this->hasMany(AlmacenDetalleConteo::class, 'almacen_detalle_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function lote()
    {
        return $this->hasOne(Lote::class, 'almacen_detalle_id');
    }
}
