<?php

namespace Tests\Unit;

use App\Models\Configuracion;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\Siat\ElectronicInvoiceService;
use Carbon\Carbon;
use ReflectionClass;
use Tests\TestCase;

class ElectronicInvoiceXmlTest extends TestCase
{
    public function test_offline_mode_is_controlled_directly_in_the_service(): void
    {
        $source = file_get_contents(app_path('Services/Siat/ElectronicInvoiceService.php'));

        $this->assertStringContainsString('private const OFFLINE_MODE = false;', $source);
        $this->assertStringContainsString('$offlineMode = self::OFFLINE_MODE;', $source);
    }

    public function test_online_invoice_uses_the_real_siat_operation(): void
    {
        $source = file_get_contents(app_path('Services/Siat/ElectronicInvoiceService.php'));

        $this->assertStringContainsString("'SolicitudServicioRecepcionFactura' => [", $source);
        $this->assertStringContainsString('$soapClient->recepcionFactura($request)', $source);
        $this->assertStringNotContainsString('recepcionFacturaXXX', $source);
    }

    public function test_issue_failure_is_not_automatically_converted_to_offline_emission(): void
    {
        $source = file_get_contents(app_path('Services/Siat/ElectronicInvoiceService.php'));
        $issueMethod = (new ReflectionClass(ElectronicInvoiceService::class))->getMethod('issue');
        $issueSource = implode('', array_slice(
            file($issueMethod->getFileName()),
            $issueMethod->getStartLine() - 1,
            $issueMethod->getEndLine() - $issueMethod->getStartLine() + 1,
        ));

        $this->assertStringNotContainsString('prepareOfflineInvoice', $issueSource);
        $this->assertStringContainsString("'estado_siat' => 'ERROR_ENVIO'", $source);
    }

    public function test_general_discount_is_not_repeated_in_invoice_details(): void
    {
        config([
            'siat.municipio' => 'Oruro',
            'siat.sucursal' => 0,
            'siat.punto_venta' => 0,
            'siat.unidad_medida' => 58,
            'siat.leyenda' => 'Leyenda de prueba',
        ]);

        $company = new Configuracion([
            'nit' => '3544875019',
            'nombre_empresa' => 'Area Fresca',
            'direccion' => 'Oruro',
            'telefono' => '70492248',
        ]);

        $sale = new Venta([
            'cliente_nombre' => 'CHAMBI',
            'tipo_documento' => 'CI',
            'numero_documento' => '7336199',
            'usuario_nombre' => 'admin',
            'subtotal' => 36.30,
            'descuento' => 10,
            'total' => 26.30,
        ]);
        $sale->id = 20;
        $sale->setRelation('detalles', collect([
            new VentaDetalle([
                'codigo' => 'P-1',
                'nombre' => 'Producto',
                'cantidad' => 1,
                'precio_venta' => 36.30,
                'subtotal' => 36.30,
                'descuento' => 10,
                'total' => 26.30,
            ]),
        ]));

        $reflection = new ReflectionClass(ElectronicInvoiceService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildXml');
        $xml = $method->invoke(
            $service,
            $sale,
            $company,
            'CUFD',
            'CUF',
            Carbon::parse('2026-07-31 22:06:13.843'),
            '4720000',
            1000076,
        );

        $invoice = simplexml_load_string($xml);

        $this->assertSame('10.00', (string) $invoice->cabecera->descuentoAdicional);
        $this->assertSame('26.30', (string) $invoice->cabecera->montoTotal);
        $this->assertSame('0', (string) $invoice->detalle->montoDescuento);
        $this->assertSame('36.30', (string) $invoice->detalle->subTotal);
    }
}
