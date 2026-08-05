<?php
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
require 'vendor/autoload.php';

require 'CUF.php';



date_default_timezone_set('America/La_Paz');
$cuis1="9A0F4C66";
$codigo1="BQTlDTkVCQkE=NzjdEODFGRDM1NkU=QlVyWE5KZUhXVUJIwREU2OTlBMjJBR";
$codigoControl1="0826769E55A6D74";

$cuis0="EB17D75E";
$codigo0="BQTlDTkVCQkE=NzjdEODFGRDM1NkU=Qm9SZkhMZUhXVUFIwREU2OTlBMjJBR";
$codigoControl0="66A99EBF55A6D74";


$codigoPuntoVenta=1;
$codigoControl=$codigoControl1;
$cufd=$codigo1;
$cuis=$cuis1;


$token="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJTRUxBX09SVTgzMSIsImNvZGlnb1Npc3RlbWEiOiI3MjBERTY5OUEyMkFGN0Q4MUZEMzU2RSIsIm5pdCI6Ikg0c0lBQUFBQUFBQUFETTBNRFF3TVRRMk1ESURBS2tRRzB3S0FBQUEiLCJpZCI6MjcyNjcsImV4cCI6MTY3MjQ0NDgwMCwiaWF0IjoxNjU1MTM1NzM2LCJuaXREZWxlZ2FkbyI6MTAxMDQxMzAyNiwic3Vic2lzdGVtYSI6IlNGRSJ9.dOFu7xY7ts0B6GVPKZ662HvdMBwElPfmxMs8hgrDpR9Y5aLsAkBvnvryfbp93D5XUq7ore0SQevztlxolFWxNA";
$codigoAmbiente="2";
$codigoDocumentoSector=13; //1 compra venta, 13 servicios basicos, 24 nota credito debito, 29 nota conciliacion
$codigoEmision=1;
$codigoModalidad=1;

$codigoSistema="720DE699A22AF7D81FD356E";
$codigoSucursal=0;

$nit="1010413026";
$tipoFacturaDocumento=1;


$temision=1;
$cdf=1;
$nf=1;
deleteFile();
//     * @param nit NIT emisor
//     * @param fh Fecha y Hora en formato yyyyMMddHHmmssSSS
//     * @param sucursal
//     * @param mod Modalidad
//     * @param temision Tipo de Emision
//     * @param cdf Codigo Documento Fiscal
//     * @param tds Tipo Documento Sector
//     * @param nf Numero de Factura
//     * @param pos Punto de Venta

