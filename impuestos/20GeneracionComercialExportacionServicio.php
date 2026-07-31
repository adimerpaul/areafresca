<?php
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
require 'vendor/autoload.php';

require 'CUF.php';



date_default_timezone_set('America/La_Paz');
$cuis1="1FC1D49F";
$codigo1="BQVVDLElsQUE=N0kQ0QUU3RDJDRkU=Qj40dDVIY0JZVUJMyMzNCRUE0MjEwQ";
$codigoControl1="56787D80D948E74"; //2023-03-01T16:56:05.359-04:00

$cuis0="7EE6BD16";
$codigo0="BQVVDLElsQUE=N0kQ0QUU3RDJDRkU=QnwzQUxJY0JZVUFMyMzNCRUE0MjEwQ";
$codigoControl0="1505B0E0D948E74"; //2023-03-01T16:55:06.383-04:00


$codigoPuntoVenta=1;
$codigoControl=$codigoControl1;
$cufd=$codigo1;
$cuis=$cuis1;


$token="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJsdWtvbWVrcCIsImNvZGlnb1Npc3RlbWEiOiI3QzIzM0JFQTQyMTBCRDRBRTdEMkNGRSIsIm5pdCI6Ikg0c0lBQUFBQUFBQUFETTJON0N3TURZd01nSUFKdGIwVEFrQUFBQT0iLCJpZCI6NzM5NjMyLCJleHAiOjE3MTQ0NzM0MjUsImlhdCI6MTcwNTUwMjE5NSwibml0RGVsZWdhZG8iOjM3MDg4MzAyMiwic3Vic2lzdGVtYSI6IlNGRSJ9.bFLqksfjpqdh_Mwf0Ehv6qgUe5xsWZ9ioi7DvnjuVn7lTbtzNuaAQz-529VV6Bsj0XQIjtkhFDAe2lZkMW65kw";
$codigoAmbiente="2";
$codigoDocumentoSector=20; //1 compra venta, 13 servicios basicos, 24 nota credito debito, 29 nota conciliacion
$codigoEmision=1;//1 online, 2 offline, 3 masiva
$codigoModalidad=1;

$cantidad=125;

$codigoSistema="7C233BEA4210BD4AE7D2CFE";
$codigoSucursal=0;

$nit="370883022";
$tipoFacturaDocumento=2;//1 con credito 2 sin credito 3 nota


//$temision=1; //1 online, 2 offline, 3 masiva
$cdf=2; // 1 con credito fiscal 2 sin credito fiscal 3 nota credito debito
$tds=20;//1 compra venta, 2 servicios basicos, 3 nota credito debito, 4 nota conciliacion
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

