<?php

namespace App\Services\Siat;

use App\Services\InvoiceDeliveryService;
use App\Models\CertificadoDigital;
use App\Models\Configuracion;
use App\Models\SiatToken;
use App\Models\SiatCufd;
use App\Models\Venta;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SoapClient;

class ElectronicInvoiceService
{
    // false = emisión en línea; true = emisión fuera de línea.
    private const OFFLINE_MODE = false;

    public function __construct(
        private CufGenerator $cufGenerator,
        private XmlSigner $xmlSigner,
        private SiatService $siat,
        private InvoiceDeliveryService $delivery,
    ) {}

    public function issue(Venta $sale): Venta
    {
        $this->log('Inicio de emisión', [
            'venta_id' => $sale->id,
            'numero' => $sale->numero,
            'total' => $sale->total,
        ]);

        try {
            $company = Configuracion::first();
            $token = SiatToken::where('vence_en', '>', now())->latest()->first();
            $certificate = CertificadoDigital::where('activo', true)
                ->where('valido_hasta', '>', now())
                ->latest()
                ->first();

            [$cuis, $cufd] = $this->siat->ensureCredentials();
            [$catalogActivity, $catalogProduct] = $this->siat->catalogDefaults();

            $activity = config('siat.actividad_economica') ?: $catalogActivity;
            $productCode = config('siat.codigo_producto_sin') ?: $catalogProduct;

            $this->validateRequirements(
                company: $company,
                token: $token,
                certificate: $certificate,
                activity: $activity,
            );

            $emissionDate = now();
            $offlineMode = self::OFFLINE_MODE;
            $timestamp = $emissionDate->format('YmdHis')
                .str_pad((string) intval($emissionDate->format('v')), 3, '0', STR_PAD_LEFT);

            $cuf = $this->cufGenerator->generate(
                nit: $company->nit,
                timestamp: $timestamp,
                branch: config('siat.sucursal'),
                modality: config('siat.modalidad'),
                emission: $offlineMode ? 2 : 1,
                invoice: $sale->id,
                pos: config('siat.punto_venta'),
                control: $cufd->codigo_control,
            );

            $xml = $this->buildXml(
                sale: $sale,
                company: $company,
                cufd: $cufd->codigo,
                cuf: $cuf,
                emissionDate: $emissionDate,
                activity: $activity,
                productCode: $productCode,
            );

            $signedXml = $this->xmlSigner->sign(
                $xml,
                $certificate->clave_privada_cifrada,
                $certificate->certificado_pem,
            );

            $this->validateSignedXml($signedXml);

            $xmlPath = "impuestos/facturas/{$sale->id}.xml";
            Storage::disk('local')->put($xmlPath, $signedXml);

            $this->log('XML generado y firmado', [
                'venta_id' => $sale->id,
                'xml_path' => $xmlPath,
                'xml_sha256' => hash('sha256', $signedXml),
            ]);

            $status = ! config('siat.enabled')
                ? 'PENDIENTE_CONFIGURACION'
                : ($offlineMode ? 'PENDIENTE_EVENTO' : 'ENVIANDO');

            $sale->update([
                'cuf' => $cuf,
                'cufd' => $cufd->codigo,
                'xml_path' => $xmlPath,
                'fecha_emision_siat' => $emissionDate,
                'estado_siat' => $status,
                'online' => false,
                'siat_mensaje' => ! config('siat.enabled')
                    ? 'SIAT_ENABLED está desactivado'
                    : ($offlineMode ? 'Emisión fuera de línea activada manualmente' : null),
            ]);

            if (! config('siat.enabled')) {
                return $sale->fresh();
            }

            if ($offlineMode) {
                $this->log('Factura emitida fuera de línea por configuración', [
                    'venta_id' => $sale->id,
                    'tipo_emision' => 2,
                ]);
                $this->deliverToCustomer($sale->fresh());

                return $sale->fresh();
            }

            $response = $this->sendToSiat(
                sale: $sale,
                company: $company,
                token: $token,
                cuis: $cuis->codigo,
                cufd: $cufd->codigo,
                signedXml: $signedXml,
                emissionDate: $emissionDate,
            );

            $this->saveSiatResponse($sale, $response);

            if ((int) ($response->codigoEstado ?? 0) === 908) {
                $this->deliverToCustomer($sale->fresh());
            }
        } catch (\Throwable $exception) {
            $this->handleFailure($sale, $exception);
        }

        return $sale->fresh();
    }

