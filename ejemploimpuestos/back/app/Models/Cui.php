<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cui extends Model{
    use SoftDeletes;
    protected $fillable = [
        'codigo',
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
