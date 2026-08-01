<?php

namespace App\Services\Siat;

use App\Models\{Configuracion, SiatCufd, SiatCuis, SiatToken, Venta};
use RuntimeException;
use SoapClient;

class SiatService
{
    public function ensureCredentials(): array
    {
        $cuis = SiatCuis::where('vence_en', '>', now()->addMinutes(5))->latest()->first();
        if (! $cuis) {
            $response = $this->call('FacturacionCodigos', 'cuis', 'RespuestaCuis', [
                'SolicitudCuis' => $this->baseRequest(false),
            ]);
            $this->assertTransaction($response, 'No se pudo obtener el CUIS');
            $cuis = SiatCuis::create([
                'codigo' => $response->codigo,
                'vence_en' => $response->fechaVigencia,
                'sucursal' => config('siat.sucursal'),
                'punto_venta' => config('siat.punto_venta'),
            ]);
        }

        $cufd = SiatCufd::where('vence_en', '>', now()->addMinutes(5))->latest()->first();
        if (! $cufd) {
            $response = $this->call('FacturacionCodigos', 'cufd', 'RespuestaCufd', [
                'SolicitudCufd' => $this->baseRequest(true, $cuis->codigo),
            ]);
            $this->assertTransaction($response, 'No se pudo obtener el CUFD');
            $cufd = SiatCufd::create([
                'codigo' => $response->codigo,
                'codigo_control' => $response->codigoControl,
                'direccion' => $response->direccion ?? null,
                'vence_en' => $response->fechaVigencia,
                'sucursal' => config('siat.sucursal'),
                'punto_venta' => config('siat.punto_venta'),
            ]);
        }

        return [$cuis, $cufd];
    }

    public function status(): array
    {
        $communication = $this->call('FacturacionCodigos', 'verificarComunicacion', 'RespuestaComunicacion', []);
        [$cuis, $cufd] = $this->ensureCredentials();

        return [
            'online' => (bool) ($communication->transaccion ?? false),
            'mensaje' => $this->message($communication) ?: 'Comunicación correcta con SIAT',
            'ambiente' => config('siat.ambiente') === 1 ? 'PRODUCCIÓN' : 'PRUEBAS',
            'cuis_vence_en' => $cuis->vence_en,
            'cufd_vence_en' => $cufd->vence_en,
        ];
    }

    public function cancellationReasons(): array
    {
        [$cuis] = $this->ensureCredentials();
        $response = $this->call('FacturacionSincronizacion', 'sincronizarParametricaMotivoAnulacion', 'RespuestaListaParametricas', [
            'SolicitudSincronizacion' => $this->baseRequest(true, $cuis->codigo),
        ]);

        return collect($this->asArray($response->listaCodigos ?? []))->map(fn ($item) => [
            'codigo' => (int) $item->codigoClasificador,
            'descripcion' => $item->descripcion,
        ])->values()->all();
    }

    public function catalogDefaults(): array
    {
        [$cuis] = $this->ensureCredentials();
        $request = ['SolicitudSincronizacion' => $this->baseRequest(true, $cuis->codigo)];
        $activities = $this->call('FacturacionSincronizacion', 'sincronizarActividades', 'RespuestaListaActividades', $request);
        $activity = $this->asArray($activities->listaActividades ?? [])[0] ?? null;
        if (! $activity) throw new RuntimeException('SIAT no devolvió actividades económicas autorizadas');
        $products = $this->call('FacturacionSincronizacion', 'sincronizarListaProductosServicios', 'RespuestaListaProductos', $request);
        $product = collect($this->asArray($products->listaCodigos ?? []))->first(
            fn ($item) => (string) $item->codigoActividad === (string) $activity->codigoCaeb
        );
        if (! $product) throw new RuntimeException('SIAT no devolvió productos para la actividad autorizada');

        return [(string) $activity->codigoCaeb, (int) $product->codigoProducto];
    }

