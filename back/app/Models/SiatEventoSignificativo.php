<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiatEventoSignificativo extends Model
{
    protected $table = 'siat_eventos_significativos';
    protected $fillable = ['codigo_motivo', 'descripcion', 'inicio', 'fin', 'cufd', 'cufd_evento', 'codigo_evento', 'codigo_recepcion', 'estado', 'mensaje', 'venta_ids'];
    protected $casts = ['inicio' => 'datetime', 'fin' => 'datetime', 'venta_ids' => 'array'];
}
