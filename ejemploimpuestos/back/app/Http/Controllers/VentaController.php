<?php

namespace App\Http\Controllers;

use App\Mail\TestMail;
use App\Models\Cliente;
use App\Models\CompraDetalle;
use App\Models\Cufd;
use App\Models\Cui;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use SimpleXMLElement;
use DOMDocument;

class VentaController extends Controller{
    public function anular(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $venta = Venta::with('ventaDetalles')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($venta->estado === 'Anulada') {
                return response()->json(['message' => 'La venta ya ha sido anulada.'], 400);
            }

            foreach ($venta->ventaDetalles as $detalle) {
                $productoId    = (int) $detalle->producto_id;
                $porRestituir  = (float) $detalle->cantidad;
                $cdDetalleId   = $detalle->compra_detalle_id ?? null;

                // 1) Devolver al mismo lote si existe
                if (!empty($cdDetalleId)) {
                    $cd = CompraDetalle::where('id', $cdDetalleId)
                        ->lockForUpdate()
                        ->first();

                    if ($cd && (int)$cd->producto_id === $productoId && $cd->estado === 'Activo') {
                        $capacidad = max(0.0, (float)$cd->cantidad - (float)$cd->cantidad_venta);
                        $sumar     = min($porRestituir, $capacidad);

                        if ($sumar > 0) {
                            $cd->cantidad_venta = (float)$cd->cantidad_venta + $sumar;
                            $cd->save();
                            $porRestituir -= $sumar;
                        }
                    }
                }

                // 2) Si queda saldo por restituir -> FIFO
                if ($porRestituir > 0) {
                    $lotes = CompraDetalle::where('producto_id', $productoId)
                        ->where('estado', 'Activo')
                        ->whereNull('deleted_at')
                        ->orderByRaw("CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento ASC")
                        ->lockForUpdate()
                        ->get(['id','cantidad','cantidad_venta']);

                    foreach ($lotes as $l) {
                        if ($porRestituir <= 0) break;

                        $capacidad = (float)$l->cantidad - (float)$l->cantidad_venta;
                        if ($capacidad <= 0) continue;

                        $sumar = min($capacidad, $porRestituir);
                        $l->cantidad_venta = (float)$l->cantidad_venta + $sumar;
                        $l->save();

                        $porRestituir -= $sumar;
                    }

                    if ($porRestituir > 1e-9) {
                        abort(422, 'No fue posible restaurar completamente el stock por lotes.');
                    }
                }
            }

            #5 anular en impuesto (solo si fue facturada con CUF)
            $codigoMotivo = $request->input('codigoMotivo', 1);
            $siatWarning = null;
            if (!empty($venta->cuf)) {
                try {
                    $Impuestos = new ImpuestoController();
                    $resp = $Impuestos->anularImpuestos($venta->cuf, $codigoMotivo);
                    if (method_exists($resp, 'getStatusCode') && $resp->getStatusCode() !== 200) {
                        $siatWarning = 'No se pudo anular en impuestos (SIAT); la venta fue anulada localmente.';
                    }
                } catch (\Throwable $e) {
                    error_log('Sin respuesta de impuestos al anular: ' . $e->getMessage());
                    $siatWarning = 'Impuestos (SIAT) no respondió; la venta fue anulada localmente.';
                }
            }

            $venta->estado = 'Anulada';
            $venta->save();

            $client = Cliente::find($venta->cliente_id);
            if ($client && $client->email != '') {
                $motivos = [
                    1 => 'FACTURA MAL EMITIDA',
                    2 => 'DATOS DE EMISION INCORRECTOS',
                    3 => 'FACTURA O NOTA DEVUELTA'
                ];
                $motivoTexto = $motivos[$codigoMotivo] ?? 'FACTURA MAL EMITIDA';

                $details = [
                    "title" => "Factura",
                    "body" => "Factura anulada",
                    "online" => true,
                    "anulado" => true,
                    "cuf" => $venta->cuf,
                    "numeroFactura" => $venta->id,
                    "sale_id" => $venta->id,
                    "carpeta" => "archivos",
                    "total" => $venta->total,
                    "fecha" => $venta->fecha . ' ' . $venta->hora,
                    "motivo" => $motivoTexto,
                ];
                try {
                    Mail::to($client->email)->send(new TestMail($details));
                } catch (\Exception $e) {
                    error_log('Error al enviar correo de anulación: ' . $e->getMessage());
                }
            }

            return response()->json([
                'message' => 'Venta anulada y stock restituido correctamente.' . ($siatWarning ? ' ' . $siatWarning : ''),
                'venta'   => $venta,
            ]);
        });
    }

    public function revertir(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $venta = Venta::with('ventaDetalles')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($venta->estado !== 'Anulada') {
                return response()->json(['message' => 'La venta no está anulada, no se puede revertir.'], 400);
            }

            foreach ($venta->ventaDetalles as $detalle) {
                $productoId = (int) $detalle->producto_id;
                $porDescontar = (float) $detalle->cantidad;
                $cdDetalleId = $detalle->compra_detalle_id ?? null;

                // 1) Intentar descontar del mismo lote original
                if (!empty($cdDetalleId)) {
                    $cd = CompraDetalle::where('id', $cdDetalleId)
                        ->lockForUpdate()
                        ->first();

                    if ($cd && (int)$cd->producto_id === $productoId && $cd->estado === 'Activo') {
                        $disponible = (float)$cd->cantidad_venta;
                        $descontar = min($porDescontar, $disponible);

                        if ($descontar > 0) {
                            $cd->cantidad_venta = (float)$cd->cantidad_venta - $descontar;
                            $cd->save();
                            $porDescontar -= $descontar;
                        }
                    }
                }

                // 2) Si aún queda por descontar -> FIFO sobre otros lotes activos con stock
                if ($porDescontar > 0) {
                    $lotes = CompraDetalle::where('producto_id', $productoId)
                        ->where('estado', 'Activo')
                        ->whereNull('deleted_at')
                        ->where('cantidad_venta', '>', 0)
                        ->orderByRaw("CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento ASC")
                        ->lockForUpdate()
                        ->get(['id', 'cantidad_venta']);

                    foreach ($lotes as $l) {
                        if ($porDescontar <= 0) break;

                        $disponible = (float)$l->cantidad_venta;
                        if ($disponible <= 0) continue;

                        $take = min($disponible, $porDescontar);
                        $l->cantidad_venta = (float)$l->cantidad_venta - $take;
                        $l->save();

                        // Actualizar el lote en el detalle
                        $detalle->compra_detalle_id = $l->id;
                        $detalle->save();

                        $porDescontar -= $take;
                    }

                    if ($porDescontar > 1e-9) {
                        abort(422, 'No hay suficiente stock en los lotes activos para revertir la anulación de esta venta.');
                    }
                }
            }

            #5 revertir en impuesto (solo si fue facturada con CUF)
            $siatWarning = null;
            if (!empty($venta->cuf)) {
                try {
                    $Impuestos = new ImpuestoController();
                    $res = $Impuestos->revertirImpuestos($venta->cuf);

                    if (isset($res->RespuestaServicioFacturacion) && !$res->RespuestaServicioFacturacion->transaccion) {
                        $msg = $res->RespuestaServicioFacturacion->mensajesList->descripcion ?? 'SIAT rechazó la reversión de la anulación';
                        $siatWarning = 'Impuestos (SIAT): ' . $msg . '. La venta fue restaurada localmente.';
                    }
                } catch (\Throwable $e) {
                    error_log('Sin respuesta de impuestos al revertir: ' . $e->getMessage());
                    $siatWarning = 'Impuestos (SIAT) no respondió; la venta fue restaurada localmente.';
                }
            }

            $venta->estado = 'Activo';
            $venta->save();

            $client = Cliente::find($venta->cliente_id);
            if ($client && $client->email != '') {
                $details = [
                    "title" => "Factura",
                    "body" => "Factura revertida - su factura está activa nuevamente",
                    "online" => true,
                    "anulado" => false,
                    "revertido" => true,
                    "cuf" => $venta->cuf,
                    "numeroFactura" => $venta->id,
                    "sale_id" => $venta->id,
                    "carpeta" => "archivos",
                    "total" => $venta->total,
                    "fecha" => $venta->fecha . ' ' . $venta->hora,
                ];
                try {
                    Mail::to($client->email)->send(new TestMail($details));
                } catch (\Exception $e) {
                    error_log('Error al enviar correo de reversión: ' . $e->getMessage());
                }
            }

            return response()->json([
                'message' => 'Reversión completada y stock descontado correctamente.' . ($siatWarning ? ' ' . $siatWarning : ''),
                'venta'   => $venta,
            ]);
        });
    }

    public function store(Request $request)
    {
        set_time_limit(180); // 3 minutos

        $data = $request->validate([
            'ci'        => 'nullable|string',
            'nombre'    => 'nullable|string',
            'tipo_pago' => 'required|string|in:Efectivo,QR',
            'agencia'   => 'nullable|string',
            'productos' => 'required|array|min:1',

            'productos.*.producto_id'       => 'required|integer|exists:productos,id',
            'productos.*.cantidad'          => 'required|numeric|min:0.01',
            'productos.*.precio'            => 'required|numeric|min:0',
            'productos.*.compra_detalle_id' => 'nullable|integer|exists:compra_detalles,id',
        ]);

        $user    = $request->user();
        $cliente = $this->clienteUpdateOrCreate($request);

        return DB::transaction(function () use ($data, $user, $cliente) {
            error_log('Cliente: ' . json_encode($cliente));

            // Determinar tipo de comprobante
            $tipoComprobante = 'NOTA';
            $ciValida = true;

            // Verificar si es cliente "0" o vacío (NO enviar a impuestos)
            if (empty($cliente->ci) || $cliente->ci === '0' || $cliente->ci === 0 || $cliente->ci === '') {
                $tipoComprobante = 'NOTA';
                $ciValida = false;
            } else {
                $tipoComprobante = 'FACTURA';
                $ciValida = true;
            }

            // 1) Venta
            $venta = Venta::create([
                'user_id'          => $user?->id,
                'cliente_id'       => $cliente?->id,
                'ci'               => $data['ci'] ?? null,
                'nombre'           => $data['nombre'] ?? null,
                'fecha'            => now()->toDateString(),
                'hora'             => now()->format('H:i:s'),
                'estado'           => 'Activo',
                'tipo_comprobante' => $tipoComprobante,
                'tipo_pago'        => $data['tipo_pago'],
                'agencia'          => $data['agencia'] ?? ($user->agencia ?? null),
                'total'            => 0,
            ]);

            $total = 0.0;

            foreach ($data['productos'] as $item) {
                $productoId = (int) $item['producto_id'];
                $cantidad   = (float) $item['cantidad'];
                $precio     = (float) $item['precio'];

                // Snapshot de nombre del producto
                $producto = Producto::select('id','nombre')->findOrFail($productoId);
                $nombreProducto = $producto->nombre;

                // 2) Si viene lote seleccionado
                if (!empty($item['compra_detalle_id'])) {
                    $loteId = (int) $item['compra_detalle_id'];

                    $cd = CompraDetalle::where('id', $loteId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ((int)$cd->producto_id !== $productoId) {
                        abort(422, 'El lote seleccionado no corresponde al producto.');
                    }
                    if ($cd->estado !== 'Activo') {
                        abort(422, 'El lote seleccionado no está activo.');
                    }
                    if ((float)$cd->cantidad_venta < $cantidad) {
                        abort(422, 'Stock insuficiente en el lote seleccionado.');
                    }

                    // Crear detalle
                    VentaDetalle::create([
                        'venta_id'          => $venta->id,
                        'producto_id'       => $productoId,
                        'cantidad'          => $cantidad,
                        'precio'            => $precio,
                        'nombre'            => $nombreProducto,
                        'lote'              => $cd->lote,
                        'fecha_vencimiento' => $cd->fecha_vencimiento,
                        'compra_detalle_id' => $cd->id,
                    ]);

                    // Descontar del lote
                    $cd->cantidad_venta = (float)$cd->cantidad_venta - $cantidad;
                    $cd->save();

                    $total += $cantidad * $precio;
                    continue;
                }

                // 3) Si NO enviaron lote: FIFO por vencimiento
                $restante = $cantidad;

                $lotes = CompraDetalle::where('producto_id', $productoId)
                    ->where('estado', 'Activo')
                    ->whereNull('deleted_at')
                    ->where('cantidad_venta', '>', 0)
                    ->orderByRaw("CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento ASC")
                    ->lockForUpdate()
                    ->get(['id','cantidad_venta','lote','fecha_vencimiento']);

                foreach ($lotes as $l) {
                    if ($restante <= 0) break;

                    $take = min((float)$l->cantidad_venta, $restante);
                    if ($take <= 0) continue;

                    // Crear detalle
                    VentaDetalle::create([
                        'venta_id'          => $venta->id,
                        'producto_id'       => $productoId,
                        'cantidad'          => $take,
                        'precio'            => $precio,
                        'nombre'            => $nombreProducto,
                        'lote'              => $l->lote,
                        'fecha_vencimiento' => $l->fecha_vencimiento,
                        'compra_detalle_id' => $l->id,
                        'online'            => false,
                    ]);

                    // Descontar del lote
                    $l->cantidad_venta = (float)$l->cantidad_venta - $take;
                    $l->save();

                    $total    += $take * $precio;
                    $restante -= $take;
                }

                if ($restante > 1e-9) {
                    abort(422, 'Stock insuficiente por lotes.');
                }
            }

            // 4) Total final
            $venta->update(['total' => $total]);

            // 5) Si CI es "0" o vacío, solo guardar como NOTA (no enviar a impuestos)
            if (!$ciValida) {
                // Guardar como NOTA, no enviar a impuestos
                $venta->online = false;
                $venta->leyenda = 'Ley N° 453: Puedes acceder a la reclamación cuando tus derechos han sido vulnerados.';
                $venta->save();

                return $this->respuestaVenta($venta);
            }

            // 6) Si CI es válida, emitir FACTURA.
            //    Si impuestos (SIAT) falla, la factura igual se emite FUERA DE LÍNEA
            //    (online = false) para enviarse después con /enviarPaquete.
            $codigoPuntoVenta = 0;
            $codigoSucursal   = 0;

            // Sin CUI/CUFD vigente no se puede emitir ni enviar después: la venta no se registra
            $cui = Cui::where('codigoPuntoVenta', $codigoPuntoVenta)
                ->where('codigoSucursal', $codigoSucursal)
                ->where('fechaVigencia', '>=', now())
                ->first();

            if (!$cui) {
                abort(422, 'No existe CUI vigente. La venta no fue registrada.');
            }

            $cufd = Cufd::where('codigoPuntoVenta', $codigoPuntoVenta)
                ->where('codigoSucursal', $codigoSucursal)
                ->where('fechaVigencia', '>=', now())
                ->first();

            if (!$cufd) {
                abort(422, 'No existe CUFD vigente. La venta no fue registrada.');
            }

            // 7) Preparar datos para impuestos
            $leyendas = [
                "Ley N° 453: Puedes acceder a la reclamación cuando tus derechos han sido vulnerados.",
                "Ley N° 453: El proveedor debe brindar atención sin discriminación, con respeto, calidez y cordialidad a los usuarios y consumidores.",
                "Ley N° 453: Está prohibido importar, distribuir o comercializar productos expirados o prontos a expirar.",
                "Ley N° 453: Los alimentos declarados de primera necesidad deben ser suministrados de manera adecuada, oportuna, continua y a precio justo.",
                "Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los productos que consumes.",
                "Ley N° 453: Tienes derecho a un trato equitativo sin discriminación en la oferta de productos.",
                "Ley N° 453: Está prohibido importar, distribuir o comercializar productos prohibidos o retirados en el país de origen por atentar a la integridad física y a la salud.",
                "Ley N° 453: El proveedor deberá entregar el producto en las modalidades y términos ofertados o convenidos.",
                "Ley N° 453: En caso de incumplimiento a lo ofertado o convenido, el proveedor debe reparar o sustituir el producto..",
                "Ley N° 453: Los servicios deben suministrarse en condiciones de inocuidad, calidad y seguridad."
            ];
            $leyendaRandom = $leyendas[array_rand($leyendas)];

            // 8) Emitir la factura (CUF + XML) en modalidad EN LÍNEA (codigoEmision = 1).
            //    Sin XML no hay factura ni forma de reenviarla: la venta no se registra.
            try {
                $emision = $this->generarXmlFactura($venta, $cliente, $user, $cufd, 1, $leyendaRandom);
            } catch (\Throwable $e) {
                error_log('Error al generar el XML de la factura (venta ' . $venta->id . '): ' . $e->getMessage());
                abort(422, 'No se pudo generar la factura: ' . $e->getMessage() . ' La venta no fue registrada.');
            }

            $venta->cuf              = $emision['cuf'];
            $venta->cufd             = $cufd->codigo;
            $venta->tipo_comprobante = 'FACTURA';
            $venta->leyenda          = $leyendaRandom;
            $venta->online           = false; // sólo pasa a true si SIAT confirma
            $venta->save();

            // 9) Enviar a impuestos. Cualquier fallo deja la factura FUERA DE LÍNEA
            try {
                $url = env('URL_SIAT');
                $client = new \SoapClient("{$url}ServicioFacturacionCompraVenta?WSDL", [
                    'stream_context' => stream_context_create([
                        'http' => [
                            'header'  => "apikey: TokenApi " . env('TOKEN'),
                            'timeout' => 30,
                        ]
                    ]),
                    'cache_wsdl'   => WSDL_CACHE_NONE,
                    'compression'  => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                    'trace'        => 1,
                    'use'          => SOAP_LITERAL,
                    'style'        => SOAP_DOCUMENT,
                    'connection_timeout' => 30,
                ]);

                $result = $client->recepcionFactura([
                    "SolicitudServicioRecepcionFactura" => [
                        "codigoAmbiente" => env('AMBIENTE'),
                        "codigoDocumentoSector" => 1,
                        "codigoEmision" => 1,
                        "codigoModalidad" => env('MODALIDAD'),
                        "codigoPuntoVenta" => $codigoPuntoVenta,
                        "codigoSistema" => env('CODIGO_SISTEMA'),
                        "codigoSucursal" => $codigoSucursal,
                        "cufd" => $cufd->codigo,
                        "cuis" => $cui->codigo,
                        "nit" => env('NIT'),
                        "tipoFacturaDocumento" => 1,
                        "archivo" => $emision['archivo'],
                        "fechaEnvio" => $emision['fechaEnvio'],
                        "hashArchivo" => $emision['hashArchivo'],
                    ]
                ]);
                error_log('result: ' . json_encode($result));

                $transaccion = $result->RespuestaServicioFacturacion->transaccion ?? false;
                if (!$transaccion) {
                    throw new \RuntimeException(
                        $result->RespuestaServicioFacturacion->mensajesList->descripcion ?? 'Error desconocido'
                    );
                }

                $venta->online = true;
                $venta->save();

                // Enviar correo directo (sin cola, para no depender del worker)
                if ($cliente->email && $cliente->email != '') {
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
                            "total" => $emision['montoTotal'],
                            "fecha" => $venta->fecha . ' ' . $venta->hora,
                        ]));
                    } catch (\Throwable $eMail) {
                        error_log('Error al enviar correo de factura: ' . $eMail->getMessage());
                    }
                }

                return $this->respuestaVenta($venta);
            } catch (\Throwable $e) {
                error_log('Error al enviar a impuestos (venta ' . $venta->id . '): ' . $e->getMessage());

                // Regenerar CUF y XML como FUERA DE LÍNEA (codigoEmision = 2)
                // para poder reenviarla luego con /enviarPaquete
                try {
                    $offline = $this->generarXmlFactura($venta, $cliente, $user, $cufd, 2, $leyendaRandom);
                    $venta->cuf = $offline['cuf'];
                } catch (\Throwable $e2) {
                    error_log('Error al regenerar XML fuera de línea: ' . $e2->getMessage());
                }

                $venta->online = false;
                $venta->save();

                return $this->respuestaVenta(
                    $venta,
                    'Impuestos (SIAT) no confirmó la factura: ' . $e->getMessage() .
                    ' La factura se emitió FUERA DE LÍNEA y quedó pendiente de envío.'
                );
            }
        });
    }

    /**
     * Respuesta de venta con el mismo formato que espera el frontend,
     * agregando opcionalmente una advertencia de impuestos.
     */
    private function respuestaVenta(Venta $venta, ?string $siatWarning = null)
    {
        $payload = $venta->load('cliente', 'ventaDetalles.producto')->toArray();
        $payload['siat_warning'] = $siatWarning;

        return response()->json($payload);
    }

    /**
     * Construye el XML de la factura, lo guarda en public/archivos/{id}.xml y
     * devuelve el CUF junto al archivo comprimido y su hash listos para SIAT.
     *
     * @param int $codigoEmision 1 = en línea, 2 = fuera de línea
     */
    private function generarXmlFactura(Venta $venta, $cliente, $user, Cufd $cufd, int $codigoEmision, string $leyenda): array
    {
        $nit                   = env('NIT');
        $codigoSucursal        = 0;
        $codigoPuntoVenta      = 0;
        $codigoModalidad       = env('MODALIDAD');
        $tipoFacturaDocumento  = 1;
        $codigoDocumentoSector = 1;
        $numeroFactura         = $venta->id;
        $fechaEnvio            = date("Y-m-d\TH:i:s.000");

        $detalleFactura    = '';
        $montoTotalFactura = 0.0;
        foreach ($venta->ventaDetalles as $detalle) {
            $subTotalDetalle = round((float)$detalle->precio * (float)$detalle->cantidad, 2);
            $montoTotalFactura += $subTotalDetalle;
            $detalleFactura .= "<detalle>
                <actividadEconomica>4772100</actividadEconomica>
                <codigoProductoSin>1003655</codigoProductoSin>
                <codigoProducto>" . $detalle->producto_id . "</codigoProducto>
                <descripcion>" . $this->xmlSafe($detalle->nombre) . "</descripcion>
                <cantidad>" . $detalle->cantidad . "</cantidad>
                <unidadMedida>62</unidadMedida>
                <precioUnitario>" . $detalle->precio . "</precioUnitario>
                <montoDescuento>0</montoDescuento>
                <subTotal>" . number_format($subTotalDetalle, 2, '.', '') . "</subTotal>
                <numeroSerie xsi:nil='true'/>
                <numeroImei xsi:nil='true'/>
            </detalle>";
        }
        $montoTotalFactura = number_format(round($montoTotalFactura, 2), 2, '.', '');

        $cufGenerador = new CUF();
        $cuf = $cufGenerador->obtenerCUF(
            $nit,
            date("YmdHis000"),
            $codigoSucursal,
            $codigoModalidad,
            $codigoEmision,
            $tipoFacturaDocumento,
            $codigoDocumentoSector,
            $numeroFactura,
            $codigoPuntoVenta
        );
        $cuf = $cuf . $cufd->codigoControl;

        $text = "<?xml version='1.0' encoding='UTF-8' standalone='yes'?>
        <facturaComputarizadaCompraVenta xsi:noNamespaceSchemaLocation='facturaComputarizadaCompraVenta.xsd' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'>
        <cabecera>
        <nitEmisor>" . env('NIT') . "</nitEmisor>
        <razonSocialEmisor>" . env('RAZON') . "</razonSocialEmisor>
        <municipio>Oruro</municipio>
        <telefono>" . env('TELEFONO') . "</telefono>
        <numeroFactura>$numeroFactura</numeroFactura>
        <cuf>$cuf</cuf>
        <cufd>" . $cufd->codigo . "</cufd>
        <codigoSucursal>$codigoSucursal</codigoSucursal>
        <direccion>" . env('DIRECCION') . "</direccion>
        <codigoPuntoVenta>$codigoPuntoVenta</codigoPuntoVenta>
        <fechaEmision>$fechaEnvio</fechaEmision>
        <nombreRazonSocial>" . $this->xmlSafe($cliente->nombre) . "</nombreRazonSocial>
        <codigoTipoDocumentoIdentidad>" . $cliente->codigoTipoDocumentoIdentidad . "</codigoTipoDocumentoIdentidad>
        <numeroDocumento>" . $cliente->ci . "</numeroDocumento>
        <complemento>" . $cliente->complemento . "</complemento>
        <codigoCliente>" . $cliente->id . "</codigoCliente>
        <codigoMetodoPago>1</codigoMetodoPago>
        <numeroTarjeta xsi:nil='true'/>
        <montoTotal>" . $montoTotalFactura . "</montoTotal>
        <montoTotalSujetoIva>" . $montoTotalFactura . "</montoTotalSujetoIva>
        <codigoMoneda>1</codigoMoneda>
        <tipoCambio>1</tipoCambio>
        <montoTotalMoneda>" . $montoTotalFactura . "</montoTotalMoneda>
        <montoGiftCard xsi:nil='true'/>
        <descuentoAdicional>0</descuentoAdicional>
        <codigoExcepcion>" . ($cliente->codigoTipoDocumentoIdentidad == 5 ? 1 : 0) . "</codigoExcepcion>
        <cafc xsi:nil='true'/>
        <leyenda>" . $this->xmlSafe($leyenda) . "</leyenda>
        <usuario>" . $this->xmlSafe(explode(" ", $user->name)[0]) . "</usuario>
        <codigoDocumentoSector>" . $codigoDocumentoSector . "</codigoDocumentoSector>
        </cabecera>";
        $text .= $detalleFactura;
        $text .= "</facturaComputarizadaCompraVenta>";

        $xml = new SimpleXMLElement($text);
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        if (!is_dir(public_path('archivos'))) {
            mkdir(public_path('archivos'), 0777, true);
        }
        $file   = public_path("archivos/" . $venta->id . '.xml');
        $gzfile = $file . '.gz';
        $dom->save($file);

        $fp = gzopen($gzfile, 'w9');
        gzwrite($fp, file_get_contents($file));
        gzclose($fp);

        $archivo = $this->getFileGzip($gzfile);

        return [
            'cuf'         => $cuf,
            'archivo'     => $archivo,
            'hashArchivo' => hash('sha256', $archivo),
            'fechaEnvio'  => $fechaEnvio,
            'montoTotal'  => $montoTotalFactura,
        ];
    }

    function getFileGzip($fileName)
    {
        $handle = fopen($fileName, "rb");
        $contents = fread($handle, filesize($fileName));
        fclose($handle);
        return $contents;
    }

    function clienteUpdateOrCreate($request){
        $ci = $request->ci;
        $findCliente = Cliente::where('ci', $ci)->first();
        if ($findCliente) {
            $findCliente->update($request->all());
            return $findCliente;
        } else {
            return Cliente::create($request->all());
        }
    }

    private function xmlSafe(?string $s): string
    {
        $s = $s ?? '';
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
        return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    function index(Request $request){
        $fechaInicio = $request->fechaInicio;
        $fechaFin = $request->fechaFin;
        $user_id = $request->user;
        $producto_id = $request->producto_id;
        $user = $request->user();

        if ($user->role == 'Admin') {
            $ventas = Venta::with('user', 'cliente')
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->when($producto_id, function ($q) use ($producto_id) {
                    $q->whereHas('ventaDetalles', function ($qq) use ($producto_id) {
                        $qq->where('producto_id', $producto_id);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();
        }else{
            $ventas = Venta::with('user', 'cliente')
                ->where('user_id', $user->id)
                ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->where('agencia', $user->agencia)
                ->when($producto_id, function ($q) use ($producto_id) {
                    $q->whereHas('ventaDetalles', function ($qq) use ($producto_id) {
                        $qq->where('producto_id', $producto_id);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();
        }
        if ($user_id != '') {
            $ventas = $ventas->where('user_id', $user_id);
        }
        return $ventas;
    }

    /**
     * Facturas activas que aún no fueron aceptadas por SIAT, sin importar la fecha.
     */
    function pendientes(Request $request){
        $user = $request->user();

        $ventas = Venta::with('user', 'cliente')
            ->where('estado', 'Activo')
            ->where('tipo_comprobante', 'FACTURA')
            ->where(function ($q) {
                $q->where('online', false)->orWhereNull('online');
            })
            ->orderBy('created_at', 'desc');

        if ($user->role != 'Admin') {
            $ventas->where('user_id', $user->id)->where('agencia', $user->agencia);
        }

        return $ventas->get();
    }

    function show($id){
        return Venta::with('user', 'cliente')->findOrFail($id);
    }

    function update(Request $request, $id){
        $venta = Venta::findOrFail($id);
        $venta->update($request->all());
        return $venta;
    }

    function destroy($id){
        $venta = Venta::findOrFail($id);
        $venta->delete();
        return $venta;
    }
}
