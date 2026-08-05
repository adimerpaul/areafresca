<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un lote contado dentro de la lÃ­nea de un producto (lote + vencimiento + cantidad). */
class AlmacenDetalleConteo extends Model
{
    protected $table = 'almacen_detalle_conteos';

    protected $fillable = ['almacen_detalle_id', 'lote', 'fecha_vencimiento', 'cantidad'];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'cantidad' => 'decimal:3',
    ];

    public function detalle()
    {
        return $this->belongsTo(AlmacenDetalle::class, 'almacen_detalle_id');
    }
}
