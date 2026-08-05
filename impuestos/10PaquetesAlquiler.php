<?php
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
require 'vendor/autoload.php';

require 'CUF.php';



date_default_timezone_set('America/La_Paz');
$cuis1="1D464E1F";
$codigo1="BQXlDcClvQUE=NzzlFN0MwMUIwRjY=QnloV1RWUURYVUJcwNDEwNkJCMEYwQ";
$codigoControl1="9F9B96EF4D5FD74"; //2023-03-01T16:56:05.359-04:00

$cuis0="10FE2652";
$codigo0="BQWVDd15nQUE=NzDRCMTBDQkZBREU=QlVKWUdKRElYVUFIxRDQ0NDQwMEFFM";
$codigoControl0="8C54022CAECFD74"; //2023-03-01T16:55:06.383-04:00

//3573986
$codigoPuntoVenta=0;
$codigoControl=$codigoControl0;
$cufd=$codigo0;
$cuis=$cuis0;

//$codigoEvento=3629231;

$codigoMotivoEvento=1;
$h="21";
$m="53";
$s="19";
//$s2=(int)$s+1;
$cantidad=500;

$token="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJNVUxUSVNBTEFTIiwiY29kaWdvU2lzdGVtYSI6IjcyMUQ0NDQ0MDBBRTA0QjEwQ0JGQURFIiwibml0IjoiSDRzSUFBQUFBQUFBQURNMnNqUXhzVEF3TWdZQWZBZTRnd2tBQUFBPSIsImlkIjo1NzEwMTgsImV4cCI6MTcwMDE3OTIwMCwiaWF0IjoxNjY4NzI0Nzg1LCJuaXREZWxlZ2FkbyI6MzI5NDQ4MDIzLCJzdWJzaXN0ZW1hIjoiU0ZFIn0.RwT_VeslNjLkKQuBoNBgXYaUl3Sq5gU5Co8yW7jxUEHp2JaX8olKZJKSl8C_bKIyILO13Uy8mK2LepVU7zwpNw";
$codigoAmbiente="1";
$codigoDocumentoSector=2; //1 compra venta, 13 servicios basicos, 24 nota credito debito, 29 nota conciliacion
$codigoEmision=2;//1 online, 2 offline, 3 masiva
$codigoModalidad=1;

$codigoSistema="721D444400AE04B10CBFADE";
$codigoSucursal=0;

$nit="329448023";
$tipoFacturaDocumento=1; //1 credito 2 sin creditos 3 nota credito debito


//$temision=1; //1 online, 2 offline, 3 masiva
$cdf=1; // 1 con credito fiscal 2 sin credito fiscal 3 nota credito debito
$nf=1;
//deleteFile();
//     * @param nit NIT emisor
//     * @param fh Fecha y Hora en formato yyyyMMddHHmmssSSS
//     * @param sucursal
//     * @param mod Modalidad
//     * @param temision Tipo de Emision
//     * @param cdf Codigo Documento Fiscal
//     * @param tds Tipo Documento Sector
//     * @param nf Numero de Factura
//     * @param pos Punto de Venta

