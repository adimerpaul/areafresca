<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';
    protected $fillable = ['nombre_empresa', 'nit', 'direccion', 'telefono', 'logo'];
}
