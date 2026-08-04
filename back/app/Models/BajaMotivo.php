<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BajaMotivo extends Model
{
    protected $table = 'baja_motivos';

    protected $fillable = ['codigo', 'nombre', 'color', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }
}
