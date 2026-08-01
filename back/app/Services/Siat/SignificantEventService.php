<?php

namespace App\Services\Siat;

use App\Models\Configuracion;
use App\Models\SiatEventoSignificativo;
use App\Models\SiatToken;
use App\Models\Venta;
use App\Services\InvoiceDeliveryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PharData;
use RuntimeException;
use SoapClient;

class SignificantEventService
{
    public const REASONS = [
        1 => 'CORTE DEL SERVICIO DE INTERNET',
        2 => 'INACCESIBILIDAD AL SERVICIO WEB DE LA ADMINISTRACIÓN TRIBUTARIA',
        3 => 'INGRESO A ZONAS SIN INTERNET POR DESPLIEGUE DE PUNTO DE VENTA',
        4 => 'VENTA EN LUGARES SIN INTERNET',
        5 => 'VIRUS INFORMÁTICO O FALLA DE SOFTWARE',
        6 => 'CAMBIO DE INFRAESTRUCTURA O FALLA DE HARDWARE',
        7 => 'CORTE DE SUMINISTRO DE ENERGÍA ELÉCTRICA',
    ];

    public function __construct(private SiatService $siat, private InvoiceDeliveryService $delivery) {}

    public function process(int $reason, string $description, $start, $end): SiatEventoSignificativo
    {
        set_time_limit(180);
        $sales = Venta::with(['detalles', 'cliente', 'usuario'])
            ->where('tipo_comprobante', 'FACTURA')
            ->where('estado_siat', 'PENDIENTE_EVENTO')
            ->whereBetween('fecha_emision_siat', [$start, $end])
            ->whereNotNull('xml_path')
            ->get();
        if ($sales->isEmpty()) throw new RuntimeException('No existen facturas pendientes dentro del periodo indicado');

        [$cuis, $currentCufd] = $this->siat->ensureCredentials();
        $eventCufd = (string) $sales->first()->cufd;
        if ($sales->contains(fn (Venta $sale) => (string) $sale->cufd !== $eventCufd)) {
            throw new RuntimeException('Las facturas seleccionadas pertenecen a diferentes CUFD; procese cada periodo por separado');
        }
        $event = SiatEventoSignificativo::create([
            'codigo_motivo' => $reason, 'descripcion' => $description,
            'inicio' => $start, 'fin' => $end,
            'cufd' => $currentCufd->codigo, 'cufd_evento' => $eventCufd,
            'venta_ids' => $sales->pluck('id')->all(),
        ]);

        try {
            $eventCode = $this->register($event, $cuis->codigo);
            $event->update(['codigo_evento' => $eventCode, 'estado' => 'REGISTRADO']);
            $receptionCode = $this->sendPackage($event, $sales, $cuis->codigo);
            $event->update(['codigo_recepcion' => $receptionCode, 'estado' => 'EN_VALIDACION']);
            $validation = $this->validatePackage($currentCufd->codigo, $cuis->codigo, $receptionCode);
            $event->update(['estado' => 'VALIDADO', 'mensaje' => $validation]);
            foreach ($sales as $sale) {
                $sale->update(['estado_siat' => 'VALIDADA', 'codigo_recepcion' => $receptionCode, 'siat_mensaje' => $validation]);
                try {
                    $this->delivery->generatePdf($sale->fresh());
                    $this->delivery->send($sale->fresh());
                } catch (\Throwable $mailError) {
                    $sale->update(['email_error' => $mailError->getMessage()]);
                    error_log('[CORREO][FACTURA] ERROR paquete: '.$mailError->getMessage());
                }
            }
        } catch (\Throwable $exception) {
            $event->update(['estado' => 'ERROR', 'mensaje' => $exception->getMessage()]);
            throw $exception;
        }

        return $event->fresh();
    }

    private function register(SiatEventoSignificativo $event, string $cuis): string
    {
        $response = $this->soap('FacturacionOperaciones')->registroEventoSignificativo([
            'SolicitudEventoSignificativo' => array_merge($this->baseRequest($cuis), [
                'codigoMotivoEvento' => $event->codigo_motivo,
                'cufd' => $event->cufd, 'cufdEvento' => $event->cufd_evento,
                'descripcion' => $event->descripcion,
                'fechaHoraInicioEvento' => $event->inicio->format('Y-m-d\TH:i:s.v'),
                'fechaHoraFinEvento' => $event->fin->format('Y-m-d\TH:i:s.v'),
            ]),
        ])->RespuestaListaEventos;
        error_log('[SIAT][EVENTO] Registro: '.json_encode($response, JSON_UNESCAPED_UNICODE));
        if (! ($response->transaccion ?? false) || empty($response->codigoRecepcionEventoSignificativo)) {
            throw new RuntimeException($this->message($response, 'SIAT rechazó el evento significativo'));
        }
        return (string) $response->codigoRecepcionEventoSignificativo;
    }

