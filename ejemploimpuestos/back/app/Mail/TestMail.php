<?php

namespace App\Mail;

//use App\Models\Medida;
use Dompdf\Dompdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Luecano\NumeroALetras\NumeroALetras;
use SimpleSoftwareIO\QrCode\Facades\QrCode;


class TestMail extends Mailable
{
    use Queueable, SerializesModels;
    public $details;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($details)
    {
        $this->details=$details;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if ($this->details['anulado']){
            $datos['cuf']=$this->details['cuf'];
            $datos['numeroFactura']=$this->details['numeroFactura'];
            $datos['motivo']=$this->details['motivo'] ?? null;
            return $this->from(env('MAIL_FROM_ADDRESS'),env('MAIL_FROM_NAME'))
                ->view('anulado',$datos)
                ->subject(env('RAZON', 'Farmacia Eliseo') . ' - Factura N° ' . $this->details['numeroFactura'] . ' Anulada')
                ->with($this->details);
        }
        if (isset($this->details['revertido']) && $this->details['revertido']){
            $datos['cuf']=$this->details['cuf'];
            $datos['numeroFactura']=$this->details['numeroFactura'];
            $datos['total']=$this->details['total'] ?? null;
            $datos['fecha']=$this->details['fecha'] ?? null;
            return $this->from(env('MAIL_FROM_ADDRESS'),env('MAIL_FROM_NAME'))
                ->view('revertido',$datos)
                ->subject(env('RAZON', 'Farmacia Eliseo') . ' - Factura N° ' . $this->details['numeroFactura'] . ' Reactivada')
                ->with($this->details);
        }
        $pathXmlFile=public_path($this->details['carpeta'].'/'.$this->details['sale_id'].'.xml');
        if (!file_exists($pathXmlFile)) return false;
        $nameFile = substr($pathXmlFile, 0, strlen($pathXmlFile) - 4);
        $content = file_get_contents($pathXmlFile);
        $xml = simplexml_load_string($content);
        $cuf = '';
        for ($i = 0; $i < strlen($xml->cabecera->cuf); $i++) {
            if (($i + 1) % 20 == 0) {
                $cuf .= substr($xml->cabecera->cuf, $i, 1) . "<br>";
            } else {
                $cuf .= substr($xml->cabecera->cuf, $i, 1);
            }
        }
        $html = self::generateHTML($xml,$cuf,$this->details['online']);
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter');
        $dompdf->render();
        file_put_contents($nameFile.'.pdf', $dompdf->output());

        return $this->from(env('MAIL_FROM_ADDRESS'),env('MAIL_FROM_NAME'))
            ->view('vista')
            ->attach(public_path($this->details['carpeta'].'/'.$this->details['sale_id'].'.pdf'), [
                'as'   => 'Factura_'.$this->details['sale_id'].'.pdf',
                'mime' => 'application/pdf',
            ])
            ->attach(public_path($this->details['carpeta'].'/'.$this->details['sale_id'].'.xml'), [
                'as'   => 'Factura_'.$this->details['sale_id'].'.xml',
                'mime' => 'application/xml',
            ])
            ->subject(env('RAZON', 'Farmacia Eliseo').' - Factura N° '.$xml->cabecera->numeroFactura)
            ->with($this->details);
    }

