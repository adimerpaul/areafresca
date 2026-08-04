<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Almacen extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'almacenes';

    protected $fillable = [
        'numero', 'user_id', 'usuario_nombre', 'descripcion', 'estado', 'observacion',
        'total_cantidad', 'total_costo', 'fecha', 'fecha_aplicado',
        'aplicado_por', 'aplicado_por_nombre',
    ];

    protected $casts = [
        'total_cantidad' => 'decimal:3',
        'total_costo' => 'decimal:2',
        'fecha' => 'datetime',
        'fecha_aplicado' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(AlmacenDetalle::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function editable(): bool
    {
        return $this->estado === 'BORRADOR';
    }
}
