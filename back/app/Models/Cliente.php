<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tipo_documento', 'numero_documento', 'complemento', 'nombre',
        'email', 'telefono', 'direccion',
    ];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
}