    public function reprepareOfflineInvoice(Venta $sale, Carbon $emissionDate): Venta
    {
        if ($sale->tipo_comprobante !== 'FACTURA' || $sale->online || $sale->estado_siat !== 'PENDIENTE_EVENTO') {
            throw new RuntimeException('Sólo se puede corregir una factura pendiente de envío fuera de línea');
        }
        if ($emissionDate->isFuture()) {
            throw new RuntimeException('La fecha de emisión no puede estar en el futuro');
        }

        $company = Configuracion::firstOrFail();
        $certificate = CertificadoDigital::where('activo', true)->where('valido_hasta', '>', now())->latest()->firstOrFail();
        $cufd = SiatCufd::where('codigo', $sale->cufd)->firstOrFail();
        if ($emissionDate->lt($cufd->created_at) || $emissionDate->gt($cufd->vence_en)) {
            throw new RuntimeException('La nueva fecha debe estar dentro de la vigencia del CUFD original');
        }

        [$catalogActivity, $catalogProduct] = $this->siat->catalogDefaults();
        $activity = config('siat.actividad_economica') ?: $catalogActivity;
        $productCode = config('siat.codigo_producto_sin') ?: $catalogProduct;
        $context = compact('company', 'certificate', 'cufd', 'emissionDate', 'activity', 'productCode');

        $this->prepareOfflineInvoice($sale->loadMissing('detalles'), $context);
        $sale->update(['fecha_emision_siat' => $emissionDate]);
        $this->log('Fecha de factura fuera de línea corregida', ['venta_id' => $sale->id, 'fecha_emision_siat' => $emissionDate->toIso8601String()]);

        return $sale->fresh();
    }

    private function validateRequirements(
        ?Configuracion $company,
        ?SiatToken $token,
        ?CertificadoDigital $certificate,
        ?string $activity,
    ): void {
        $requirements = [
            'Empresa/NIT' => $company?->nit,
            'código de sistema' => config('siat.codigo_sistema'),
            'actividad económica' => $activity,
            'token SIAT' => $token,
            'certificado digital' => $certificate,
        ];

        foreach ($requirements as $name => $value) {
            if (! $value) {
                throw new RuntimeException("Falta {$name} para facturar");
            }
        }
    }

    private function sendToSiat(
        Venta $sale,
        Configuracion $company,
        SiatToken $token,
        string $cuis,
        string $cufd,
        string $signedXml,
        $emissionDate,
    ): object {
        if (! class_exists(SoapClient::class)) {
            throw new RuntimeException('La extensión PHP SOAP no está habilitada');
        }

        $compressedXml = gzencode($signedXml, 9);
        $fileHash = hash('sha256', $compressedXml);

        $this->log('Enviando factura a Impuestos', [
            'venta_id' => $sale->id,
            'archivo_bytes' => strlen($compressedXml),
            'hash_archivo' => $fileHash,
        ]);

        $soapClient = new SoapClient(config('siat.wsdl'), [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => 'apikey: TokenApi '.$token->token_cifrado,
                ],
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => true,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);

        $request = [
            'SolicitudServicioRecepcionFactura' => [
                'codigoAmbiente' => config('siat.ambiente'),
                'codigoDocumentoSector' => 1,
                'codigoEmision' => 1,
                'codigoModalidad' => config('siat.modalidad'),
                'codigoPuntoVenta' => config('siat.punto_venta'),
                'codigoSistema' => config('siat.codigo_sistema'),
                'codigoSucursal' => config('siat.sucursal'),
                'cufd' => $cufd,
                'cuis' => $cuis,
                'nit' => $company->nit,
                'tipoFacturaDocumento' => 1,
                'archivo' => $compressedXml,
                'fechaEnvio' => $emissionDate->format('Y-m-d\TH:i:s.v'),
                'hashArchivo' => $fileHash,
            ],
        ];

        $result = $soapClient->recepcionFactura($request);

        return $result->RespuestaServicioFacturacion ?? $result;
    }

    private function saveSiatResponse(Venta $sale, object $response): void
    {
        $statusCode = $response->codigoEstado ?? null;
        $status = (int) $statusCode === 908 ? 'VALIDADA' : 'OBSERVADA';
        $message = $this->responseMessage($response);

        $sale->update([
            'estado_siat' => $status,
            'online' => $status === 'VALIDADA',
            'codigo_recepcion' => $response->codigoRecepcion ?? null,
            'siat_mensaje' => $message,
        ]);

        $this->log('Emisión finalizada', [
            'venta_id' => $sale->id,
            'codigo_estado' => $statusCode,
            'estado_siat' => $status,
            'codigo_recepcion' => $response->codigoRecepcion ?? null,
            'mensaje' => $message,
        ]);
    }

    private function handleFailure(Venta $sale, \Throwable $exception): void
    {
        $this->log('ERROR emisión', [
            'venta_id' => $sale->id,
            'estado' => 'ERROR_ENVIO',
            'error' => $exception->getMessage(),
            'tipo' => $exception::class,
        ]);

        $sale->update([
            'estado_siat' => 'ERROR_ENVIO',
            'online' => false,
            'siat_mensaje' => $exception->getMessage(),
        ]);

        report($exception);
    }

