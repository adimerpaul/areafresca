<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Compra extends Model{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'proveedor_id',
        'fecha',
        'hora',
        'ci',
        'nombre',
        'estado',
        'tipo_pago',
        'total',
        'nro_factura',
        'agencia'
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    function user(){
        return $this->belongsTo(User::class);
    }
    function proveedor(){
        return $this->belongsTo(Proveedor::class);
    }
    function compraDetalles(){
        return $this->hasMany(CompraDetalle::class);
    }
    protected $appends = ['detailsText'];
    function getDetailsTextAttribute(){
        $detailsText = '';
        foreach ($this->compraDetalles as $compraDetalle) {
            $detailsText .= $compraDetalle->cantidad . ' ' . $compraDetalle->producto->nombre . ',';
        }
        $detailsText = substr($detailsText, 0, -1);
        return $detailsText;
    }
}
