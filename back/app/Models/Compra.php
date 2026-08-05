<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Compra extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'numero', 'user_id', 'usuario_nombre', 'proveedor_id', 'proveedor_nombre',
        'numero_factura', 'tipo_pago', 'monto_efectivo', 'monto_qr',
        'comentario', 'total', 'estado', 'fecha',
    ];

    protected $casts = [
        'total' => 'decimal:2', 'monto_efectivo' => 'decimal:2',
        'monto_qr' => 'decimal:2', 'fecha' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }
}