    public function verifyInvoice(Venta $sale): array
    {
        abort_unless($sale->cuf, 422, 'La venta no tiene CUF para consultar en SIAT');
        [$cuis, $cufd] = $this->ensureCredentials();
        $response = $this->call('ServicioFacturacionCompraVenta', 'verificacionEstadoFactura', 'RespuestaServicioFacturacion', [
            'SolicitudServicioVerificacionEstadoFactura' => array_merge($this->invoiceRequest($cuis->codigo, $cufd->codigo), ['cuf' => $sale->cuf]),
        ]);
        $sale->update([
            'estado_siat' => ((int) ($response->codigoEstado ?? 0) === 908) ? 'VALIDADA' : $sale->estado_siat,
            'siat_mensaje' => $this->responseDescription($response),
        ]);

        return $this->responseData($response);
    }

    public function cancelInvoice(Venta $sale, int $reason): array
    {
        abort_unless($sale->cuf, 422, 'La venta no tiene CUF para anular en SIAT');
        [$cuis, $cufd] = $this->ensureCredentials();
        $response = $this->call('ServicioFacturacionCompraVenta', 'anulacionFactura', 'RespuestaServicioFacturacion', [
            'SolicitudServicioAnulacionFactura' => array_merge($this->invoiceRequest($cuis->codigo, $cufd->codigo), [
                'codigoMotivo' => $reason, 'cuf' => $sale->cuf,
            ]),
        ]);
        $success = (bool) ($response->transaccion ?? false);
        $sale->update([
            'estado_siat' => $success ? 'ANULADA' : 'OBSERVADA',
            'siat_mensaje' => $this->responseDescription($response),
        ]);

        return $this->responseData($response);
    }

    private function baseRequest(bool $withCuis, ?string $cuis = null): array
    {
        $company = Configuracion::first();
        if (! $company?->nit || ! config('siat.codigo_sistema')) throw new RuntimeException('Faltan NIT o código de sistema SIAT');
        $data = [
            'codigoAmbiente' => config('siat.ambiente'), 'codigoModalidad' => config('siat.modalidad'),
            'codigoPuntoVenta' => config('siat.punto_venta'), 'codigoSistema' => config('siat.codigo_sistema'),
            'codigoSucursal' => config('siat.sucursal'), 'nit' => (int) $company->nit,
        ];
        if ($withCuis) $data['cuis'] = $cuis;
        return $data;
    }

    private function invoiceRequest(string $cuis, string $cufd): array
    {
        return array_merge($this->baseRequest(true, $cuis), [
            'codigoDocumentoSector' => 1, 'codigoEmision' => 1, 'cufd' => $cufd,
            'tipoFacturaDocumento' => 1,
        ]);
    }

    private function call(string $service, string $method, string $responseKey, array $payload): object
    {
        if (! class_exists(SoapClient::class)) throw new RuntimeException('La extensión PHP SOAP no está habilitada');
        $token = SiatToken::where('vence_en', '>', now())->latest()->first();
        if (! $token) throw new RuntimeException('No existe un token SIAT vigente');
        $client = new SoapClient(config('siat.base_url').$service.'?wsdl', [
            'stream_context' => stream_context_create(['http' => ['header' => 'apikey: TokenApi '.$token->token_cifrado]]),
            'cache_wsdl' => WSDL_CACHE_NONE, 'trace' => true, 'exceptions' => true,
        ]);
        $result = $client->{$method}($payload);
        return $result->{$responseKey} ?? $result->return ?? $result;
    }

    private function assertTransaction(object $response, string $fallback): void
    {
        if (! ($response->transaccion ?? false)) throw new RuntimeException($this->message($response) ?: $fallback);
    }

    private function responseData(object $response): array
    {
        return [
            'transaccion' => (bool) ($response->transaccion ?? false),
            'codigo_estado' => $response->codigoEstado ?? null,
            'codigo_recepcion' => $response->codigoRecepcion ?? null,
            'mensaje' => $this->responseDescription($response),
        ];
    }

    private function responseDescription(object $response): string
    {
        return $response->codigoDescripcion ?? $this->message($response) ?: 'Sin descripción';
    }

    private function message(object $response): ?string
    {
        $messages = $this->asArray($response->mensajesList ?? []);
        return implode('; ', array_filter(array_map(fn ($message) => $message->descripcion ?? null, $messages))) ?: null;
    }

    private function asArray(mixed $value): array
    {
        if ($value === null) return [];
        return is_array($value) ? $value : [$value];
    }
}