    public function generateHTML($xml,$cuf,$online): string
    {
        if ($this->details['carpeta']=='rentals'){
            $rental='<tr>
                    <td class="right">
                        <div class="bold">Periodo Facturado:</div>
                    </td>
                    <td>
                        <div>' . $xml->cabecera->periodoFacturado . '</div>
                    </td>
                </tr>';
        }else{
            $rental='';
        }

        $formatter = new NumeroALetras();
        $literal = $formatter->toInvoice((float)$xml->cabecera->montoTotal, 2, 'Bolivianos');
        $detalles = "";
        $hora = substr($xml->cabecera->fechaEmision, 11, 8);
        $fecha = substr($xml->cabecera->fechaEmision, 0, 10);
        foreach ($xml->detalle as $d) {
//            if ($d->unidadMedida == Env::unidadMedida) {
//            } else {
            $medidas = [
                ['codigoClasificador' => 38,  'descripcion' => 'MILLARES'],
                ['codigoClasificador' => 39,  'descripcion' => 'MILLON DE UNIDADES'],
                ['codigoClasificador' => 40,  'descripcion' => 'ONZAS'],
                ['codigoClasificador' => 41,  'descripcion' => 'PALETAS'],
                ['codigoClasificador' => 42,  'descripcion' => 'PAQUETE'],
                ['codigoClasificador' => 43,  'descripcion' => 'PAR'],
                ['codigoClasificador' => 44,  'descripcion' => 'PIES'],
                ['codigoClasificador' => 45,  'descripcion' => 'PIES CUADRADOS'],
                ['codigoClasificador' => 108, 'descripcion' => 'AMORTIZACION'],
                ['codigoClasificador' => 46,  'descripcion' => 'PIES CUBICOS'],
                ['codigoClasificador' => 47,  'descripcion' => 'PIEZAS'],
                ['codigoClasificador' => 48,  'descripcion' => 'PLACAS'],
                ['codigoClasificador' => 49,  'descripcion' => 'PLIEGO'],
                ['codigoClasificador' => 70,  'descripcion' => 'ROLLO'],
                ['codigoClasificador' => 50,  'descripcion' => 'PULGADAS'],
                ['codigoClasificador' => 71,  'descripcion' => 'HORAS'],
                ['codigoClasificador' => 51,  'descripcion' => 'RESMA'],
                ['codigoClasificador' => 52,  'descripcion' => 'TAMBOR'],
                ['codigoClasificador' => 53,  'descripcion' => 'TONELADA CORTA'],
                ['codigoClasificador' => 54,  'descripcion' => 'TONELADA LARGA'],
                ['codigoClasificador' => 63,  'descripcion' => 'ONZA TROY'],
                ['codigoClasificador' => 55,  'descripcion' => 'TONELADAS'],
                ['codigoClasificador' => 64,  'descripcion' => 'LIBRA FINA'],
                ['codigoClasificador' => 56,  'descripcion' => 'TUBOS'],
                ['codigoClasificador' => 72,  'descripcion' => 'AGUJA'],
                ['codigoClasificador' => 57,  'descripcion' => 'UNIDAD (BIENES)'],
                ['codigoClasificador' => 73,  'descripcion' => 'AMPOLLA'],
                ['codigoClasificador' => 58,  'descripcion' => 'UNIDAD (SERVICIOS)'],
                ['codigoClasificador' => 74,  'descripcion' => 'BIDÓN'],
                ['codigoClasificador' => 59,  'descripcion' => 'US GALON (3,7843 L)'],
                ['codigoClasificador' => 75,  'descripcion' => 'BOLSA'],
                ['codigoClasificador' => 60,  'descripcion' => 'YARDA'],
                ['codigoClasificador' => 76,  'descripcion' => 'CAPSULA'],
                ['codigoClasificador' => 61,  'descripcion' => 'YARDA CUADRADA'],
                ['codigoClasificador' => 77,  'descripcion' => 'CARTUCHO'],
                ['codigoClasificador' => 62,  'descripcion' => 'OTRO'],
                ['codigoClasificador' => 78,  'descripcion' => 'COMPRIMIDO'],
                ['codigoClasificador' => 79,  'descripcion' => 'ESTUCHE'],
                ['codigoClasificador' => 80,  'descripcion' => 'FRASCO'],
                ['codigoClasificador' => 81,  'descripcion' => 'JERINGA'],
                ['codigoClasificador' => 82,  'descripcion' => 'MINI BOTELLA'],
                ['codigoClasificador' => 83,  'descripcion' => 'SACHET'],
                ['codigoClasificador' => 84,  'descripcion' => 'TABLETA'],
                ['codigoClasificador' => 85,  'descripcion' => 'TERMO'],
                ['codigoClasificador' => 86,  'descripcion' => 'TUBO'],
                ['codigoClasificador' => 65,  'descripcion' => 'DISPLAY'],
                ['codigoClasificador' => 66,  'descripcion' => 'BULTO'],
                ['codigoClasificador' => 109, 'descripcion' => 'OVULOS'],
                ['codigoClasificador' => 110, 'descripcion' => 'SUPOSITORIOS'],
                ['codigoClasificador' => 111, 'descripcion' => 'SOBRES'],
                ['codigoClasificador' => 112, 'descripcion' => 'VIAL'],
                ['codigoClasificador' => 113, 'descripcion' => 'HECTAREAS'],
                ['codigoClasificador' => 114, 'descripcion' => 'ARROBA'],
                ['codigoClasificador' => 115, 'descripcion' => 'AEROSOL'],
                ['codigoClasificador' => 116, 'descripcion' => 'BARRA'],
                ['codigoClasificador' => 117, 'descripcion' => 'CONJUNTO'],
                ['codigoClasificador' => 118, 'descripcion' => 'FANEGA'],
                ['codigoClasificador' => 119, 'descripcion' => 'PACK'],
                ['codigoClasificador' => 122, 'descripcion' => 'PASTILLA'],
                ['codigoClasificador' => 120, 'descripcion' => 'PIPETA'],
                ['codigoClasificador' => 121, 'descripcion' => 'POTE'],
                ['codigoClasificador' => 123, 'descripcion' => 'TONELADA METRICA'],
                ['codigoClasificador' => 124, 'descripcion' => 'EQUIPOS'],
                ['codigoClasificador' => 105, 'descripcion' => 'TIRA'],
                ['codigoClasificador' => 104, 'descripcion' => 'BLISTER'],
                ['codigoClasificador' => 103, 'descripcion' => 'TURRIL'],
                ['codigoClasificador' => 102, 'descripcion' => 'BANDEJA'],
                ['codigoClasificador' => 101, 'descripcion' => 'YARDA'],
                ['codigoClasificador' => 100, 'descripcion' => 'JABA'],
                ['codigoClasificador' => 67,  'descripcion' => 'DIAS'],
                ['codigoClasificador' => 99,  'descripcion' => 'CARTOLA'],
                ['codigoClasificador' => 68,  'descripcion' => 'MESES'],
                ['codigoClasificador' => 98,  'descripcion' => 'TETRAPACK'],
                ['codigoClasificador' => 97,  'descripcion' => 'VASO'],
                ['codigoClasificador' => 96,  'descripcion' => 'POMO'],
                ['codigoClasificador' => 95,  'descripcion' => 'UNIDAD TERMICA BRITANICA (TI)'],
                ['codigoClasificador' => 94,  'descripcion' => 'MILLONES DE BTU (1000000 BTU)'],
                ['codigoClasificador' => 93,  'descripcion' => 'MILLONES DE PIES CUBICOS (1000000 PC)'],
                ['codigoClasificador' => 69,  'descripcion' => 'QUINTAL'],
                ['codigoClasificador' => 92,  'descripcion' => 'MILLAR DE PIES CUBICOS (1000 PC)'],
                ['codigoClasificador' => 91,  'descripcion' => 'MIL PIES CUBICOS 14696 PSI 68FAH'],
                ['codigoClasificador' => 90,  'descripcion' => 'MIL PIES CUBICOS 14696 PSI'],
                ['codigoClasificador' => 89,  'descripcion' => 'METRO CUBICO 68F VOL'],
                ['codigoClasificador' => 88,  'descripcion' => 'BARRIL [42 GALONES(EEUU)]'],
                ['codigoClasificador' => 87,  'descripcion' => 'BARRIL (EEUU) 60 F'],
                ['codigoClasificador' => 106, 'descripcion' => 'MEGAWATT'],
                ['codigoClasificador' => 107, 'descripcion' => 'KILOWATT'],
                ['codigoClasificador' => 125, 'descripcion' => 'PIE TABLAR'],
                ['codigoClasificador' => 126, 'descripcion' => 'KILATES'],
                ['codigoClasificador' => 1,   'descripcion' => 'BOBINAS'],
                ['codigoClasificador' => 2,   'descripcion' => 'BALDE'],
                ['codigoClasificador' => 3,   'descripcion' => 'BARRILES'],
                ['codigoClasificador' => 4,   'descripcion' => 'BOLSA'],
                ['codigoClasificador' => 5,   'descripcion' => 'BOTELLAS'],
                ['codigoClasificador' => 6,   'descripcion' => 'CAJA'],
                ['codigoClasificador' => 7,   'descripcion' => 'CARTONES'],
                ['codigoClasificador' => 8,   'descripcion' => 'CENTIMETRO CUADRADO'],
                ['codigoClasificador' => 9,   'descripcion' => 'CENTIMETRO CUBICO'],
                ['codigoClasificador' => 10,  'descripcion' => 'CENTIMETRO LINEAL'],
                ['codigoClasificador' => 11,  'descripcion' => 'CIENTO DE UNIDADES'],
                ['codigoClasificador' => 12,  'descripcion' => 'CILINDRO'],
                ['codigoClasificador' => 13,  'descripcion' => 'CONOS'],
                ['codigoClasificador' => 14,  'descripcion' => 'DOCENA'],
                ['codigoClasificador' => 15,  'descripcion' => 'FARDO'],
                ['codigoClasificador' => 16,  'descripcion' => 'GALON INGLES'],
                ['codigoClasificador' => 17,  'descripcion' => 'GRAMO'],
                ['codigoClasificador' => 18,  'descripcion' => 'GRUESA'],
                ['codigoClasificador' => 19,  'descripcion' => 'HECTOLITRO'],
                ['codigoClasificador' => 20,  'descripcion' => 'HOJA'],
                ['codigoClasificador' => 21,  'descripcion' => 'JUEGO'],
                ['codigoClasificador' => 22,  'descripcion' => 'KILOGRAMO'],
                ['codigoClasificador' => 23,  'descripcion' => 'KILOMETRO'],
                ['codigoClasificador' => 24,  'descripcion' => 'KILOVATIO HORA'],
                ['codigoClasificador' => 25,  'descripcion' => 'KIT'],
                ['codigoClasificador' => 26,  'descripcion' => 'LATAS'],
                ['codigoClasificador' => 27,  'descripcion' => 'LIBRAS'],
                ['codigoClasificador' => 28,  'descripcion' => 'LITRO'],
                ['codigoClasificador' => 29,  'descripcion' => 'MEGAWATT HORA'],
                ['codigoClasificador' => 30,  'descripcion' => 'METRO'],
                ['codigoClasificador' => 31,  'descripcion' => 'METRO CUADRADO'],
                ['codigoClasificador' => 32,  'descripcion' => 'METRO CUBICO'],
                ['codigoClasificador' => 33,  'descripcion' => 'MILIGRAMOS'],
                ['codigoClasificador' => 34,  'descripcion' => 'MILILITRO'],
                ['codigoClasificador' => 35,  'descripcion' => 'MILIMETRO'],
                ['codigoClasificador' => 36,  'descripcion' => 'MILIMETRO CUADRADO'],
                ['codigoClasificador' => 37,  'descripcion' => 'MILIMETRO CUBICO'],
            ];
//            $med=Medida::where('codigoClasificador',$d->unidadMedida)->first();
            $med=null;
            foreach ($medidas as $m){
                if ($m['codigoClasificador']==$d->unidadMedida){
                    $med=(object)$m;
                    break;
                }
            }
            if ($med==null){
                $med=(object)['codigoClasificador'=>62,'descripcion'=>'OTRO'];
            }
            $d->unidadMedida = $med->descripcion;

//                $d->unidadMedida = Env::unidadMedidaOtroDescripcion;
//            }
            $detalles .= '
                <tr>
                    <td class="border">' . $d->codigoProducto . '</td>
                    <td class="border">' . $d->cantidad . '</td>
                    <td class="border">' . $d->unidadMedida . '</td>
                    <td class="border">' . $d->descripcion . '</td>
                    <td class="border right">' . $d->precioUnitario . '</td>
                    <td class="border right">0</td>
                    <td class="border right">' . $d->subTotal . '</td>
                </tr>
            ';
        }
        $url = env('URL_SIAT2') . "consulta/QR?nit=" . env('NIT') . "&cuf=" . $xml->cabecera->cuf . "&numero=" . $xml->cabecera->numeroFactura . "&t=2";
        $qrcode = base64_encode(QrCode::format('svg')->size(200)->errorCorrection('H')->generate($url));
        $doc = $xml->cabecera->complemento!=""? $xml->cabecera->numeroDocumento."-".$xml->cabecera->complemento: $xml->cabecera->numeroDocumento;
        $pieleyenda= $online?"Este documento es la Representación Gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en linea":"“Este documento es la
Representación Gráfica de un
Documento Fiscal Digital emitido
fuera de línea, verifique su envío
con su proveedor o en la página
web www.impuestos.gob.bo”.";
        return ('
        <!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <style>
        *{
            font-size: 12px;
        }
        .bold{
            font-weight: bold;
        }
        .text-h1{
            font-size: 20px;
        }
        .text-h2{
            font-size: 10px;
        }
        .text-h5{
            font-size: 8px;
        }
        .text-h6{
            font-size: 7px;
        }
        .center{
            text-align: center;
        }
        .right{
            text-align: right;
        }
        .border{
            border: 1px solid black
        }
        .collapse{
            border-collapse: collapse;
        }
        .background{
            background: #edf2f7
        }
        .overflow-visible {
          white-space: initial;
        }
    </style>
</head>
<body>
<table width="100%"  class="collapse" >
    <tr>
        <td width="33%">
            <div class="bold center">' . $xml->cabecera->razonSocialEmisor . '</div>
            <div class="bold center">CASA MATRIZ</div>
            <div class="center">No. Punto de Venta ' . $xml->cabecera->codigoPuntoVenta . '</div>
        </td>
        <td></td>
        <td width="120px">
            <table>
                <tr>
                    <td valign=top width="130px">
                        <div class="bold">NIT</div>
                        <div class="bold">FACTURA N°</div>
                        <div class="bold">CÓD. AUTORIZACIÓN</div>
                    </td>
                    <td>
                        <div>' . $xml->cabecera->nitEmisor . '</div>
                        <div>' . $xml->cabecera->numeroFactura . '</div>
                        <div style="width: 120px">' . $cuf . '</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <div class="center">
                ' . $xml->cabecera->direccion . '
            </div>
            <div class="center">Teléfono: ' . $xml->cabecera->telefono . '</div>
            <div class="center">Oruro-Bolivia</div>
        </td>
    </tr>
    <tr>
        <td colspan="3">
            <div class="bold center text-h1">
                FACTURA
            </div>
            <div class="center">
                (Con Derecho a Crédito Fiscal)
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <table width="100%">
                <tr>
                    <td>
                        <div class="bold">Fecha: </div>
                    </td>
                    <td>
                        <div>' . $fecha . ' ' . $hora . '</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="bold">Nombre/Razón Social:</div>
                    </td>
                    <td>
                        <div>' . $xml->cabecera->nombreRazonSocial . '</div>
                    </td>
                </tr>
            </table>
        </td>
        <td>
            <table width="100%">
                <tr>
                    <td class="right">
                        <div class="bold">NIT/CI/CEX:</div>
                    </td>
                    <td>
                        <div>'. $doc .  '</div>
                    </td>
                </tr>
                <tr>
                    <td class="right">
                        <div class="bold">Cod. Cliente:</div>
                    </td>
                    <td>
                        <div>' . $xml->cabecera->codigoCliente . '</div>
                    </td>
                </tr>
                '.$rental.'
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="3">
            <table width="100%" class="collapse">
                <tr class="background" >
                    <th class="border" width="50px" >CÓDIGO
                        PRODUCTO /
                        SERVICIO</th>
                    <th class="border">CANTIDAD</th>
                    <th class="border">UNIDAD DE
                        MEDIDA</th>
                    <th class="border">DESCRIPCIÓN</th>
                    <th class="border" width="60px">PRECIO
                        UNITARIO</th>
                    <th class="border">DESCUENTO</th>
                    <th class="border">SUBTOTAL</th>
                </tr>
                ' . $detalles . '
                <tr>
                    <td colspan="4"></td>
                    <td class="border right text-h5" colspan="2">SUBTOTAL Bs</td>
                    <td class="border right">' . $xml->cabecera->montoTotal . '</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td class="border right text-h5" colspan="2">DESCUENTO Bs</td>
                    <td class="border right">0</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td class="border right text-h5" colspan="2">TOTAL Bs</td>
                    <td class="border right">' . $xml->cabecera->montoTotal . '</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td class="border right text-h5" colspan="2">MONTO GIFT CARD Bs</td>
                    <td class="border right">0</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td class="border right text-h5 bold background" colspan="2">MONTO A PAGAR Bs</td>
                    <td class="border right">' . $xml->cabecera->montoTotal . '</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td class="border right  text-h5 bold background" colspan="2">IMPORTE BASE CRÉDITO FISCAL Bs</td>
                    <td class="border right">' . $xml->cabecera->montoTotal . '</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="3" >
            <table width="100%">
                <tr>
                    <td class="center" valign=top>
                        <div class="left bold">Son: ' . $literal . '</div>
                        <div class="text-h5">ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY
                            </div>
                        <div class="text-h5">' . $xml->cabecera->leyenda . '</div>
                        <div class="text-h2 bold">“'.$pieleyenda.'”</div>
                    </td>
                    <td>
                        <img width="95px" src="data:image/png;base64,' . $qrcode . '">
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
');
    }

}
