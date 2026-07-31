<?php

namespace App\Http\Controllers;

use App\Mail\TestMail;
use App\Models\Cufd;
use App\Models\Cui;
use App\Models\EventoSignificativo;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Phar;
use PharData;
//use SoapClient;

class ImpuestoController extends Controller{
    function facturacionOperaciones(){
//        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:siat="https://siat.impuestos.gob.bo/">
//   <soapenv:Header/>
//   <soapenv:Body>
//      <siat:consultaEventoSignificativo>
//         <SolicitudConsultaEvento>
//            <codigoAmbiente>?</codigoAmbiente>
//            <!--Optional:-->
//            <codigoPuntoVenta>?</codigoPuntoVenta>
//            <codigoSistema>?</codigoSistema>
//            <codigoSucursal>?</codigoSucursal>
//            <cufd>?</cufd>
//            <cuis>?</cuis>
//            <fechaEvento>?</fechaEvento>
//            <nit>?</nit>
//         </SolicitudConsultaEvento>
//      </siat:consultaEventoSignificativo>
//   </soapenv:Body>
//</soapenv:Envelope>
        $client = new \SoapClient(env('URL_SIAT')."FacturacionOperaciones?WSDL",  [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "apikey: TokenApi ".env('TOKEN'),
                ]
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);
        $result= $client->consultaEventoSignificativo([
            "SolicitudConsultaEvento"=>[
                "codigoAmbiente"=>env('AMBIENTE'),
                "codigoPuntoVenta"=>0,
                "codigoSistema"=>env('CODIGO_SISTEMA'),
                "codigoSucursal"=>0,
                "cufd"=>Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "cuis"=>Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "fechaEvento"=>date('Y-m-d'),
                "nit"=>env('NIT'),
            ]
        ]);
        return response()->json($result, 200);
    }
    function eventoSignificativo(Request $request){
        $cui=Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cui->count()==0){
            return response()->json(['message' => 'El CUI no existe'], 400);
        }
        $cufd=Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cufd->count()==0){
            return response()->json(['message' => 'El CUFD no existe'], 400);
        }
        $codigoPuntoVenta =0;
        $codigoSucursal =0;

        $cui=Cui::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->first();
        $cufd=Cufd::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->first();
        $venta = Venta::find($request->venta_id);
        $fecha = $venta->fecha;
        $hora = $venta->hora;

//        verificar si existe en evento_significativo
        $eventoSignificativo = EventoSignificativo::where('codigoPuntoVenta', $codigoPuntoVenta)
            ->where('codigoSucursal', $codigoSucursal)
            ->where('fechaHoraInicioEvento', '<=', date('Y-m-d H:i:s', strtotime($fecha.' '.$hora)))
            ->where('fechaHoraFinEvento', '>=', date('Y-m-d H:i:s', strtotime($fecha.' '.$hora)))