for ($i=1;$i<=$cantidad;$i++){
    $miliSegundo=str_pad($i, 3, '0', STR_PAD_LEFT);
    $fechaEnvio=date("Y-m-d\TH:i:s").".$miliSegundo";
    $cuf = new CUF();
    $cuf = $cuf->obtenerCUF($nit, date("YmdHis$miliSegundo"), $codigoSucursal, $codigoModalidad, $codigoEmision, $cdf, $tds, $nf, $codigoPuntoVenta);
    $cuf=$cuf.$codigoControl;
    $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?>
<facturaElectronicaComercialExportacionMinera xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:noNamespaceSchemaLocation='facturaElectronicaComercialExportacionMinera.xsd'>    <cabecera>
        <nitEmisor>$nit</nitEmisor>
        <razonSocialEmisor>Carlos Loza</razonSocialEmisor>
        <municipio>La Paz</municipio>
        <telefono>2846225</telefono>
        <numeroFactura>1</numeroFactura>
        <cuf>$cuf</cuf>
        <cufd>$cufd</cufd>
        <codigoSucursal>0</codigoSucursal>
        <direccion>AV. JORGE LOPEZ #123</direccion>
        <codigoPuntoVenta>$codigoPuntoVenta</codigoPuntoVenta>
        <fechaEmision>$fechaEnvio</fechaEmision>
        <nombreRazonSocial>Qhawaq Mining Andina S.R.L.</nombreRazonSocial>
        <direccionComprador>Av. Arce No 2299 Edif Multicentro Piso 4</direccionComprador>
        <codigoTipoDocumentoIdentidad>1</codigoTipoDocumentoIdentidad>
        <numeroDocumento>5115889</numeroDocumento>
        <complemento xsi:nil='true'/>
        <ruex>11858</ruex>
        <nim>02-0015-03</nim>
        <concentradoGranel>Zinc - Plata</concentradoGranel>
        <origen>Potosi - Bolivia</origen>
        <puertoTransito>Antofagasta</puertoTransito>
        <puertoDestino>Toyama Shinko</puertoDestino>
        <paisDestino>200</paisDestino>
        <incoterm>CIF</incoterm>
        <codigoCliente>51158891</codigoCliente>
        <montoTotalSujetoIva>0</montoTotalSujetoIva>
        <codigoMoneda>2</codigoMoneda>
        <tipoCambio>6.96</tipoCambio>
        <tipoCambioANB>6.96</tipoCambioANB>
        <numeroLote>18-ZN-QMA-009 FINAL</numeroLote>
        <kilosNetosHumedos>346.81</kilosNetosHumedos>
        <humedadPorcentaje>98</humedadPorcentaje>
        <humedadValor>346.81</humedadValor>
        <mermaPorcentaje>12</mermaPorcentaje>
        <mermaValor>3</mermaValor>
        <kilosNetosSecos>6.96</kilosNetosSecos>
        <codigoMetodoPago>1</codigoMetodoPago>
        <numeroTarjeta xsi:nil='true'/>
        <montoTotal>3094823.44</montoTotal>
        <montoTotalMoneda>444658.54</montoTotalMoneda>
        <gastosRealizacion>74030.63</gastosRealizacion>
        <otrosDatos>[{'gatosAdicionales':'100032'}]</otrosDatos>
        <descuentoAdicional>0</descuentoAdicional>
        <codigoExcepcion xsi:nil='true'/>
        <cafc xsi:nil='true'/>
        <leyenda>Ley N° 453: Los servicios deben suministrarse en condiciones de inocuidad, calidad y seguridad</leyenda>
        <usuario>vjcm</usuario>
        <codigoDocumentoSector>20</codigoDocumentoSector>
    </cabecera>
    <detalle>
        <actividadEconomica>466201</actividadEconomica>
        <codigoProductoSin>991009</codigoProductoSin>
        <codigoProducto>123456</codigoProducto>
        <codigoNandina>2608.00.00.00</codigoNandina>
        <descripcion>Zinc</descripcion>
        <descripcionLeyes>51.90</descripcionLeyes>
        <cantidadExtraccion>168010.68</cantidadExtraccion>
        <cantidad>311375.93</cantidad>
        <unidadMedidaExtraccion>1</unidadMedidaExtraccion>
        <unidadMedida>1</unidadMedida>
        <precioUnitario>1.46</precioUnitario>
        <montoDescuento>0</montoDescuento>
        <subTotal>454608.8578</subTotal>
    </detalle>
    <detalle>
        <actividadEconomica>466201</actividadEconomica>
        <codigoProductoSin>991009</codigoProductoSin>
        <codigoProducto>123456</codigoProducto>
        <codigoNandina>2616.10.00.00</codigoNandina>
        <descripcion>Plata</descripcion>
        <descripcionLeyes>descripcionLeyes</descripcionLeyes>
        <cantidadExtraccion>3800.73</cantidadExtraccion>
        <cantidad>3800.73</cantidad>
        <unidadMedidaExtraccion>1</unidadMedidaExtraccion>
        <unidadMedida>1</unidadMedida>
        <precioUnitario>16.86</precioUnitario>
        <montoDescuento>0</montoDescuento>
        <subTotal>64080.3078</subTotal>
    </detalle>
</facturaElectronicaComercialExportacionMinera>");
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    $nameFile=microtime();
    $dom->save("archivos/".$nameFile.'.xml');

    firmar("archivos/".$nameFile.'.xml');

    $xml = new DOMDocument();
    $xml->load("archivos/".$nameFile.'.xml');
    if (!$xml->schemaValidate('./facturaElectronicaComercialExportacionMinera.xsd')) {
        echo "invalid";
    }
    else {
//    echo "validated";
    }
    $file = "archivos/".$nameFile.'.xml';
    $gzfile = "archivos/".$nameFile.'.xml'.'.gz';
    $fp = gzopen ($gzfile, 'w9');
    gzwrite ($fp, file_get_contents($file));
    gzclose($fp);

    $archivo=getFileGzip("archivos/".$nameFile.'.xml'.'.gz');
    $hashArchivo=hash('sha256', $archivo);

    $client = new \SoapClient("https://pilotosiatservicios.impuestos.gob.bo/v2/ServicioFacturacionElectronica?WSDL",  [
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
//    sleep(1);
    $result= $client->anulacionFactura([
        "SolicitudServicioAnulacionFactura"=>[
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
            "codigoMotivo"=>"1",
            "cuf"=>$cuf,
        ]
    ]);
    var_dump($result);
    $result= $client->reversionAnulacionFactura([
        "SolicitudServicioReversionAnulacionFactura"=>[
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
            "cuf"=>$cuf,
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
    $objKey->loadKey('key/privateKey.pem', TRUE);

    $objDSig->sign($objKey);

    $objDSig->add509Cert(file_get_contents('key/publicKey.pem'));

    $objDSig->appendSignature($doc->documentElement);
    $doc->save($fileName);
}
?>
