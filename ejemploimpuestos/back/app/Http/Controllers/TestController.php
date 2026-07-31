<?php

namespace App\Http\Controllers;

use App\Models\Cufd;
use App\Models\Cui;
use Illuminate\Http\Request;
use SimpleXMLElement;
use DOMDocument;

class TestController extends Controller
{
    function index()
    {
        $token = env('TOKEN');
        $nit = env('NIT');
        $ambiente = env('AMBIENTE');
        $codigoSucursal = 0;
        $codigoModalidad = env('MODALIDAD');
        $codigoEmision = 1;
        $tipoFacturaDocumento = 1; // 1 con credito fiscal 2 sin credito fiscal 3 nota credito debito
        $codigoDocumentoSector = 1; //1 compra venta, 13 servicios basicos, 24 nota credito debito, 29 nota conciliacion
        $nf = 1; // numero de factura
        $codigoPuntoVenta = 0;
        $codigoSistema = env('CODIGO_SISTEMA');

        $fechaEnvio = date("Y-m-d\TH:i:s.000");
        $cuf = new CUF();
        $cuf = $cuf->obtenerCUF(
            $nit,
            date("YmdHis000"),
            $codigoSucursal,
            $codigoModalidad,
            $codigoEmision,
            $tipoFacturaDocumento,
            $codigoDocumentoSector,
            $nf,
            $codigoPuntoVenta
        );
        $cuis = Cui::where('codigoPuntoVenta', $codigoPuntoVenta)
            ->where('codigoSucursal', $codigoSucursal)
            ->where('fechaVigencia', '>=', now())
            ->first();
        if (!$cuis) {
            return response()->json(['message' => 'No existe CUI para la venta!!'], 400);
        }
        $cufd = Cufd::where('codigoPuntoVenta', $codigoPuntoVenta)
            ->where('codigoSucursal', $codigoSucursal)
            ->where('fechaVigencia', '>=', now())
            ->first();
        if (!$cufd) {
            return response()->json(['message' => 'No existe CUFD para la venta!!'], 400);
        }
//        return $cuf;
        $cuf = $cuf . $cufd->codigoControl;
        $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?>
<facturaElectronicaCompraVenta xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:noNamespaceSchemaLocation='facturaElectronicaCompraVenta.xsd'>
<cabecera>
        <nitEmisor>$nit</nitEmisor>
        <razonSocialEmisor>Carlos Loza</razonSocialEmisor>
        <municipio>La Paz</municipio>
        <telefono>2846005</telefono>
        <numeroFactura>1</numeroFactura>
        <cuf>" . $cuf . "</cuf>
        <cufd>" . $cufd->codigo . "</cufd>
        <codigoSucursal>0</codigoSucursal>
        <direccion>AV. JORGE LOPEZ #123</direccion>
        <codigoPuntoVenta>$codigoPuntoVenta</codigoPuntoVenta>
        <fechaEmision>$fechaEnvio</fechaEmision>
        <nombreRazonSocial>Mi razon social</nombreRazonSocial>
        <codigoTipoDocumentoIdentidad>1</codigoTipoDocumentoIdentidad>
        <numeroDocumento>5115889</numeroDocumento>
        <complemento xsi:nil='true'/>
        <codigoCliente>51158891</codigoCliente>
        <codigoMetodoPago>1</codigoMetodoPago>
        <numeroTarjeta xsi:nil='true'/>
        <montoTotal>99</montoTotal>
        <montoTotalSujetoIva>99</montoTotalSujetoIva>
        <codigoMoneda>1</codigoMoneda>
        <tipoCambio>1</tipoCambio>
        <montoTotalMoneda>99</montoTotalMoneda>
        <montoGiftCard xsi:nil='true'/>
        <descuentoAdicional>1</descuentoAdicional>
        <codigoExcepcion xsi:nil='true'/>
        <cafc xsi:nil='true'/>
        <leyenda>Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los
            servicios que utilices.
        </leyenda>
        <usuario>pperez</usuario>
        <codigoDocumentoSector>1</codigoDocumentoSector>
    </cabecera>
        <detalle>
        <actividadEconomica>477300</actividadEconomica>
        <codigoProductoSin>99100</codigoProductoSin>
        <codigoProducto>JN-131231</codigoProducto>
        <descripcion>JUGO DE NARANJA EN VASO</descripcion>
        <cantidad>1</cantidad>
        <unidadMedida>1</unidadMedida>
        <precioUnitario>100</precioUnitario>
        <montoDescuento>0</montoDescuento>
        <subTotal>100</subTotal>
        <numeroSerie>124548</numeroSerie>
        <numeroImei>545454</numeroImei>
    </detalle>
</facturaElectronicaCompraVenta>");
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $nameFile = 1;
        $dom->save("archivos/" . $nameFile . '.xml');

        $firmar = new Firmar();
        $firmar->firmar("archivos/" . $nameFile . '.xml');


        $xml = new DOMDocument();
        $xml->load("archivos/" . $nameFile . '.xml');
        if (!$xml->schemaValidate('facturaElectronicaCompraVenta.xsd')) {
            echo "invalid";
        }

        $file = "archivos/" . $nameFile . '.xml';
        $gzfile = "archivos/" . $nameFile . '.xml' . '.gz';
        $fp = gzopen($gzfile, 'w9');
        gzwrite($fp, file_get_contents($file));
        gzclose($fp);

        $archivo = $firmar->getFileGzip("archivos/" . $nameFile . '.xml' . '.gz');
        $hashArchivo = hash('sha256', $archivo);

        $client = new \SoapClient("https://pilotosiatservicios.impuestos.gob.bo/v2/ServicioFacturacionCompraVenta?WSDL", [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "apikey: TokenApi " . $token,
                ]
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);
        $result = $client->recepcionFactura([
            "SolicitudServicioRecepcionFactura" => [
                "codigoAmbiente" => $ambiente,
                "codigoDocumentoSector" => $codigoDocumentoSector,
                "codigoEmision" => $codigoEmision,
                "codigoModalidad" => $codigoModalidad,
                "codigoPuntoVenta" => $codigoPuntoVenta,
                "codigoSistema" => $codigoSistema,
                "codigoSucursal" => $codigoSucursal,
                "cufd" => $cufd,
                "cuis" => $cuis->codigo,
                "nit" => $nit,
                "tipoFacturaDocumento" => $tipoFacturaDocumento,
                "archivo" => $archivo,
                "fechaEnvio" => $fechaEnvio,
                "hashArchivo" => $hashArchivo,
            ]
        ]);
        return response()->json($result);
    }
}
