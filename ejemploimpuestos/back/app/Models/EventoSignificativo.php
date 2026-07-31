<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventoSignificativo extends Model{
    use SoftDeletes;
    protected $fillable = [
        'codigoPuntoVenta',
        'codigoSucursal',
        'fechaHoraFinEvento',
        'fechaHoraInicioEvento',
        'codigoMotivoEvento',
        'descripcion',
        'cufd',
        'cufdEvento',
        'codigoRecepcion',
        'codigoRecepcionEventoSignificativo',
        'cufd_id'
    ];
}
