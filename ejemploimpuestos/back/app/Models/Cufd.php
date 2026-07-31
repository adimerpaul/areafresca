<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cufd extends Model{
    use SoftDeletes;
    protected $fillable = [
        'codigo',
        'codigoControl',
        'direccion',
        'fechaVigencia',
        'fechaCreacion',
        'codigoPuntoVenta',
        'codigoSucursal',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
