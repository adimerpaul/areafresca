<?php

namespace App\Services;

use App\Models\CertificadoDigital;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DigitalCertificateService
{
    public function import(UploadedFile $file, string $password, ?int $userId): CertificadoDigital
    {
        $binary = file_get_contents($file->getRealPath());
        if ($binary === false) throw new RuntimeException('No se pudo leer el archivo P12.');

        $certificates = [];
        if (! openssl_pkcs12_read($binary, $certificates, $password)) {
            $this->clearOpenSslErrors();
            throw new RuntimeException('El archivo P12 o su contrasena no son validos.');
        }

        $certificatePem = $certificates['cert'] ?? null;
        $privateKeyPem = $certificates['pkey'] ?? null;
        if (! $certificatePem || ! $privateKeyPem) {
            throw new RuntimeException('El P12 no contiene el certificado y la clave privada requeridos.');
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (! $privateKey) {
            throw new RuntimeException('No se pudo interpretar la clave privada incluida en el P12.');
        }

        $details = openssl_x509_parse($certificatePem, false);
        $publicResource = openssl_pkey_get_public($certificatePem);
        $publicDetails = $publicResource ? openssl_pkey_get_details($publicResource) : false;
        if (! $details || ! $publicDetails || empty($publicDetails['key'])) {
            throw new RuntimeException('No se pudo interpretar el certificado digital.');
        }

        // Se conserva el PEM PKCS#8 que entrega el P12 para que XMLSecLibs pueda
        // firmar sin conversiones incompatibles con OpenSSL 3. En la base queda
        // cifrado mediante el cast "encrypted" y el archivo vive fuera de public/.
        $privatePem = $privateKeyPem;
        $internalPassword = bin2hex(random_bytes(24));

        $rawFingerprint = openssl_x509_fingerprint($certificatePem, 'sha256');
        if (! $rawFingerprint) throw new RuntimeException('No se pudo calcular la huella del certificado.');
        $fingerprint = strtoupper(implode(':', str_split($rawFingerprint, 2)));
        return DB::transaction(function () use ($file, $binary, $details, $fingerprint, $privatePem, $publicDetails, $certificatePem, $internalPassword, $userId) {
            CertificadoDigital::query()->update(['activo' => false]);

            $attributes = [
                'nombre_archivo' => $file->getClientOriginalName(),
                'numero_serie' => $details['serialNumberHex'] ?? ($details['serialNumber'] ?? null),
                'titular' => $details['subject'] ?? null,
                'emisor' => $details['issuer'] ?? null,
                'valido_desde' => isset($details['validFrom_time_t']) ? date('Y-m-d H:i:s', $details['validFrom_time_t']) : null,
                'valido_hasta' => isset($details['validTo_time_t']) ? date('Y-m-d H:i:s', $details['validTo_time_t']) : null,
                'archivo_p12_cifrado' => base64_encode($binary),
                'clave_privada_cifrada' => $privatePem,
                'clave_publica' => $publicDetails['key'],
                'certificado_pem' => $certificatePem,
                'contrasena_interna_cifrada' => $internalPassword,
                'activo' => true,
                'creado_por' => $userId,
            ];

            $certificate = CertificadoDigital::query()->create([
                ...$attributes,
                'huella_sha256' => $fingerprint,
            ]);

            $directory = 'impuestos/certificados/'.$certificate->id;
            try {
                $written = Storage::disk('local')->put($directory.'/private.key', $privatePem)
                    && Storage::disk('local')->put($directory.'/public.key', $certificatePem)
                    && Storage::disk('local')->put($directory.'/certificate.pem', $certificatePem);
                if (! $written) throw new RuntimeException('No se pudieron guardar las claves en el almacenamiento privado.');
                @chmod(Storage::disk('local')->path($directory.'/private.key'), 0600);
                $certificate->update(['ruta_directorio' => $directory]);
            } catch (Throwable $exception) {
                Storage::disk('local')->deleteDirectory($directory);
                throw $exception;
            }

            return $certificate->fresh();
        });
    }

    private function clearOpenSslErrors(): void
    {
        while (openssl_error_string() !== false) {}
    }
}
