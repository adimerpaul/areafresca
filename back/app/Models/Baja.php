<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Baja extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'bajas';

    protected $fillable = [
        'numero', 'user_id', 'usuario_nombre', 'motivo_id', 'motivo',
        'total_costo', 'estado', 'observacion', 'fecha',
    ];

    protected $casts = [
        'total_costo' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(BajaDetalle::class);
    }

    public function motivoRelacion()
    {
        return $this->belongsTo(BajaMotivo::class, 'motivo_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
