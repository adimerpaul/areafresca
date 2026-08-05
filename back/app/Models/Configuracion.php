<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';
    protected $fillable = ['nombre_empresa', 'nit', 'direccion', 'telefono', 'logo'];
    protected $appends = ['siat_portal_url'];

    public function getSiatPortalUrlAttribute(): string
    {
        return config('siat.portal_url');
    }
}
