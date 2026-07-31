<?php
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
require 'vendor/autoload.php';

require 'CUF.php';



date_default_timezone_set('America/La_Paz');
$cuis1="B5BEEE8A";
$codigo1="CQUFlQ3deZ0FBNzDRCMTBDQkZBREU=QsKhV21nV1hJV1VIxRDQ0NDQwMEFFM";
$codigoControl1="2354BEAD0BB6D74";

$cuis0="5B5A5502";
$codigo0="BQWVDd15nQUE=NzDRCMTBDQkZBREU=QlU1dlRXWElXVUFIxRDQ0NDQwMEFFM";
$codigoControl0="5AA20B9D0BB6D74";


$codigoPuntoVenta=0;
$codigoControl=$codigoControl0;
$cufd=$codigo0;
$cuis=$cuis0;


$token="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJNVUxUSVNBTEFTIiwiY29kaWdvU2lzdGVtYSI6IjcyMUQ0NDQ0MDBBRTA0QjEwQ0JGQURFIiwibml0IjoiSDRzSUFBQUFBQUFBQURNMnNqUXhzVEF3TWdZQWZBZTRnd2tBQUFBPSIsImlkIjo1NzEwMTgsImV4cCI6MTY2NDQ5NjAwMCwiaWF0IjoxNjYwNzgxMzQyLCJuaXREZWxlZ2FkbyI6MzI5NDQ4MDIzLCJzdWJzaXN0ZW1hIjoiU0ZFIn0.IdIp7K0t4aod_CooxZ7MzhSFn6xgjiuhqKThKwcphqKSxeLcM9N84-CvFWeA5Q3pJSyxcoarWh7pk6pCajpVuA";
$codigoAmbiente="2";
$codigoDocumentoSector=2; //1 compra venta,// 2qlauilres, 13 servicios basicos, 24 nota credito debito, 29 nota conciliacion
$codigoEmision=1;
$codigoModalidad=1;

$codigoSistema="721D444400AE04B10CBFADE";
$codigoSucursal=0;

$nit="329448023";
$tipoFacturaDocumento=1;//1 con credito 2 sin credito 3 nota


$temision=1; //1 online, 2 offline, 3 masiva
//$cdf=3;
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
    $cuf = $cuf->obtenerCUF($nit, date("YmdHis$miliSegundo"), $codigoSucursal, $codigoModalidad, $temision, $tipoFacturaDocumento, $codigoDocumentoSector, $nf, $codigoPuntoVenta);
    $cuf=$cuf.$codigoControl;
    $xml = new SimpleXMLElement("
    <facturaElectronicaAlquilerBienInmueble xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:noNamespaceSchemaLocation='facturaElectronicaAlquilerBienInmueble.xsd'>
<cabecera>
<nitEmisor>$nit</nitEmisor>
<razonSocialEmisor>Carlos Loza</razonSocialEmisor>
<municipio>La Paz</municipio>
<telefono>2846005</telefono>
<numeroFactura>$nf</numeroFactura>
<cuf>$cuf</cuf>
<cufd>$cufd</cufd>
<codigoSucursal>0</codigoSucursal>
<direccion>AV. JORGE LOPEZ #123</direccion>
<codigoPuntoVenta>$codigoPuntoVenta</codigoPuntoVenta>
<fechaEmision>$fechaEnvio</fechaEmision>
<nombreRazonSocial>Control Tributario</nombreRazonSocial>
<codigoTipoDocumentoIdentidad>1</codigoTipoDocumentoIdentidad>
<numeroDocumento>5115889</numeroDocumento>
<complemento xsi:nil='true'/>
<codigoCliente>51158891</codigoCliente>
<periodoFacturado>MAYO 2019</periodoFacturado>
<codigoMetodoPago>1</codigoMetodoPago>
<numeroTarjeta xsi:nil='true'/>
<montoTotal>100</montoTotal>
<montoTotalSujetoIva>100</montoTotalSujetoIva>
<codigoMoneda>1</codigoMoneda>
<tipoCambio>1</tipoCambio>
<montoTotalMoneda>100</montoTotalMoneda>
<descuentoAdicional xsi:nil='true'/>
<codigoExcepcion>0</codigoExcepcion>
<cafc xsi:nil='true'/>
<leyenda>Una leyenda</leyenda>
<usuario>vjcm</usuario>
<codigoDocumentoSector>2</codigoDocumentoSector>
</cabecera>
<detalle>
<actividadEconomica>681011</actividadEconomica>
<codigoProductoSin>72111</codigoProductoSin>
<codigoProducto>123</codigoProducto>
<descripcion>Alquiler mes de Febrero</descripcion>
<cantidad>1</cantidad>
<unidadMedida>1</unidadMedida>
<precioUnitario>100</precioUnitario>
<montoDescuento>0</montoDescuento>
<subTotal>100</subTotal>
</detalle>
</facturaElectronicaAlquilerBienInmueble>
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
    if (!$xml->schemaValidate('./facturaElectronicaAlquilerBienInmueble.xsd')) {
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
