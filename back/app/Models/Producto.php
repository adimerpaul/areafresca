<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Producto extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'codigo', 'codigo_barras', 'nombre', 'categoria', 'categoria_id', 'unidad',
        'precio_compra', 'precio_venta', 'precio_1', 'precio_2', 'precio_3', 'precio_4',
        'stock_inicial', 'fecha_registro', 'foto',
    ];

    public function categoriaRelacion()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    protected $casts = [
        'precio_compra' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'precio_1' => 'decimal:2',
        'precio_2' => 'decimal:2',
        'precio_3' => 'decimal:2',
        'precio_4' => 'decimal:2',
        'stock_inicial' => 'decimal:3',
        'fecha_registro' => 'date',
    ];
}