$client = new \SoapClient("https://siatrest.impuestos.gob.bo/v2/FacturacionOperaciones?WSDL",  [
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
$result= $client->registroEventoSignificativo([
    "SolicitudEventoSignificativo"=>[
        "codigoAmbiente"=>$codigoAmbiente,
        "codigoMotivoEvento"=>$codigoMotivoEvento,
        "codigoPuntoVenta"=>$codigoPuntoVenta,
        "codigoSistema"=>$codigoSistema,
        "codigoSucursal"=>$codigoSucursal,
        "cufd"=>$cufd,
        "cufdEvento"=>"BQWVDd15nQUE=NzDRCMTBDQkZBREU=QkFIeUxMQklYVUFIxRDQ0NDQwMEFFM",
        "cuis"=>$cuis,
        "descripcion"=>$codigoMotivoEvento,
        "fechaHoraFinEvento"=>"2023-07-31T19:12:51.000",
        "fechaHoraInicioEvento"=>"2023-07-31T17:42:44.000",
        "nit"=>$nit
    ]
]);
var_dump($result);

//exit;
//exit;
$codigoEvento=$result->RespuestaListaEventos->codigoRecepcionEventoSignificativo;
//error_log("codigoMotivoEvento: ".json_encode($codigoMotivoEvento));
//exit;


//for ($i=1;$i<=$cantidad;$i++){
//    $miliSegundo=str_pad($i, 3, '0', STR_PAD_LEFT);
//    $fechaEnvio=date("Y-m-d\T$h:$m:$s").".000";
//    $cuf = new CUF();
//    $cuf = $cuf->obtenerCUF($nit, date("Ymd".$h.$m.$s."0000"), $codigoSucursal, $codigoModalidad, $codigoEmision, $cdf, $codigoDocumentoSector, $nf, $codigoPuntoVenta);
//    $cuf=$cuf.$codigoControl;schemaValidate
//    $dom = new DOMDocument('1.0');
//    $dom->preserveWhiteSpace = false;
//    $dom->formatOutput = true;
//    $dom->loadXML($xml->asXML());
//    $nameFile=str_replace(' ', '', microtime());
//    $dom->save("archivos/".$nameFile.'.xml');
//
////    firmar("archivos/".$nameFile.'.xml');
//
//    $xml = new DOMDocument();
//    $xml->load("archivos/".$nameFile.'.xml');
//    if (!$xml->schemaValidate('./facturaElectronicaAlquilerBienInmueble.xsd')) {
//        echo "invalid";
//    }
//    else {
//        echo " validated\n";
//    }
//    exit;

//    $file = "archivos/".$nameFile.'.xml';
//    $gzfile = "archivos/".$nameFile.'.xml'.'.gz';
//    $fp = gzopen ($gzfile, 'w9');
//    gzwrite ($fp, file_get_contents($file));
//    gzclose($fp);
//
//    $archivo=getFileGzip("archivos/".$nameFile.'.xml'.'.gz');
//    $hashArchivo=hash('sha256', $archivo);
//
//    $client = new \SoapClient("https://pilotosiatservicios.impuestos.gob.bo/v2/ServicioFacturacionCompraVenta?WSDL",  [
//        'stream_context' => stream_context_create([
//            'http' => [
//                'header' => "apikey: TokenApi " . $token,
//            ]
//        ]),
//        'cache_wsdl' => WSDL_CACHE_NONE,
//        'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
//        'trace' => 1,
//        'use' => SOAP_LITERAL,
//        'style' => SOAP_DOCUMENT,
//    ]);
//    $result= $client->recepcionFactura([
//        "SolicitudServicioRecepcionFactura"=>[
//            "codigoAmbiente"=>$codigoAmbiente,
//            "codigoDocumentoSector"=>$codigoDocumentoSector,
//            "codigoEmision"=>$codigoEmision,
//            "codigoModalidad"=>$codigoModalidad,
//            "codigoPuntoVenta"=>$codigoPuntoVenta,
//            "codigoSistema"=>$codigoSistema,
//            "codigoSucursal"=>$codigoSucursal,
//            "cufd"=>$cufd,
//            "cuis"=>$cuis,
//            "nit"=>$nit,
//            "tipoFacturaDocumento"=>$tipoFacturaDocumento,
//            "archivo"=>$archivo,
//            "fechaEnvio"=>$fechaEnvio,
//            "hashArchivo"=>$hashArchivo,
//        ]
//    ]);
//    var_dump($result);
//}
//exit;
//    exit;
createZip();
$archivo=getFileGzip("archivos/archive.tar.gz");
$hashArchivo=hash('sha256', $archivo);
$fechaEnvio=date('Y-m-d\TH:i:s.000');
unlink("archivos/archive.tar.gz");
$client = new \SoapClient("https://siatrest.impuestos.gob.bo/v2/ServicioFacturacionElectronica?WSDL",  [
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
$result= $client->recepcionPaqueteFactura([
    "SolicitudServicioRecepcionPaquete"=>[
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
        "cantidadFacturas"=>7,
        "codigoEvento"=>$codigoEvento,
//        "cafc"=>"101DB3D11742D",
    ]
]);
var_dump($result);

//exit;
//var_dump($result->RespuestaServicioFacturacion);
//echo $result->RespuestaServicioFacturacion->codigoRecepcion;
while (true){
    sleep(3);
    $result= $client->validacionRecepcionPaqueteFactura([
        "SolicitudServicioValidacionRecepcionPaquete"=>[
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
            "codigoRecepcion"=>$result->RespuestaServicioFacturacion->codigoRecepcion
        ]
    ]);
    var_dump($result);
}


function createZip(){
    try
    {
        $a = new PharData('archivos/archive.tar');

        // ADD FILES TO archive.tar FILE
        $files = glob('archivos/*'); //obtenemos todos los nombres de los ficheros
        $count = 0;
        foreach($files as $file){
            error_log($file);
            $a->addFile($file); //Agregamos el fichero
            $count++;
            echo $count."\n";
        }

        // COMPRESS archive.tar FILE. COMPRESSED FILE WILL BE archive.tar.gz
        $a->compress(Phar::GZ);

        // NOTE THAT BOTH FILES WILL EXISTS. SO IF YOU WANT YOU CAN UNLINK archive.tar
        unlink('archivos/archive.tar');
    }
    catch (Exception $e)
    {
        echo "Exception : " . $e;
    }
}
function getFileGzip($fileName)
{
    $file = $fileName;

    $handle = fopen($file, "rb");
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
    $objKey->loadKey('key/privateKeyPlaza.pem', TRUE);

    $objDSig->sign($objKey);

    $objDSig->add509Cert(file_get_contents('key/publicKeyPlaza.pem'));

    $objDSig->appendSignature($doc->documentElement);
    $doc->save($fileName);
}
?>
