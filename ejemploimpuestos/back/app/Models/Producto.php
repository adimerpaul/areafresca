<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'unidad',
        'precio',
        'stock',
        'stock_minimo',
        'stock_maximo',
        'barra',
        'imagen',
    ];

    protected $appends = ['imagen_url'];

    public function getImagenUrlAttribute()
    {
        if ($this->imagen) {
            return asset('/../../images/' . $this->imagen);
        }
        return null;
    }

    public function comprasDetalles()
    {
        return $this->hasMany(CompraDetalle::class);
    }

    // Relación con ventas (si existe)
    public function ventasDetalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }
}
