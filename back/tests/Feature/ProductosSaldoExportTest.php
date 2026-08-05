<?php

namespace Tests\Feature;

use App\Exports\ProductosSaldoExport;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProductosSaldoExportTest extends TestCase
{
    public function test_genera_el_reporte_de_saldo_con_formulas_y_encabezado_amarillo(): void
    {
        $product = new Producto([
            'codigo' => 'P-001',
            'codigo_barras' => '7792070000938',
            'nombre' => 'Producto de prueba',
            'fecha_registro' => '2026-08-05',
            'stock_inicial' => 4.125,
            'precio_compra' => 25.83,
            'precio_venta' => 36.30,
        ]);

        $contents = Excel::raw(new ProductosSaldoExport(new Collection([$product])), \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'saldo-productos-');
        file_put_contents($path, $contents);

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();

            $this->assertSame('Cod. Producto', $sheet->getCell('A1')->getValue());
            $this->assertSame('FFFF00', $sheet->getStyle('A1')->getFill()->getStartColor()->getRGB());
            $this->assertSame('7792070000938', $sheet->getCell('A2')->getValue());
            $this->assertSame('=D2*E2', $sheet->getCell('G2')->getValue());
            $this->assertSame('=F2-E2', $sheet->getCell('H2')->getValue());
            $this->assertEqualsWithDelta(106.55, $sheet->getCell('G2')->getCalculatedValue(), 0.01);
            $this->assertEqualsWithDelta(10.47, $sheet->getCell('H2')->getCalculatedValue(), 0.01);
            $this->assertSame('TOTAL GENERAL', $sheet->getCell('F3')->getValue());
            $this->assertSame('=SUM(G2:G2)', $sheet->getCell('G3')->getValue());
            $this->assertEqualsWithDelta(106.55, $sheet->getCell('G3')->getCalculatedValue(), 0.01);
            $this->assertSame('FFFF00', $sheet->getStyle('G3')->getFill()->getStartColor()->getRGB());
        } finally {
            @unlink($path);
        }
    }
}
