<?php

require_once __DIR__ . '/datos_generales.php';

$siat = obtenerDatosSiat(0);
$codigoPuntoVenta = $siat['codigoPuntoVenta'];
$cuis = $siat['cuis'];
$nit = $siat['nit'];
$token = $siat['token'];
$codigoAmbiente = $siat['codigoAmbiente'];
$codigoSistema = $siat['codigoSistema'];
$codigoSucursal = $siat['codigoSucursal'];
$modalidad = $siat['codigoModalidad'];

$contador = 1;

for ($i=0; $i < $contador; $i++) {
    $client = new \SoapClient("https://siatrest.impuestos.gob.bo/v2/FacturacionCodigos?WSDL",  [
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
    $data = [
        "SolicitudCuis"=>[
            "codigoAmbiente"=>$codigoAmbiente,
            "codigoModalidad"=>$modalidad,
            "codigoPuntoVenta"=>$codigoPuntoVenta,
            "codigoSistema"=>$codigoSistema,
            "codigoSucursal"=>$codigoSucursal,
            "cuis"=>$cuis,
            "nit"=>$nit,
        ]
    ];
    $result= $client->cuis($data);
    var_dump($result);
//    exit();
}


//https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionOperaciones
//<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:siat="https://siat.impuestos.gob.bo/">
//   <soapenv:Header/>
//   <soapenv:Body>
//      <siat:registroPuntoVenta>
//         <SolicitudRegistroPuntoVenta>
//            <codigoAmbiente>2</codigoAmbiente>
//            <codigoModalidad>1</codigoModalidad>
//            <codigoSistema>371F99692DA0DD4D41DE</codigoSistema>
//            <codigoSucursal>0</codigoSucursal>
//            <codigoTipoPuntoVenta>5</codigoTipoPuntoVenta>
//            <cuis>F498F4FF</cuis>
//            <descripcion>1</descripcion>
//            <nit>5062436018</nit>
//            <nombrePuntoVenta>1</nombrePuntoVenta>
//         </SolicitudRegistroPuntoVenta>
//      </siat:registroPuntoVenta>
//   </soapenv:Body>
//</soapenv:Envelope>