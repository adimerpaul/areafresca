<?php

namespace App\Services\Siat;

use App\Models\CertificadoDigital;
use App\Models\Configuracion;
use App\Models\SiatToken;
use App\Models\Venta;
use DOMDocument;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SoapClient;

class ElectronicInvoiceService
{
    public function __construct(
        private CufGenerator $cufGenerator,
        private XmlSigner $xmlSigner,
        private SiatService $siat,
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
            $timestamp = $emissionDate->format('YmdHis')
                .str_pad((string) intval($emissionDate->format('v')), 3, '0', STR_PAD_LEFT);

            $cuf = $this->cufGenerator->generate(
                nit: $company->nit,
                timestamp: $timestamp,
                branch: config('siat.sucursal'),
                modality: config('siat.modalidad'),
                emission: 1,
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

            $sale->update([
                'cuf' => $cuf,
                'cufd' => $cufd->codigo,
                'xml_path' => $xmlPath,
                'fecha_emision_siat' => $emissionDate,
                'estado_siat' => config('siat.enabled') ? 'ENVIANDO' : 'PENDIENTE_CONFIGURACION',
                'siat_mensaje' => config('siat.enabled') ? null : 'SIAT_ENABLED está desactivado',
            ]);

            if (! config('siat.enabled')) {
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
        } catch (\Throwable $exception) {
            $this->handleFailure($sale, $exception);
        }

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
            'estado' => 'PENDIENTE_EVENTO',
            'error' => $exception->getMessage(),
            'tipo' => $exception::class,
        ]);

        $sale->update([
            'estado_siat' => 'PENDIENTE_EVENTO',
            'siat_mensaje' => $exception->getMessage(),
        ]);

        report($exception);
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
            'montoTotal' => $sale->total,
            'montoTotalSujetoIva' => $sale->total,
            'codigoMoneda' => 1,
            'tipoCambio' => 1,
            'montoTotalMoneda' => $sale->total,
            'montoGiftCard' => null,
            'descuentoAdicional' => $sale->descuento,
            'codigoExcepcion' => null,
            'cafc' => null,
            'leyenda' => config('siat.leyenda'),
            'usuario' => $sale->usuario_nombre,
            'codigoDocumentoSector' => 1,
        ];

        $this->appendFields($document, $header, $headerFields);

        foreach ($sale->detalles as $item) {
            $detail = $root->appendChild($document->createElement('detalle'));
            $detailFields = [
                'actividadEconomica' => $activity,
                'codigoProductoSin' => $productCode,
                'codigoProducto' => $item->codigo_barras ?: $item->codigo,
                'descripcion' => $item->nombre,
                'cantidad' => $item->cantidad,
                'unidadMedida' => config('siat.unidad_medida'),
                'precioUnitario' => $item->precio_venta,
                // El descuento de la venta se declara una sola vez en
                // descuentoAdicional. Repetirlo aquí hace que SIAT lo reste dos veces.
                'montoDescuento' => 0,
                'subTotal' => $item->subtotal,
                'numeroSerie' => null,
                'numeroImei' => null,
            ];

            $this->appendFields($document, $detail, $detailFields);
        }

        return $document->saveXML();
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