    private function deliverToCustomer(Venta $sale): void
    {
        try {
            $this->delivery->generatePdf($sale);
            $this->delivery->send($sale->fresh());
        } catch (\Throwable $exception) {
            $sale->update(['email_error' => $exception->getMessage()]);
            $this->log('ERROR correo de factura', [
                'venta_id' => $sale->id,
                'error' => $exception->getMessage(),
            ]);
            report($exception);
        }
    }

    private function prepareOfflineInvoice(Venta $sale, array $context): void
    {
        $date = $context['emissionDate'];
        $timestamp = $date->format('YmdHis').str_pad((string) intval($date->format('v')), 3, '0', STR_PAD_LEFT);
        $cuf = $this->cufGenerator->generate(
            nit: $context['company']->nit,
            timestamp: $timestamp,
            branch: config('siat.sucursal'),
            modality: config('siat.modalidad'),
            emission: 2,
            invoice: $sale->id,
            pos: config('siat.punto_venta'),
            control: $context['cufd']->codigo_control,
        );
        $xml = $this->buildXml($sale, $context['company'], $context['cufd']->codigo, $cuf, $date, $context['activity'], $context['productCode']);
        $signedXml = $this->xmlSigner->sign($xml, $context['certificate']->clave_privada_cifrada, $context['certificate']->certificado_pem);
        $this->validateSignedXml($signedXml);
        $path = "impuestos/facturas/{$sale->id}.xml";
        Storage::disk('local')->put($path, $signedXml);
        $sale->update(['cuf' => $cuf, 'cufd' => $context['cufd']->codigo, 'xml_path' => $path]);
        $this->log('Factura preparada para evento significativo', ['venta_id' => $sale->id, 'tipo_emision' => 2]);
    }

    private function buildXml(
        Venta $sale,
        Configuracion $company,
        string $cufd,
        string $cuf,
        $emissionDate,
        string $activity,
        int $productCode,
    ): string {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElement('facturaElectronicaCompraVenta');
        $root->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance',
        );
        $root->setAttributeNS(
            'http://www.w3.org/2001/XMLSchema-instance',
            'xsi:noNamespaceSchemaLocation',
            'facturaElectronicaCompraVenta.xsd',
        );
        $document->appendChild($root);

        [$lines, $total, $additionalDiscount] = $this->roundedAmounts($sale);

        $header = $root->appendChild($document->createElement('cabecera'));
        $headerFields = [
            'nitEmisor' => $company->nit,
            'razonSocialEmisor' => $company->nombre_empresa,
            'municipio' => config('siat.municipio'),
            'telefono' => $company->telefono ?: '0',
            'numeroFactura' => $sale->id,
            'cuf' => $cuf,
            'cufd' => $cufd,
            'codigoSucursal' => config('siat.sucursal'),
            'direccion' => $company->direccion ?: 'S/D',
            'codigoPuntoVenta' => config('siat.punto_venta'),
            'fechaEmision' => $emissionDate->format('Y-m-d\TH:i:s.v'),
            'nombreRazonSocial' => $sale->cliente_nombre ?: 'SIN NOMBRE',
            'codigoTipoDocumentoIdentidad' => $sale->tipo_documento === 'NIT' ? 5 : 1,
            'numeroDocumento' => $sale->numero_documento,
            'complemento' => $sale->complemento ?: null,
            'codigoCliente' => $sale->numero_documento,
            'codigoMetodoPago' => 1,
            'numeroTarjeta' => null,
            'montoTotal' => $this->money($total),
            'montoTotalSujetoIva' => $this->money($total),
            'codigoMoneda' => 1,
            'tipoCambio' => $this->money(1),
            'montoTotalMoneda' => $this->money($total),
            'montoGiftCard' => null,
            'descuentoAdicional' => $this->money($additionalDiscount),
            'codigoExcepcion' => null,
            'cafc' => null,
            'leyenda' => config('siat.leyenda'),
            'usuario' => $sale->usuario_nombre,
            'codigoDocumentoSector' => 1,
        ];

        $this->appendFields($document, $header, $headerFields);

        foreach ($lines as $line) {
            $item = $line['item'];
            $detail = $root->appendChild($document->createElement('detalle'));
            $detailFields = [
                'actividadEconomica' => $activity,
                'codigoProductoSin' => $productCode,
                'codigoProducto' => $item->codigo_barras ?: $item->codigo,
                'descripcion' => $item->nombre,
                'cantidad' => $this->money($line['cantidad']),
                'unidadMedida' => config('siat.unidad_medida'),
                'precioUnitario' => $this->money($line['precioUnitario']),
                // El descuento comercial de la venta se declara una sola vez en
                // descuentoAdicional (repetirlo aquí haría que SIAT lo reste dos veces).
                // Aquí sólo va el residuo del redondeo a 2 decimales, para que
                // cantidad * precioUnitario - montoDescuento = subTotal exacto.
                'montoDescuento' => $this->money($line['montoDescuento']),
                'subTotal' => $this->money($line['subTotal']),
                'numeroSerie' => null,
                'numeroImei' => null,
            ];

            $this->appendFields($document, $detail, $detailFields);
        }