    private function sendPackage(SiatEventoSignificativo $event, Collection $sales, string $cuis): string
    {
        $directory = storage_path('app/private/impuestos/paquetes');
        if (! is_dir($directory)) mkdir($directory, 0755, true);
        $tarPath = $directory."/evento_{$event->id}.tar";
        $gzPath = $tarPath.'.gz';
        foreach ([$tarPath, $gzPath] as $path) if (is_file($path)) unlink($path);
        $archive = new PharData($tarPath);
        foreach ($sales as $sale) {
            $xmlPath = Storage::disk('local')->path($sale->xml_path);
            if (! is_file($xmlPath)) throw new RuntimeException("No se encontró el XML de la venta {$sale->id}");
            $archive->addFile($xmlPath, "{$sale->id}.xml");
        }
        $archive->compress(\Phar::GZ);
        unset($archive);
        $file = file_get_contents($gzPath);
        $response = $this->soap('ServicioFacturacionCompraVenta')->recepcionPaqueteFactura([
            'SolicitudServicioRecepcionPaquete' => array_merge($this->invoiceRequest($cuis, $event->cufd), [
                'archivo' => $file, 'fechaEnvio' => now()->format('Y-m-d\TH:i:s.v'),
                'hashArchivo' => hash('sha256', $file), 'cantidadFacturas' => $sales->count(),
                'codigoEvento' => $event->codigo_evento,
            ]),
        ])->RespuestaServicioFacturacion;
        error_log('[SIAT][EVENTO] Recepción paquete: '.json_encode($response, JSON_UNESCAPED_UNICODE));
        if (empty($response->codigoRecepcion)) throw new RuntimeException($this->message($response, 'SIAT no recibió el paquete'));
        return (string) $response->codigoRecepcion;
    }

    private function validatePackage(string $cufd, string $cuis, string $reception): string
    {
        $client = $this->soap('ServicioFacturacionCompraVenta');
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            if ($attempt > 1) sleep(1);
            $response = $client->validacionRecepcionPaqueteFactura([
                'SolicitudServicioValidacionRecepcionPaquete' => array_merge($this->invoiceRequest($cuis, $cufd), ['codigoRecepcion' => $reception]),
            ])->RespuestaServicioFacturacion;
            error_log("[SIAT][EVENTO] Validación paquete intento {$attempt}: ".json_encode($response, JSON_UNESCAPED_UNICODE));
            if ((int) ($response->codigoEstado ?? 0) === 908 || strtoupper((string) ($response->codigoDescripcion ?? '')) === 'VALIDADA') {
                return $this->message($response, 'PAQUETE VALIDADO');
            }
        }
        throw new RuntimeException('SIAT no validó el paquete dentro del tiempo esperado');
    }

    private function soap(string $service): SoapClient
    {
        $token = SiatToken::where('vence_en', '>', now())->latest()->firstOrFail();
        return new SoapClient(config('siat.base_url').$service.'?wsdl', [
            'stream_context' => stream_context_create(['http' => ['header' => 'apikey: TokenApi '.$token->token_cifrado]]),
            'cache_wsdl' => WSDL_CACHE_NONE, 'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => true, 'use' => SOAP_LITERAL, 'style' => SOAP_DOCUMENT,
        ]);
    }

    private function baseRequest(string $cuis): array
    {
        return ['codigoAmbiente' => config('siat.ambiente'), 'codigoPuntoVenta' => config('siat.punto_venta'), 'codigoSistema' => config('siat.codigo_sistema'), 'codigoSucursal' => config('siat.sucursal'), 'cuis' => $cuis, 'nit' => (int) Configuracion::firstOrFail()->nit];
    }

    private function invoiceRequest(string $cuis, string $cufd): array
    {
        return array_merge($this->baseRequest($cuis), ['codigoDocumentoSector' => 1, 'codigoEmision' => 2, 'codigoModalidad' => config('siat.modalidad'), 'cufd' => $cufd, 'tipoFacturaDocumento' => 1]);
    }

    private function message(object $response, string $fallback): string
    {
        $messages = $response->mensajesList ?? null;
        $items = is_array($messages) ? $messages : ($messages ? [$messages] : []);
        return implode('; ', array_filter(array_map(fn ($item) => $item->descripcion ?? null, $items))) ?: ((string) ($response->codigoDescripcion ?? $fallback));
    }
}
