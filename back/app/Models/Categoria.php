<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Categoria extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $fillable = ['nombre', 'color'];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}
