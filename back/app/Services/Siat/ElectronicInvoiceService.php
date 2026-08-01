<?php
namespace App\Services\Siat;

use App\Models\{CertificadoDigital,Configuracion,SiatCufd,SiatCuis,SiatToken,Venta};
use DOMDocument; use RuntimeException; use SoapClient;

class ElectronicInvoiceService {
    public function __construct(private CufGenerator $cuf, private XmlSigner $signer, private SiatService $siat) {}
    public function issue(Venta $sale): Venta {
        error_log('[SIAT] Inicio emisión: '.json_encode(['venta_id'=>$sale->id,'numero'=>$sale->numero,'total'=>$sale->total], JSON_UNESCAPED_UNICODE));
        try {
            $company=Configuracion::first(); $token=SiatToken::where('vence_en','>',now())->latest()->first();
            $cert=CertificadoDigital::where('activo',true)->where('valido_hasta','>',now())->latest()->first();
            [$cuis,$cufd]=$this->siat->ensureCredentials();
            [$catalogActivity,$catalogProduct]=$this->siat->catalogDefaults();
            $activity=config('siat.actividad_economica')?:$catalogActivity; $productCode=config('siat.codigo_producto_sin')?:$catalogProduct;
            foreach ([['Empresa/NIT',$company?->nit],['código de sistema',config('siat.codigo_sistema')],['actividad económica',$activity],['token SIAT',$token],['certificado digital',$cert]] as [$name,$value]) if(!$value) throw new RuntimeException("Falta {$name} para facturar");
            $date=now(); $stamp=$date->format('YmdHis').str_pad((string)intval($date->format('v')),3,'0',STR_PAD_LEFT);
            $cuf=$this->cuf->generate($company->nit,$stamp,config('siat.sucursal'),config('siat.modalidad'),1,$sale->id,config('siat.punto_venta'),$cufd->codigo_control);
            $xml=$this->buildXml($sale,$company,$cufd->codigo,$cuf,$date,$activity,$productCode); $signed=$this->signer->sign($xml,$cert->clave_privada_cifrada,$cert->certificado_pem);
            $path="impuestos/facturas/{$sale->id}.xml"; \Storage::disk('local')->put($path,$signed);
            error_log('[SIAT] XML generado y firmado: '.json_encode(['venta_id'=>$sale->id,'xml_path'=>$path,'xml_sha256'=>hash('sha256',$signed)], JSON_UNESCAPED_UNICODE));
            $sale->update(['cuf'=>$cuf,'cufd'=>$cufd->codigo,'xml_path'=>$path,'fecha_emision_siat'=>$date,'estado_siat'=>config('siat.enabled')?'ENVIANDO':'PENDIENTE_CONFIGURACION','siat_mensaje'=>config('siat.enabled')?null:'SIAT_ENABLED está desactivado']);
            if(!config('siat.enabled')) return $sale->fresh();
            if(!class_exists(SoapClient::class)) throw new RuntimeException('La extensión PHP SOAP no está habilitada');
            $gz=gzencode($signed,9); error_log('[SIAT] Enviando factura a Impuestos: '.json_encode(['venta_id'=>$sale->id,'archivo_bytes'=>strlen($gz),'hash_archivo'=>hash('sha256',$gz)], JSON_UNESCAPED_UNICODE)); $client=new SoapClient(config('siat.wsdl'),['stream_context'=>stream_context_create(['http'=>['header'=>'apikey: TokenApi '.$token->token_cifrado]]),'cache_wsdl'=>WSDL_CACHE_NONE,'trace'=>1]);
            $result=$client->recepcionFactura(['SolicitudServicioRecepcionFactura'=>['codigoAmbiente'=>config('siat.ambiente'),'codigoDocumentoSector'=>1,'codigoEmision'=>1,'codigoModalidad'=>config('siat.modalidad'),'codigoPuntoVenta'=>config('siat.punto_venta'),'codigoSistema'=>config('siat.codigo_sistema'),'codigoSucursal'=>config('siat.sucursal'),'cufd'=>$cufd->codigo,'cuis'=>$cuis->codigo,'nit'=>$company->nit,'tipoFacturaDocumento'=>1,'archivo'=>$gz,'fechaEnvio'=>$date->format('Y-m-d\TH:i:s.v'),'hashArchivo'=>hash('sha256',$gz)]]);
            $response=$result->RespuestaServicioFacturacion??$result; $code=$response->codigoEstado??null; $sale->update(['estado_siat'=>$code==908?'VALIDADA':'OBSERVADA','codigo_recepcion'=>$response->codigoRecepcion??null,'siat_mensaje'=>$response->mensajesList?->descripcion??null]);
            error_log('[SIAT] Emisión finalizada: '.json_encode(['venta_id'=>$sale->id,'codigo_estado'=>$code,'estado_siat'=>$code==908?'VALIDADA':'OBSERVADA','codigo_recepcion'=>$response->codigoRecepcion??null,'mensaje'=>$response->mensajesList?->descripcion??null], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) { error_log('[SIAT] ERROR emisión: '.json_encode(['venta_id'=>$sale->id,'estado'=>'PENDIENTE_EVENTO','error'=>$e->getMessage(),'tipo'=>$e::class], JSON_UNESCAPED_UNICODE)); $sale->update(['estado_siat'=>'PENDIENTE_EVENTO','siat_mensaje'=>$e->getMessage()]); report($e); }
        return $sale->fresh();
    }
    private function buildXml(Venta $sale, Configuracion $company, string $cufd, string $cuf, $date, string $activity, int $productCode): string {
        $doc=new DOMDocument('1.0','UTF-8'); $doc->formatOutput=true; $root=$doc->createElement('facturaElectronicaCompraVenta'); $root->setAttributeNS('http://www.w3.org/2000/xmlns/','xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance'); $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance','xsi:noNamespaceSchemaLocation','facturaElectronicaCompraVenta.xsd'); $doc->appendChild($root); $head=$root->appendChild($doc->createElement('cabecera'));
        $fields=['nitEmisor'=>$company->nit,'razonSocialEmisor'=>$company->nombre_empresa,'municipio'=>config('siat.municipio'),'telefono'=>$company->telefono?:'0','numeroFactura'=>$sale->id,'cuf'=>$cuf,'cufd'=>$cufd,'codigoSucursal'=>config('siat.sucursal'),'direccion'=>$company->direccion?:'S/D','codigoPuntoVenta'=>config('siat.punto_venta'),'fechaEmision'=>$date->format('Y-m-d\TH:i:s.v'),'nombreRazonSocial'=>$sale->cliente_nombre?:'SIN NOMBRE','codigoTipoDocumentoIdentidad'=>$sale->tipo_documento==='NIT'?5:1,'numeroDocumento'=>$sale->numero_documento,'codigoCliente'=>$sale->numero_documento,'codigoMetodoPago'=>1,'montoTotal'=>$sale->total,'montoTotalSujetoIva'=>$sale->total,'codigoMoneda'=>1,'tipoCambio'=>1,'montoTotalMoneda'=>$sale->total,'descuentoAdicional'=>$sale->descuento,'leyenda'=>config('siat.leyenda'),'usuario'=>$sale->usuario_nombre,'codigoDocumentoSector'=>1]; foreach($fields as $k=>$v)$head->appendChild($doc->createElement($k,htmlspecialchars((string)$v,ENT_XML1)));
        foreach($sale->detalles as $item){$d=$root->appendChild($doc->createElement('detalle')); foreach(['actividadEconomica'=>$activity,'codigoProductoSin'=>$productCode,'codigoProducto'=>$item->codigo_barras?:$item->codigo,'descripcion'=>$item->nombre,'cantidad'=>$item->cantidad,'unidadMedida'=>config('siat.unidad_medida'),'precioUnitario'=>$item->precio_venta,'montoDescuento'=>$item->descuento,'subTotal'=>$item->total] as $k=>$v)$d->appendChild($doc->createElement($k,htmlspecialchars((string)$v,ENT_XML1)));}
        return $doc->saveXML();
    }
}