        return $document->saveXML();
    }

    /**
     * El XSD del SIAT sólo admite 2 decimales en cantidad, precioUnitario,
     * montoDescuento y subTotal, mientras que la venta guarda 3 decimales de
     * cantidad y 4 de precio. Aquí se redondea todo a 2 decimales manteniendo
     * las dos igualdades que valida Impuestos:
     *   cantidad * precioUnitario - montoDescuento = subTotal
     *   suma(subTotal) - descuentoAdicional = montoTotal
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: float}
     */
    private function roundedAmounts(Venta $sale): array
    {
        $lines = [];
        $sumSubTotal = 0.0;

        foreach ($sale->detalles as $item) {
            $quantity = $this->round2((float) $item->cantidad);
            if ($quantity <= 0) {
                $quantity = 0.01;
            }

            // subTotal de la línea antes del descuento comercial.
            $subTotal = $this->round2((float) $item->subtotal);
            if ($subTotal <= 0) {
                // SIAT no acepta precioUnitario ni subTotal en cero: se factura
                // el mínimo y la diferencia se compensa en descuentoAdicional.
                $subTotal = $this->round2($quantity * 0.01);
            }

            // Se redondea hacia arriba para que cantidad * precioUnitario nunca
            // quede por debajo del subTotal y el residuo sea un descuento >= 0.
            $unitPrice = $this->ceil2($subTotal / $quantity);
            $gross = $this->round2($quantity * $unitPrice);

            $lines[] = [
                'item' => $item,
                'cantidad' => $quantity,
                'precioUnitario' => $unitPrice,
                'montoDescuento' => $this->round2($gross - $subTotal),
                'subTotal' => $subTotal,
            ];

            $sumSubTotal = $this->round2($sumSubTotal + $subTotal);
        }

        $total = $this->round2((float) $sale->total);
        $additionalDiscount = $this->round2($sumSubTotal - $total);

        if ($additionalDiscount < 0) {
            // El redondeo dejó las líneas por debajo del total cobrado: se
            // factura la suma de las líneas para no declarar un descuento negativo.
            $total = $sumSubTotal;
            $additionalDiscount = 0.0;
        }

        if ($total <= 0) {
            throw new RuntimeException('El monto total de la factura debe ser mayor a cero');
        }

        return [$lines, $total, $additionalDiscount];
    }

    private function round2(float $value): float
    {
        return round($value, 2);
    }

    private function ceil2(float $value): float
    {
        // El round previo absorbe el error binario (p. ej. 30.80 que se
        // representa como 30.800000000000004 y ascendería a 30.81).
        return ceil(round($value * 100, 6)) / 100;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function appendFields(DOMDocument $document, \DOMElement $parent, array $fields): void
    {
        foreach ($fields as $name => $value) {
            if ($value === null) {
                $element = $document->createElement($name);
                $element->setAttributeNS(
                    'http://www.w3.org/2001/XMLSchema-instance',
                    'xsi:nil',
                    'true',
                );
                $parent->appendChild($element);

                continue;
            }

            $parent->appendChild(
                $document->createElement(
                    $name,
                    htmlspecialchars((string) $value, ENT_XML1),
                ),
            );
        }
    }

    private function validateSignedXml(string $xml): void
    {
        $schemaPath = resource_path('siat/facturaElectronicaCompraVenta.xsd');

        if (! is_file($schemaPath)) {
            throw new RuntimeException("No se encontró el esquema XSD: {$schemaPath}");
        }

        $previousSetting = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument;
        $loaded = $document->loadXML($xml, LIBXML_NONET);
        $valid = $loaded && $document->schemaValidate($schemaPath);

        $errors = array_map(
            fn ($error) => trim($error->message).' (línea '.$error->line.')',
            libxml_get_errors(),
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        if (! $valid) {
            throw new RuntimeException('XML inválido según XSD: '.implode('; ', $errors));
        }

        $this->log('XML validado correctamente contra el XSD');
    }

    private function responseMessage(object $response): ?string
    {
        $messages = $response->mensajesList ?? null;

        if (is_array($messages)) {
            return implode('; ', array_filter(array_map(
                fn ($message) => $message->descripcion ?? null,
                $messages,
            )));
        }

        return $messages?->descripcion;
    }

    private function log(string $message, array $context = []): void
    {
        $suffix = $context
            ? ': '.json_encode($context, JSON_UNESCAPED_UNICODE)
            : '';

        error_log("[SIAT] {$message}{$suffix}");
    }
}
