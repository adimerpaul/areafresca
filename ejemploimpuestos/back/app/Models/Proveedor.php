<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model{
    use SoftDeletes;
    protected $table = 'proveedores';
    protected $fillable = [
        'nombre',
        'ci',
        'telefono',
        'direccion',
        'email'
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
}
