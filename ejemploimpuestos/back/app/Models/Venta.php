<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'cliente_id',
        'fecha',
        'hora',
        'ci',
        'nombre',
        'estado',
        'tipo_comprobante',
        'total',
//        'tipo_venta',
        'tipo_pago',
        'agencia',
        'cuf',
        'leyenda',
        'online',
        'cufd'
//        'pagado_interno'
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    function user(){
        return $this->belongsTo(User::class);
    }
    function cliente(){
        return $this->belongsTo(Cliente::class);
    }
    function ventaDetalles(){
        return $this->hasMany(VentaDetalle::class);
    }
    protected $appends = ['detailsText'];
    function getDetailsTextAttribute(){
        $detailsText = '';
        foreach ($this->ventaDetalles as $ventaDetalle) {
            $detailsText .= $ventaDetalle->cantidad . ' ' . $ventaDetalle->producto->nombre . ',';
        }
        $detailsText = substr($detailsText, 0, -1);
        return $detailsText;
    }
}