for ($i=1;$i<=125;$i++){
    $miliSegundo=str_pad($i, 3, '0', STR_PAD_LEFT);
    $fechaEnvio=date("Y-m-d\TH:i:s").".$miliSegundo";
    $cuf = new CUF();
    $cuf = $cuf->obtenerCUF($nit, date("YmdHis$miliSegundo"), $codigoSucursal, $codigoModalidad, $temision, $cdf, $codigoDocumentoSector, $nf, $codigoPuntoVenta);
    $cuf=$cuf.$codigoControl;
    $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?>
<facturaElectronicaServicioBasico xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'
                                  xsi:noNamespaceSchemaLocation='facturaElectronicaServicioBasico.xsd'>
    <cabecera>
        <nitEmisor>$nit</nitEmisor>
        <razonSocialEmisor>Carlos Loza</razonSocialEmisor>
        <municipio>La Paz</municipio>
        <telefono>2846005</telefono>
        <numeroFactura>1</numeroFactura>
        <cuf>$cuf</cuf>
        <cufd>$cufd</cufd>
        <codigoSucursal>0</codigoSucursal>
        <direccion>AV. JORGE LOPEZ #123</direccion>
        <codigoPuntoVenta>$codigoPuntoVenta</codigoPuntoVenta>
        <mes>Marzo</mes>
        <gestion>2022</gestion>
        <ciudad>La Paz</ciudad>
        <zona>Alto Obrajes</zona>
        <numeroMedidor>3456</numeroMedidor>
        <fechaEmision>$fechaEnvio</fechaEmision>
        <nombreRazonSocial>Mi razon social</nombreRazonSocial>
        <domicilioCliente>Av. Tejada Sorzano 231</domicilioCliente>
        <codigoTipoDocumentoIdentidad>1</codigoTipoDocumentoIdentidad>
        <numeroDocumento>5115889</numeroDocumento>
        <complemento xsi:nil='true'/>
        <codigoCliente>51158891</codigoCliente>
        <codigoMetodoPago>1</codigoMetodoPago>
        <numeroTarjeta xsi:nil='true'/>
        <montoTotal>100.50</montoTotal>
        <montoTotalSujetoIva>86</montoTotalSujetoIva>
        <consumoPeriodo xsi:nil='true'/>
        <beneficiarioLey1886 xsi:nil='true'/>
        <montoDescuentoLey1886 xsi:nil='true'/>
        <montoDescuentoTarifaDignidad xsi:nil='true'/>
        <tasaAseo>5</tasaAseo>
        <tasaAlumbrado>2</tasaAlumbrado>
        <ajusteNoSujetoIva>5</ajusteNoSujetoIva>
        <detalleAjusteNoSujetoIva>{'Ajuste por Reclamo':5}</detalleAjusteNoSujetoIva>
        <ajusteSujetoIva>10</ajusteSujetoIva>
        <detalleAjusteSujetoIva>{'Cobropor Reconexión':10}</detalleAjusteSujetoIva>
        <otrosPagosNoSujetoIva>7</otrosPagosNoSujetoIva>
        <detalleOtrosPagosNoSujetoIva>{'Pago Cuota Cooperativa':7}</detalleOtrosPagosNoSujetoIva>
        <otrasTasas>0.50</otrasTasas>
        <codigoMoneda>1</codigoMoneda>
        <tipoCambio>1</tipoCambio>
        <montoTotalMoneda>100.50</montoTotalMoneda>
        <descuentoAdicional xsi:nil='true'/>
        <codigoExcepcion xsi:nil='true'/>
        <cafc xsi:nil='true'/>
        <leyenda>Una leyenda</leyenda>
        <usuario>vjcm</usuario>
        <codigoDocumentoSector>13</codigoDocumentoSector>
    </cabecera>
    <detalle>
        <actividadEconomica>841001</actividadEconomica>
        <codigoProductoSin>991009</codigoProductoSin>
        <codigoProducto>123456</codigoProducto>
        <descripcion>Servicio Mes Febrero</descripcion>
        <cantidad>1</cantidad>
        <unidadMedida>58</unidadMedida>
        <precioUnitario>76</precioUnitario>
        <montoDescuento xsi:nil='true'/>
        <subTotal>76</subTotal>
    </detalle>
</facturaElectronicaServicioBasico>
    ");
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    $nameFile=microtime();
    $dom->save("archivos/".$nameFile.'.xml');

    firmar("archivos/".$nameFile.'.xml');

    $xml = new DOMDocument();
    $xml->load("archivos/".$nameFile.'.xml');
    if (!$xml->schemaValidate('./facturaElectronicaServicioBasico.xsd')) {
        echo "invalid";
    }
    else {
//        echo "validated";
    }
//    exit;
    $file = "archivos/".$nameFile.'.xml';
    $gzfile = "archivos/".$nameFile.'.xml'.'.gz';
    $fp = gzopen ($gzfile, 'w9');
    gzwrite ($fp, file_get_contents($file));
    gzclose($fp);

    $archivo=getFileGzip("archivos/".$nameFile.'.xml'.'.gz');
    $hashArchivo=hash('sha256', $archivo);

    $client = new \SoapClient("https://pilotosiatservicios.impuestos.gob.bo/v2/ServicioFacturacionServicioBasico?WSDL",  [
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
    $result= $client->recepcionFactura([
        "SolicitudServicioRecepcionFactura"=>[
            "codigoAmbiente"=>$codigoAmbiente,
            "codigoDocumentoSector"=>$codigoDocumentoSector,
            "codigoEmision"=>$codigoEmision,
            "codigoModalidad"=>$codigoModalidad,
            "codigoPuntoVenta"=>$codigoPuntoVenta,
            "codigoSistema"=>$codigoSistema,
            "codigoSucursal"=>$codigoSucursal,
            "cufd"=>$cufd,
            "cuis"=>$cuis,
            "nit"=>$nit,
            "tipoFacturaDocumento"=>$tipoFacturaDocumento,
            "archivo"=>$archivo,
            "fechaEnvio"=>$fechaEnvio,
            "hashArchivo"=>$hashArchivo,
        ]
    ]);
    var_dump($result);
}



function getFileGzip($fileName)
{
    $fileName = $fileName;

    $handle = fopen($fileName, "rb");
    $contents = fread($handle, filesize($fileName));
    fclose($handle);

    return $contents;
}
function deleteFile()
{
    $files = glob('archivos/*'); //obtenemos todos los nombres de los ficheros
    foreach($files as $file){
        if(is_file($file))
            unlink($file); //elimino el fichero
    }
}

function firmar($fileName){
    $doc = new DOMDocument();
    $doc->load($fileName);

    $objDSig = new XMLSecurityDSig();
    $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
    $objDSig->addReference(
        $doc,
        XMLSecurityDSig::SHA256,
        array('http://www.w3.org/2000/09/xmldsig#enveloped-signature','http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments'),
        array('force_uri' => true)
    );
    $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type'=>'private'));
    /*
    If key has a passphrase, set it using
    $objKey->passphrase = '<passphrase>';
    */
    $objKey->loadKey('key/privatekey.pem', TRUE);

    $objDSig->sign($objKey);

    $objDSig->add509Cert(file_get_contents('key/selaimpuestos.pem'));

    $objDSig->appendSignature($doc->documentElement);
    $doc->save($fileName);
}
?>
