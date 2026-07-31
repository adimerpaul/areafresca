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
    $client = new \SoapClient("https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionCodigos?WSDL",  [
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
        "SolicitudCufd"=>[
            "codigoAmbiente"=>$codigoAmbiente,
            "codigoModalidad"=>$modalidad,
            "codigoPuntoVenta"=>$codigoPuntoVenta,
            "codigoSistema"=>$codigoSistema,
            "codigoSucursal"=>$codigoSucursal,
            "cuis"=>$cuis,
            "nit"=>$nit,
        ]
    ];
    $result= $client->cufd($data);
    var_dump($result);
//    exit();
}
