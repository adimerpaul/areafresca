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

$contador = 125;

for ($i=0; $i < $contador; $i++) {
    $client = new \SoapClient("https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionSincronizacion?WSDL",  [
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
        "SolicitudSincronizacion"=>[
            "codigoAmbiente"=>$codigoAmbiente,
            "codigoPuntoVenta"=>$codigoPuntoVenta,
            "codigoSistema"=>$codigoSistema,
            "codigoSucursal"=>$codigoSucursal,
            "cuis"=>$cuis,
            "nit"=>$nit,
        ]
    ];
    $result= $client->sincronizarActividades($data);
    var_dump($result);
//    exit();

    $result= $client->sincronizarFechaHora($data);
    var_dump($result);

    $result= $client->sincronizarListaActividadesDocumentoSector($data);
    var_dump($result);

    $result= $client->sincronizarListaLeyendasFactura($data);
    var_dump($result);

    $result= $client->sincronizarListaMensajesServicios($data);
    var_dump($result);

    $result= $client->sincronizarListaProductosServicios($data);
    var_dump($result);

    $result= $client->sincronizarParametricaEventosSignificativos($data);
    var_dump($result);

    $result= $client->sincronizarParametricaMotivoAnulacion($data);
    var_dump($result);

    $result= $client->sincronizarParametricaPaisOrigen($data);
    var_dump($result);

    $result= $client->sincronizarParametricaTipoDocumentoIdentidad($data);
    var_dump($result);
//    error_log(json_encode($result));

    $result= $client->sincronizarParametricaTipoDocumentoSector($data);
    var_dump($result);

    $result= $client->sincronizarParametricaTipoEmision($data);
    var_dump($result);

    $result= $client->sincronizarParametricaTipoHabitacion($data);
    var_dump($result);

    $result= $client->sincronizarParametricaTipoMetodoPago($data);
    var_dump($result);

    $result= $client->sincronizarParametricaTipoMoneda($data);
    var_dump($result);

    $result= $client->sincronizarParametricaTipoPuntoVenta($data);
    var_dump($result);

    $result= $client->sincronizarParametricaTiposFactura($data);
    var_dump($result);

    $result= $client->sincronizarParametricaUnidadMedida($data);
    var_dump($result);
//    error_log(json_encode($result));
}