//            ->where('cufdEvento', $venta->cuf)
            ->first();
        if (!$eventoSignificativo){
            $fechaInicio = date('Y-m-d\TH:i:s', strtotime($fecha.' '.$hora.' -1 second'));
            $fechaFin = date('Y-m-d\TH:i:s', strtotime($fecha.' '.$hora.' +1 second'));
            $fechaInicio = date('Y-m-d\TH:i:s.000', strtotime($fechaInicio));
            $fechaFin = date('Y-m-d\TH:i:s.000', strtotime($fechaFin));

            error_log("fechaInicio: ".$fechaInicio);
            error_log("fechaFin: ".$fechaFin);

            $client = new \SoapClient(env('URL_SIAT')."FacturacionOperaciones?WSDL",  [
                'stream_context' => stream_context_create([
                    'http' => [
                        'header' => "apikey: TokenApi ".env('TOKEN'),
                    ]
                ]),
                'cache_wsdl' => WSDL_CACHE_NONE,
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                'trace' => 1,
                'use' => SOAP_LITERAL,
                'style' => SOAP_DOCUMENT,
            ]);
            try {
                $result= $client->registroEventoSignificativo([
                    "SolicitudEventoSignificativo"=>[
                        "codigoAmbiente"=>env('AMBIENTE'),
                        "codigoMotivoEvento"=>$request->codigoMotivoEvento,
                        "codigoPuntoVenta"=>0,
                        "codigoSistema"=>env('CODIGO_SISTEMA'),
                        "codigoSucursal"=>0,
                        "cufd"=>$cufd->codigo,
                        "cufdEvento"=>$venta->cufd,
                        "cuis"=>$cui->codigo,
                        "descripcion"=>$request->descripcion,
                        "fechaHoraFinEvento"=>$fechaFin,
                        "fechaHoraInicioEvento"=>$fechaInicio,
                        "nit"=>env('NIT'),
                    ]
                ]);
                error_log("result: ".json_encode($result));

                if ($result->RespuestaListaEventos->transaccion){
                    $eventoSignificativo = new EventoSignificativo();
                    $eventoSignificativo->codigoPuntoVenta=$codigoPuntoVenta;
                    $eventoSignificativo->codigoSucursal=$codigoSucursal;
                    $eventoSignificativo->fechaHoraFinEvento=date('Y-m-d H:i:s', strtotime($fecha .' '.$hora.' +1 second'));
                    $eventoSignificativo->fechaHoraInicioEvento=date('Y-m-d H:i:s', strtotime($fecha .' '.$hora.' -1 second'));
                    $eventoSignificativo->codigoMotivoEvento=$request->codigoMotivoEvento;
                    $eventoSignificativo->descripcion=$request->descripcion;
                    $eventoSignificativo->cufd=$cufd->codigo;
                    $eventoSignificativo->cufdEvento=$venta->cuf;
                    $eventoSignificativo->cufd_id=$cufd->id;
                    $eventoSignificativo->codigoRecepcionEventoSignificativo=$result->RespuestaListaEventos->codigoRecepcionEventoSignificativo;
                    $eventoSignificativo->save();
                    return response()->json(['message' => 'Evento Significativo registrado correctamente!!'], 200);
                }else{
                    return response()->json(['message' => json_encode($result->RespuestaListaEventos->mensajesList) ], 500);
                }
            }catch (\Exception $e){
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }
        $a = new PharData('archivos/archive.tar');
        $ruta = "archivos/".$venta->id.".xml";
        $a->addFile($ruta);
        $a->compress(Phar::GZ);
        unlink('archivos/archive.tar');
        $firmar = new Firmar();
        $archivo=$firmar->getFileGzip("archivos/archive.tar.gz");
        $hashArchivo=hash('sha256', $archivo);
        unlink('archivos/archive.tar.gz');

        $client = new \SoapClient(env('URL_SIAT')."ServicioFacturacionCompraVenta?WSDL",  [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "apikey: TokenApi ".env('TOKEN'),
                ]
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);
        $codigoAmbiente=env('AMBIENTE');
        $codigoDocumentoSector=1; // 1 compraventa 2 alquiler 23 prevaloradas
        $codigoEmision=2; // 1 online 2 offline 3 masivo
        $codigoModalidad=env('MODALIDAD'); //1 electronica 2 computarizada
        $codigoPuntoVenta=0;//
        $codigoSistema=env('CODIGO_SISTEMA');
        $tipoFacturaDocumento=1; // 1 con credito fiscal 2 sin creditofical 3 nota debito credito
        $result= $client->recepcionPaqueteFactura([
            "SolicitudServicioRecepcionPaquete"=>[
                "codigoAmbiente"=>$codigoAmbiente,
                "codigoDocumentoSector"=>$codigoDocumentoSector,
                "codigoEmision"=>$codigoEmision,
                "codigoModalidad"=>$codigoModalidad,
                "codigoPuntoVenta"=>$codigoPuntoVenta,
                "codigoSistema"=>$codigoSistema,
                "codigoSucursal"=>$codigoSucursal,
                "cufd"=>$cufd->codigo,
                "cuis"=>$cui->codigo,
                "nit"=>env('NIT'),
                "tipoFacturaDocumento"=>$tipoFacturaDocumento,
                "archivo"=>$archivo,
                "fechaEnvio"=>date('Y-m-d\TH:i:s.000'),
                "hashArchivo"=>$hashArchivo,
                "cafc"=>"XXX",
                "cantidadFacturas"=>1,
                "codigoEvento"=>$eventoSignificativo->codigoRecepcionEventoSignificativo,
            ]
        ]);
        error_log("result: ".json_encode($result));
        $eventoSignificativo->codigoRecepcion=$result->RespuestaServicioFacturacion->codigoRecepcion;
        $eventoSignificativo->save();

    }
    /**
     * Flujo completo para factura fuera de línea, en un solo paso (como el proyecto ejemplo):
     * registrar evento significativo → empaquetar XML → recepcionPaqueteFactura →
     * polling validacionRecepcionPaqueteFactura → marcar venta online → enviar correo.
     */
    public function enviarPaquete(Request $request){
        set_time_limit(180);

        $venta = Venta::with('cliente')->findOrFail($request->venta_id);
        if ($venta->online) {
            return response()->json(['message' => 'La venta ya está registrada en línea en SIAT.'], 400);
        }
        if (empty($venta->cuf)) {
            return response()->json(['message' => 'La venta no tiene CUF (no fue emitida como factura).'], 400);
        }

        $xmlPath = public_path('archivos/' . $venta->id . '.xml');
        if (!file_exists($xmlPath)) {
            return response()->json(['message' => 'No se encontró el XML de la factura (archivos/' . $venta->id . '.xml).'], 400);
        }

        $codigoPuntoVenta = 0;
        $codigoSucursal   = 0;

        $cui = Cui::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia', '>=', now())->first();
        if (!$cui) {
            return response()->json(['message' => 'No existe CUI vigente!!'], 400);
        }
        $cufd = Cufd::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia', '>=', now())->first();
        if (!$cufd) {
            return response()->json(['message' => 'No existe CUFD vigente!!'], 400);
        }

        $soapOptions = [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "apikey: TokenApi " . env('TOKEN'),
                ]
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ];

        $tarPath = public_path('archivos/paquete_' . $venta->id . '.tar');
        $gzPath  = $tarPath . '.gz';

        try {
            // 1) Evento significativo (reutilizar si ya existe uno que cubra la fecha/hora de la venta)
            $fechaHoraVenta = date('Y-m-d H:i:s', strtotime($venta->fecha . ' ' . $venta->hora));
            $evento = EventoSignificativo::where('codigoPuntoVenta', $codigoPuntoVenta)
                ->where('codigoSucursal', $codigoSucursal)
                ->where('fechaHoraInicioEvento', '<=', $fechaHoraVenta)
                ->where('fechaHoraFinEvento', '>=', $fechaHoraVenta)
                ->first();

            if (!$evento) {
                $codigoMotivoEvento = $request->codigoMotivoEvento ?? 2;
                $descripcion = $request->descripcion ?? 'INACCESIBILIDAD AL SERVICIO WEB DE LA ADMINISTRACION TRIBUTARIA';

                $fechaInicio = date('Y-m-d\TH:i:s.000', strtotime($fechaHoraVenta . ' -1 second'));
                $fechaFin    = date('Y-m-d\TH:i:s.000', strtotime($fechaHoraVenta . ' +1 second'));

                $clientOperaciones = new \SoapClient(env('URL_SIAT') . "FacturacionOperaciones?WSDL", $soapOptions);
                $result = $clientOperaciones->registroEventoSignificativo([
                    "SolicitudEventoSignificativo" => [
                        "codigoAmbiente" => env('AMBIENTE'),
                        "codigoMotivoEvento" => $codigoMotivoEvento,
                        "codigoPuntoVenta" => $codigoPuntoVenta,
                        "codigoSistema" => env('CODIGO_SISTEMA'),
                        "codigoSucursal" => $codigoSucursal,
                        "cufd" => $cufd->codigo,
                        "cufdEvento" => $venta->cufd,
                        "cuis" => $cui->codigo,
                        "descripcion" => $descripcion,
                        "fechaHoraFinEvento" => $fechaFin,
                        "fechaHoraInicioEvento" => $fechaInicio,
                        "nit" => env('NIT'),
                    ]
                ]);
                error_log("enviarPaquete[{$venta->id}] evento: " . json_encode($result));

                if (empty($result->RespuestaListaEventos->transaccion)) {
                    return response()->json(['message' => 'SIAT rechazó el evento significativo: ' . json_encode($result->RespuestaListaEventos->mensajesList ?? $result)], 500);
                }

                $evento = new EventoSignificativo();
                $evento->codigoPuntoVenta = $codigoPuntoVenta;
                $evento->codigoSucursal = $codigoSucursal;
                $evento->fechaHoraInicioEvento = date('Y-m-d H:i:s', strtotime($fechaHoraVenta . ' -1 second'));
                $evento->fechaHoraFinEvento = date('Y-m-d H:i:s', strtotime($fechaHoraVenta . ' +1 second'));
                $evento->codigoMotivoEvento = $codigoMotivoEvento;
                $evento->descripcion = $descripcion;
                $evento->cufd = $cufd->codigo;
                $evento->cufdEvento = $venta->cufd;
                $evento->cufd_id = $cufd->id;
                $evento->codigoRecepcionEventoSignificativo = $result->RespuestaListaEventos->codigoRecepcionEventoSignificativo;
                $evento->save();
            }

            // 2) Empaquetar el XML en tar.gz
            @unlink($tarPath);
            @unlink($gzPath);
            $phar = new PharData($tarPath);
            $phar->addFile($xmlPath, $venta->id . '.xml');
            $phar->compress(Phar::GZ);
            $archivo = file_get_contents($gzPath);
            $hashArchivo = hash('sha256', $archivo);

            // 3) Enviar paquete
            $client = new \SoapClient(env('URL_SIAT') . "ServicioFacturacionCompraVenta?WSDL", $soapOptions);
            $result = $client->recepcionPaqueteFactura([
                "SolicitudServicioRecepcionPaquete" => [
                    "codigoAmbiente" => env('AMBIENTE'),
                    "codigoDocumentoSector" => 1,
                    "codigoEmision" => 2,
                    "codigoModalidad" => env('MODALIDAD'),
                    "codigoPuntoVenta" => $codigoPuntoVenta,
                    "codigoSistema" => env('CODIGO_SISTEMA'),
                    "codigoSucursal" => $codigoSucursal,
                    "cufd" => $cufd->codigo,
                    "cuis" => $cui->codigo,
                    "nit" => env('NIT'),
                    "tipoFacturaDocumento" => 1,
                    "archivo" => $archivo,
                    "fechaEnvio" => date('Y-m-d\TH:i:s.000'),
                    "hashArchivo" => $hashArchivo,
                    "cantidadFacturas" => 1,
                    "codigoEvento" => $evento->codigoRecepcionEventoSignificativo,
                ]
            ]);
            error_log("enviarPaquete[{$venta->id}] recepcion: " . json_encode($result));

            $codigoRecepcion = $result->RespuestaServicioFacturacion->codigoRecepcion ?? null;
            if (!$codigoRecepcion) {
                return response()->json(['message' => 'SIAT no devolvió código de recepción: ' . json_encode($result->RespuestaServicioFacturacion->mensajesList ?? $result)], 500);
            }
            $evento->codigoRecepcion = $codigoRecepcion;
            $evento->save();

            // 4) Polling de validación (máx. 10 intentos, 1 s de pausa)
            $validado = false;
            $ultimaRespuesta = null;
            for ($i = 0; $i < 10 && !$validado; $i++) {
                sleep(1);
                $val = $client->validacionRecepcionPaqueteFactura([
                    "SolicitudServicioValidacionRecepcionPaquete" => [
                        "codigoAmbiente" => env('AMBIENTE'),
                        "codigoDocumentoSector" => 1,
                        "codigoEmision" => 2,
                        "codigoModalidad" => env('MODALIDAD'),
                        "codigoPuntoVenta" => $codigoPuntoVenta,
                        "codigoSistema" => env('CODIGO_SISTEMA'),
                        "codigoSucursal" => $codigoSucursal,
                        "cufd" => $cufd->codigo,
                        "cuis" => $cui->codigo,
                        "nit" => env('NIT'),
                        "tipoFacturaDocumento" => 1,
                        "codigoRecepcion" => $codigoRecepcion,
                    ]
                ]);
                $ultimaRespuesta = $val;
                error_log("enviarPaquete[{$venta->id}] validacion intento {$i}: " . json_encode($val));
                if (($val->RespuestaServicioFacturacion->codigoDescripcion ?? '') === 'VALIDADA') {
                    $validado = true;
                }
            }

            if (!$validado) {
                return response()->json(['message' => 'SIAT no validó el paquete: ' . json_encode($ultimaRespuesta->RespuestaServicioFacturacion->mensajesList ?? $ultimaRespuesta)], 500);
            }

            // 5) Marcar la venta como en línea y enviar el correo al cliente
            $venta->online = true;
            $venta->save();

            $cliente = $venta->cliente;
            if ($cliente && $cliente->email && $cliente->email != '') {
                try {
                    Mail::to($cliente->email)->send(new TestMail([
                        "title" => "Factura",
                        "body" => "Gracias por su compra",
                        "online" => true,
                        "anulado" => false,
                        "cuf" => $venta->cuf,
                        "numeroFactura" => $venta->id,
                        "sale_id" => $venta->id,
                        "carpeta" => "archivos",
                        "total" => $venta->total,
                        "fecha" => $venta->fecha . ' ' . $venta->hora,
                    ]));
                } catch (\Exception $e) {
                    error_log('Error al enviar correo de factura: ' . $e->getMessage());
                }
            }

            return response()->json([
                'message' => 'Factura enviada y validada en SIAT correctamente.' . (($cliente && $cliente->email) ? ' Correo enviado al cliente.' : ''),
                'venta' => $venta,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        } finally {
            @unlink($tarPath);
            @unlink($gzPath);
        }
    }

    public function validarPaquete(Request $request){
        try {
            $codigoPuntoVenta=0;
            $codigoSucursal=0;
            if (Cui::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->count()==0){
                return response()->json(['message' => 'No existe CUI para la venta!!'], 400);
            }
            if (Cufd::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->count()==0){
                return response()->json(['message' => 'No exite CUFD para la venta!!'], 400);
            }
            $cui=Cui::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->first();
            $cufd=Cufd::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->first();

            $client = new \SoapClient(env('URL_SIAT')."ServicioFacturacionCompraVenta?WSDL",  [
                'stream_context' => stream_context_create([
                    'http' => [
                        'header' => "apikey: TokenApi ".env('TOKEN'),
                    ]
                ]),
                'cache_wsdl' => WSDL_CACHE_NONE,
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                'trace' => 1,
                'use' => SOAP_LITERAL,
                'style' => SOAP_DOCUMENT,
            ]);

            $venta = Venta::find($request->venta_id);

            $eventoSignificativo = EventoSignificativo::
                where('fechaHoraInicioEvento', '<=', date('Y-m-d H:i:s', strtotime($venta->fecha.' '.$venta->hora)))
                ->where('fechaHoraFinEvento', '>=', date('Y-m-d H:i:s', strtotime($venta->fecha.' '.$venta->hora)))
                ->first();


            $result= $client->validacionRecepcionPaqueteFactura([
                "SolicitudServicioValidacionRecepcionPaquete"=>[
                    "codigoAmbiente"=>env('AMBIENTE'),
                    "codigoDocumentoSector"=>"1",
                    "codigoEmision"=>2,
                    "codigoModalidad"=>1,
                    "codigoPuntoVenta"=>0,
                    "codigoSistema"=>env('CODIGO_SISTEMA'),
                    "codigoSucursal"=>0,
                    "cufd"=>$cufd->codigo,
                    "cuis"=>$cui->codigo,
                    "nit"=>env('NIT'),
                    "tipoFacturaDocumento"=>1,
//                    "codigoRecepcion"=>$request->codigoRecepcion,
                    "codigoRecepcion"=>$eventoSignificativo->codigoRecepcion
                ]
            ]);
//            "RespuestaServicioFacturacion": {
//                "codigoDescripcion": "VALIDADA",
            if ($result->RespuestaServicioFacturacion->codigoDescripcion=="VALIDADA"){
                $venta->online=true;
                $venta->save();
            }
            return  $result;
        }catch (\Exception $e) {
            return response()->json(["success"=>false,'message' => $e->getMessage()], 500);
        }
    }
    function anularImpuestos($cuf, $codigoMotivo = 1){
        $cui=Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cui->count()==0){
            return response()->json(['message' => 'El CUI no existe'], 400);
        }
        $cufd=Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cufd->count()==0){
            return response()->json(['message' => 'El CUFD no existe'], 400);
        }
        $client = new \SoapClient(env("URL_SIAT")."ServicioFacturacionCompraVenta?WSDL",  [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "apikey: TokenApi ".env('TOKEN'),
                ]
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);
        $result= $client->anulacionFactura([
            "SolicitudServicioAnulacionFactura"=>[
                "codigoAmbiente"=>env('AMBIENTE'),
                "codigoDocumentoSector"=>1,
                "codigoEmision"=>1,
                "codigoModalidad"=>env('MODALIDAD'),
                "codigoPuntoVenta"=>0,
                "codigoSistema"=>env('CODIGO_SISTEMA'),
                "codigoSucursal"=>0,
                "cufd"=>Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "cuis"=>Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "nit"=>env('NIT'),
                "tipoFacturaDocumento"=>1,
                "codigoMotivo"=>$codigoMotivo,
                "cuf"=>$cuf,
            ]
        ]);
        return response()->json($result, 200);
    }
    function verificarImpuestos($cuf){
        $cui=Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cui->count()==0){
            return response()->json(['message' => 'El CUI no existe'], 400);
        }
        $cufd=Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cufd->count()==0){
            return response()->json(['message' => 'El CUFD no existe'], 400);
        }
        $client = new \SoapClient(env("URL_SIAT")."ServicioFacturacionCompraVenta?WSDL",  [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "apikey: TokenApi ".env('TOKEN'),
                ]
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);
        $result= $client->verificacionEstadoFactura([
            "SolicitudServicioVerificacionEstadoFactura"=>[
                "codigoAmbiente"=>env('AMBIENTE'),
                "codigoDocumentoSector"=>1,
                "codigoEmision"=>1,
                "codigoModalidad"=>env('MODALIDAD'),
                "codigoPuntoVenta"=>0,
                "codigoSistema"=>env('CODIGO_SISTEMA'),
                "codigoSucursal"=>0,
                "cufd"=>Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "cuis"=>Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "nit"=>env('NIT'),
                "tipoFacturaDocumento"=>1,
                "cuf"=>$cuf,
            ]
        ]);
        return response()->json($result, 200);
    }
    public function generarCUI(){
        $codigoPuntoVenta = 0;
        $codigoSucursal = 0;
        $token=env('TOKEN');
        if (Cui::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->count()>=1){
            return response()->json(['message' => 'El CUI ya existe'], 400);
        }else{
            $client = new \SoapClient(env("URL_SIAT")."FacturacionCodigos?WSDL",  [
                'stream_context' => stream_context_create([
                    'http' => [
                        'header' => "apikey: TokenApi ".$token,
                    ]
                ]),
                'cache_wsdl' => WSDL_CACHE_NONE,
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                'trace' => 1,
                'use' => SOAP_LITERAL,
                'style' => SOAP_DOCUMENT,
            ]);
            error_log('Ambiente: '.env('AMBIENTE'));
            error_log('Modalidad: '.env('MODALIDAD'));
            error_log('Codigo Sistema: '.env('CODIGO_SISTEMA'));
            error_log('NIT: '.env('NIT'));
            $result= $client->cuis([
                "SolicitudCuis"=>[
                    "codigoAmbiente"=>env('AMBIENTE'),
                    "codigoModalidad"=>env('MODALIDAD'),
                    "codigoPuntoVenta"=>$codigoPuntoVenta,
                    "codigoSistema"=>env('CODIGO_SISTEMA'),
                    "codigoSucursal"=>$codigoSucursal,
                    "nit"=>env('NIT'),
                ]
            ]);
            error_log("result: ".json_encode($result));
            $cui = new Cui();
            $cui->codigo = $result->RespuestaCuis->codigo;
            $cui->fechaVigencia =  date('Y-m-d H:i:s', strtotime($result->RespuestaCuis->fechaVigencia));
            $cui->codigoPuntoVenta = $codigoPuntoVenta;
            $cui->codigoSucursal = $codigoSucursal;
            $cui->fechaCreacion= date('Y-m-d H:i:s');
            $cui->save();
            return response()->json(['success' => 'CUI creado correctamente'], 200);
        }
    }
    function listCUFD(){
        $cufd = Cufd::orderBy('id', 'desc')->get();
        return response()->json($cufd, 200);
    }
    function generarCUFD(){
        $codigoPuntoVenta = 0;
        $codigoSucursal = 0;
        if (Cufd::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now())->count()>=1){
            return response()->json(['message' => 'El CUFD ya existe'], 400);
        }else{
            $cui=Cui::where('codigoPuntoVenta', $codigoPuntoVenta)->where('codigoSucursal', $codigoSucursal)->where('fechaVigencia','>=', now());
            if ($cui->count()==0){
                return response()->json(['message' => 'El CUI no existe'], 400);
            }
            $client = new \SoapClient(env("URL_SIAT")."FacturacionCodigos?WSDL",  [
                'stream_context' => stream_context_create([
                    'http' => [
                        'header' => "apikey: TokenApi ".env('TOKEN'),
                    ]
                ]),
                'cache_wsdl' => WSDL_CACHE_NONE,
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                'trace' => 1,
                'use' => SOAP_LITERAL,
                'style' => SOAP_DOCUMENT,
            ]);
            $result= $client->cufd([
                "SolicitudCufd"=>[
                    "codigoAmbiente"=>env('AMBIENTE'),
                    "codigoModalidad"=>env('MODALIDAD'),
                    "codigoPuntoVenta"=>$codigoPuntoVenta,
                    "codigoSistema"=>env('CODIGO_SISTEMA'),
                    "codigoSucursal"=>$codigoSucursal,
                    "cuis"=> $cui->first()->codigo,
                    "nit"=>env('NIT'),
                ]
            ]);
            error_log("result: ".json_encode($result));

            $cufd = new Cufd();
            $cufd->codigo = $result->RespuestaCufd->codigo;
            $cufd->codigoControl = $result->RespuestaCufd->codigoControl;
//            $cufd->fechaVigencia =  date('Y-m-d H:i:s', strtotime($result->RespuestaCufd->fechaVigencia));
            $cufd->fechaVigencia =  date('Y-m-d H:i:s', strtotime (date('Y-m-d 23:59:59')));
            $cufd->fechaCreacion =  date('Y-m-d H:i:s');
            $cufd->codigoPuntoVenta = $codigoPuntoVenta;
            $cufd->codigoSucursal = $codigoSucursal;
            $cufd->save();
            return response()->json(['success' => 'CUFD creado correctamente'], 200);
        }
    }
    function revertirImpuestos($cuf){
        $cui=Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cui->count()==0){
            throw new \Exception('El CUI no existe');
        }
        $cufd=Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now());
        if ($cufd->count()==0){
            throw new \Exception('El CUFD no existe');
        }
        $client = new \SoapClient(env("URL_SIAT")."ServicioFacturacionCompraVenta?WSDL",  [
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => "apikey: TokenApi ".env('TOKEN'),
                ]
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'trace' => 1,
            'use' => SOAP_LITERAL,
            'style' => SOAP_DOCUMENT,
        ]);
        $result= $client->reversionAnulacionFactura([
            "SolicitudServicioReversionAnulacionFactura"=>[
                "codigoAmbiente"=>env('AMBIENTE'),
                "codigoDocumentoSector"=>1,
                "codigoEmision"=>1,
                "codigoModalidad"=>env('MODALIDAD'),
                "codigoPuntoVenta"=>0,
                "codigoSistema"=>env('CODIGO_SISTEMA'),
                "codigoSucursal"=>0,
                "cufd"=>Cufd::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "cuis"=>Cui::where('codigoPuntoVenta',0)->where('codigoSucursal',0)->where('fechaVigencia','>=', now())->orderBy('id','desc')->first()->codigo,
                "nit"=>env('NIT'),
                "tipoFacturaDocumento"=>1,
                "cuf"=>$cuf,
            ]
        ]);
        return $result;
    }
}
