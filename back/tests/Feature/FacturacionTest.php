<?php

namespace Tests\Feature;

use App\Models\Facturacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use ZipArchive;

class FacturacionTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'Nº', 'FECHA DE LA FACTURA', 'Nº DE LA FACTURA', 'CODIGO DE AUTORIZACIÓN', 'NIT / CI CLIENTE',
        'COMPLEMENTO', 'NOMBRE O RAZON SOCIAL', 'IMPORTE TOTAL DE LA VENTA', 'IMPORTE ICE', 'IMPORTE IEHD',
        'IMPORTE IPJ', 'TASAS', 'OTROS NO SUJETOS AL IVA', 'EXPORTACIONES Y OPERACIONES EXENTAS',
        'VENTAS GRAVADAS A TASA CERO', 'SUBTOTAL', 'DESCUENTOS BONIFICACIONES Y REBAJAS SUJETAS AL IVA',
        'IMPORTE GIFT CARD', 'IMPORTE BASE PARA DEBITO FISCAL', 'DEBITO FISCAL', 'ESTADO',
        'CODIGO DE CONTROL', 'TIPO DE VENTA', 'CON DERECHO A CREDITO FISCAL', 'ESTADO CONSOLIDACION',
    ];

    private function admin(): User
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);

        return $admin;
    }

    /** Una fila del libro de ventas, con los importes en las mismas columnas que el reporte del SIAT. */
    private function row(string $cuf, string $date, string $number, float $total, string $estado = 'VALIDA'): array
    {
        return [
            1, $date, $number, $cuf, '5722167', null, 'HUANCA', $total, 0, 0, 0, 0, 0, 0, 0,
            $total, 0, 0, $total, round($total * 0.13, 2), $estado, '', 'OTROS', 'SI', 'CONSOLIDADO',
        ];
    }

    private function xlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray(array_merge([self::HEADERS], $rows), null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'libro').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function upload(string $path, string $name): TestResponse
    {
        return $this->post('/api/facturacion/importar', [
            'archivo' => new UploadedFile($path, $name, null, null, true),
        ]);
    }

    public function test_the_cuf_prevents_importing_the_same_invoice_twice(): void
    {
        $this->admin();
        $file = $this->xlsx([
            $this->row('CUF-1', '31/08/2026', '9384', 52.55),
            $this->row('CUF-2', '30/08/2026', '9382', 68.33, 'ANULADA'),
        ]);

        $this->upload($file, 'archivoVentas.xlsx')->assertOk()
            ->assertJson(['total' => 2, 'insertados' => 2, 'duplicados' => 0, 'meses' => ['2026-08']]);

        // El archivo crece con una factura nueva: sólo entra esa, las repetidas se omiten.
        $bigger = $this->xlsx([
            $this->row('CUF-1', '31/08/2026', '9384', 52.55),
            $this->row('CUF-2', '30/08/2026', '9382', 68.33, 'ANULADA'),
            $this->row('CUF-3', '01/09/2026', '9400', 10.00),
        ]);

        $this->upload($bigger, 'archivoVentas.xlsx')->assertOk()
            ->assertJson(['total' => 3, 'insertados' => 1, 'duplicados' => 2]);

        $this->assertSame(3, Facturacion::count());
        $this->assertSame(1, Facturacion::where('cuf', 'CUF-1')->count());
    }

    public function test_a_deleted_invoice_keeps_its_cuf_reserved(): void
    {
        $this->admin();
        $file = $this->xlsx([$this->row('CUF-1', '31/08/2026', '9384', 52.55)]);
        $this->upload($file, 'archivoVentas.xlsx')->assertOk();

        $this->deleteJson('/api/facturacion/'.Facturacion::first()->id)->assertNoContent();

        // Reimportar no debe resucitar ni duplicar lo que alguien borró a propósito.
        $this->upload($file, 'archivoVentas.xlsx')->assertOk()
            ->assertJson(['total' => 1, 'insertados' => 0, 'duplicados' => 1]);

        $this->assertSame(0, Facturacion::count());
        $this->assertSame(1, Facturacion::withTrashed()->count());
    }

    public function test_it_imports_the_zip_the_siat_delivers(): void
    {
        $this->admin();
        $xlsx = $this->xlsx([$this->row('CUF-Z', '15/08/2026', '9001', 20.00)]);
        $zipPath = tempnam(sys_get_temp_dir(), 'libro').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($xlsx, 'archivoVentas.xlsx');
        $zip->close();

        $this->upload($zipPath, 'VentasXlsx (34).zip')->assertOk()
            ->assertJson(['total' => 1, 'insertados' => 1]);

        $this->assertSame('CUF-Z', Facturacion::first()->cuf);
    }

    public function test_the_listing_defaults_to_the_previous_month(): void
    {
        $this->admin();
        $previous = now()->subMonthNoOverflow();
        $this->upload($this->xlsx([
            $this->row('CUF-ANT', $previous->copy()->startOfMonth()->format('d/m/Y'), '1', 100.00),
            $this->row('CUF-ACT', now()->startOfMonth()->format('d/m/Y'), '2', 200.00),
        ]), 'archivoVentas.xlsx')->assertOk();

        // Sin filtro se ve el mes cerrado, que es el que el SIAT ya publicó.
        $this->getJson('/api/facturacion')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.cuf', 'CUF-ANT');

        $this->getJson('/api/facturacion?mes='.now()->format('Y-m'))->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.cuf', 'CUF-ACT');

        $resumen = $this->getJson('/api/facturacion-resumen')->assertOk()
            ->assertJsonPath('mes', $previous->format('Y-m'))
            ->assertJsonPath('cantidad', 1);
        $this->assertEquals(100, $resumen->json('importe_total'));
    }

    public function test_importing_requires_its_own_permission(): void
    {
        $user = User::create(['name' => 'CONTADORA', 'username' => 'contadora', 'password' => bcrypt('123456')]);
        $user->givePermissionTo('Ver Facturación');
        Sanctum::actingAs($user);

        $this->upload($this->xlsx([$this->row('CUF-1', '31/08/2026', '9384', 52.55)]), 'archivoVentas.xlsx')
            ->assertForbidden();

        $this->getJson('/api/facturacion')->assertOk();
    }
}
