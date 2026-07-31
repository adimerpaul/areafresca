<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class CertificadoDigital extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'certificados_digitales';

    protected $fillable = [
        'nombre_archivo', 'numero_serie', 'huella_sha256', 'titular', 'emisor',
        'valido_desde', 'valido_hasta', 'archivo_p12_cifrado', 'clave_privada_cifrada',
        'clave_publica', 'certificado_pem', 'contrasena_interna_cifrada',
        'ruta_directorio', 'activo', 'creado_por',
    ];

    protected $hidden = [
        'archivo_p12_cifrado', 'clave_privada_cifrada', 'clave_publica',
        'certificado_pem', 'contrasena_interna_cifrada', 'ruta_directorio',
    ];

    protected $auditExclude = [
        'archivo_p12_cifrado',
        'clave_privada_cifrada',
        'contrasena_interna_cifrada',
        'clave_publica',
        'certificado_pem',
        'ruta_directorio',
    ];

    protected function casts(): array
    {
        return [
            'titular' => 'array', 'emisor' => 'array', 'valido_desde' => 'datetime',
            'valido_hasta' => 'datetime', 'activo' => 'boolean',
            'archivo_p12_cifrado' => 'encrypted', 'clave_privada_cifrada' => 'encrypted',
            'contrasena_interna_cifrada' => 'encrypted',
        ];
    }
}
