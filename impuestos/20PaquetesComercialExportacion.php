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

//3573986
$codigoPuntoVenta=0;
$codigoControl=$codigoControl0;
$cufd=$codigo0;
$cuis=$cuis0;

//$codigoEvento=3629231;

$codigoMotivoEvento=4;
$h="08";
$m="13";
$s="00";
//$s2=(int)$s+1;
$cantidad=1;

$token="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJsdWtvbWVrcCIsImNvZGlnb1Npc3RlbWEiOiI3QzIzM0JFQTQyMTBCRDRBRTdEMkNGRSIsIm5pdCI6Ikg0c0lBQUFBQUFBQUFETTJON0N3TURZd01nSUFKdGIwVEFrQUFBQT0iLCJpZCI6NzM5NjMyLCJleHAiOjE3MTQ0NzM0MjUsImlhdCI6MTcwNTUwMjE5NSwibml0RGVsZWdhZG8iOjM3MDg4MzAyMiwic3Vic2lzdGVtYSI6IlNGRSJ9.bFLqksfjpqdh_Mwf0Ehv6qgUe5xsWZ9ioi7DvnjuVn7lTbtzNuaAQz-529VV6Bsj0XQIjtkhFDAe2lZkMW65kw";
$codigoAmbiente="2";
$codigoDocumentoSector=20; //1 compra venta, 13 servicios basicos, 24 nota credito debito, 29 nota conciliacion
$codigoEmision=2;//1 online, 2 offline, 3 masiva
$codigoModalidad=1;

$codigoSistema="7C233BEA4210BD4AE7D2CFE";
$codigoSucursal=0;

$nit="370883022";
$tipoFacturaDocumento=2;//1 con credito 2 sin credito 3 nota



//$temision=1; //1 online, 2 offline, 3 masiva
$cdf=2; // 1 con credito fiscal 2 sin credito fiscal 3 nota credito debito
$nf=1;

for ($y=1;$y<=30;$y++){
    deleteFile();
    $client = new \SoapClient("https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionOperaciones?WSDL",  [
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
            "cufdEvento"=>$cufd,
            "cuis"=>$cuis,
            "descripcion"=>$codigoMotivoEvento,
            "fechaHoraFinEvento"=>date("Y-m-d\T$h:$m:$s").".600",
            "fechaHoraInicioEvento"=>date("Y-m-d\T$h:$m:$s").".000",
            "nit"=>$nit
        ]
    ]);
    var_dump($result);
    $codigoEvento=$result->RespuestaListaEventos->codigoRecepcionEventoSignificativo;
//error_log("codigoMotivoEvento: ".json_encode($codigoMotivoEvento));
//exit;


    for ($i=1;$i<=$cantidad;$i++){
        $miliSegundo=str_pad($i, 3, '0', STR_PAD_LEFT);
        $fechaEnvio=date("Y-m-d\T$h:$m:$s").".$miliSegundo";
        $cuf = new CUF();
        $cuf = $cuf->obtenerCUF($nit, date("Ymd".$h.$m.$s."$miliSegundo"), $codigoSucursal, $codigoModalidad, $codigoEmision, $cdf, $codigoDocumentoSector, $nf, $codigoPuntoVenta);
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
        $nameFile=str_replace(' ', '', microtime());
        $dom->save("archivos/".$nameFile.'.xml');

        firmar("archivos/".$nameFile.'.xml');

        $xml = new DOMDocument();
        $xml->load("archivos/".$nameFile.'.xml');
        if (!$xml->schemaValidate('./facturaElectronicaComercialExportacionMinera.xsd')) {
            echo "invalid";
        }
        else {
            echo "$i validated\n";
        }
    }

    $archiveName="archivos/archive".$y.".tar";
    createZip($archiveName);
    $archivo=getFileGzip($archiveName.".gz");
    $hashArchivo=hash('sha256', $archivo);
    $fechaEnvio=date('Y-m-d\TH:i:s.000');
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
            "cantidadFacturas"=>$cantidad,
            "codigoEvento"=>$codigoEvento,
//        "cafc"=>"101DB3D11742D",
        ]
    ]);
    var_dump($result);
//var_dump($result->RespuestaServicioFacturacion);
//echo $result->RespuestaServicioFacturacion->codigoRecepcion;
    $sw=true;
    while ($sw){
        sleep(1);
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
        if ($result->RespuestaServicioFacturacion->codigoDescripcion=="VALIDADA"){
            $sw=false;
        }
    }
    $svalInt = (int)$s+1;
    $s=str_pad($svalInt, 2, '0', STR_PAD_LEFT);
    error_log("s: ".$s);
//    exit();
}
exit();


function createZip($archiveName){
    try
    {
        $a = new PharData($archiveName);

        // ADD FILES TO archive.tar FILE
        $files = glob('archivos/*'); //obtenemos todos los nombres de los ficheros
        $count = 0;
        foreach($files as $file){
            error_log('creando zip: '.$file);
            $a->addFile($file); //Agregamos el fichero
            $count++;
            echo $count."\n";
        }

        // COMPRESS archive.tar FILE. COMPRESSED FILE WILL BE archive.tar.gz
        $a->compress(Phar::GZ);

        // NOTE THAT BOTH FILES WILL EXISTS. SO IF YOU WANT YOU CAN UNLINK archive.tar
//        unlink('archivos/archive.tar');
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
        if(is_file($file)){
            unlink($file); //elimino el fichero
            error_log("borrando".$file);
        }
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

    $objDSig->add509Cert(file_get_contents('key/publicKey.pem'));

    $objDSig->appendSignature($doc->documentElement);
    $doc->save($fileName);
}
?>
