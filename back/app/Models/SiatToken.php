<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SiatToken extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'token_cifrado', 'vence_en',
    ];

    protected $hidden = ['token_cifrado'];
    protected $auditExclude = ['token_cifrado'];

    protected function casts(): array
    {
        return [
            'token_cifrado' => 'encrypted', 'vence_en' => 'datetime',
        ];
    }
}
