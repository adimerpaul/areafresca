<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Venta extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'numero', 'user_id', 'cliente_id', 'usuario_nombre', 'subtotal', 'descuento',
        'total', 'tipo_pago', 'monto_efectivo', 'monto_qr',
        'estado', 'observacion', 'fecha', 'tipo_documento', 'numero_documento',
        'complemento', 'cliente_nombre', 'cliente_email', 'tipo_comprobante',
        'estado_siat', 'cuf', 'cufd', 'codigo_recepcion', 'xml_path',
        'siat_mensaje', 'fecha_emision_siat',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2', 'descuento' => 'decimal:2',
        'total' => 'decimal:2', 'monto_efectivo' => 'decimal:2',
        'monto_qr' => 'decimal:2', 'fecha' => 'datetime', 'fecha_emision_siat' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
