<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Base de los reportes en Excel: encabezado con la empresa, el título del
 * reporte, los filtros que se aplicaron y quién lo generó; debajo una banda de
 * indicadores, la tabla con cabecera de color, autofiltro, fila de totales y la
 * hoja lista para imprimir. Las clases hijas sólo ponen los datos.
 */
abstract class ReporteExcel extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    /** Fila donde arranca la tabla; se calcula al armar el arreglo. */
    protected int $filaCabecera = 7;

    protected int $filas = 0;

    private const COLOR_TITULO = '1B5E20';

    private const COLOR_CABECERA = '1B5E20';

    private const COLOR_BANDA = 'E8F5E9';

    private const COLOR_CEBRA = 'F6FAF6';

    private const COLOR_BORDE = 'C8D6CB';

    public function __construct(protected readonly array $meta = []) {}

    /** Título del reporte (segunda línea del encabezado). */
    abstract public function titulo(): string;

    /** Cabeceras de la tabla. */
    abstract public function cabeceras(): array;

    /** Filas de datos, ya formateadas en el mismo orden que las cabeceras. */
    abstract public function filas(): array;

    /** Línea de contexto debajo del título (revisión, estado, rango, …). */
    public function subtitulo(): string
    {
        return '';
    }

    /** Indicadores que salen en la banda superior: ['Etiqueta' => valor]. */
    public function indicadores(): array
    {
        return [];
    }

    /** Fila de totales al pie de la tabla, o [] si el reporte no lleva. */
    public function totales(): array
    {
        return [];
    }

    /** Ancho de cada columna: ['A' => 12, …]. */
    public function anchos(): array
    {
        return [];
    }

    /** Formato numérico por columna: ['E' => '#,##0.000', …]. */
    public function formatos(): array
    {
        return [];
    }

    /** Columnas que son etiquetas y no números (códigos, números de documento). */
    public function columnasTexto(): array
    {
        return [];
    }

    public function array(): array
    {
        $columnas = count($this->cabeceras());
        $vacia = array_fill(0, $columnas, null);
        $indicadores = $this->indicadores();

        $filas = [
            $this->ancha($this->empresa(), $columnas),
            $this->ancha($this->titulo(), $columnas),
            $this->ancha($this->subtitulo(), $columnas),
            $this->ancha($this->textoFiltros(), $columnas),
            $this->ancha($this->textoGenerado(), $columnas),
            $vacia,
        ];

        if ($indicadores !== []) {
            $filas[] = $this->ancha(array_keys($indicadores), $columnas);
            $filas[] = $this->ancha(array_values($indicadores), $columnas);
            $filas[] = $vacia;
        }

        $this->filaCabecera = count($filas) + 1;
        $filas[] = $this->cabeceras();

        $datos = $this->filas();
        $this->filas = count($datos);
        foreach ($datos as $fila) {
            $filas[] = array_values($fila);
        }

        if ($this->filas === 0) {
            $filas[] = $this->ancha('No hay información para los filtros seleccionados', $columnas);
        } elseif ($totales = $this->totales()) {
            $filas[] = array_values($totales);
        }

        return $filas;
    }

    public function title(): string
    {
        return 'Reporte';
    }

    /**
     * Códigos, números de revisión y lotes son etiquetas: si Excel los toma como
     * número se pierden los ceros a la izquierda y el prefijo.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if ($value !== null && $cell->getRow() > $this->filaCabecera && in_array($cell->getColumn(), $this->columnasTexto(), true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->decorar($event->sheet->getDelegate()),
        ];
    }

    protected function empresa(): string
    {
        return $this->meta['empresa'] ?? 'Area Fresca';
    }

    /** Fecha o null convertida al serial que entiende Excel. */
    protected function fechaExcel($valor): ?float
    {
        if (! $valor) {
            return null;
        }

        return Date::dateTimeToExcel($valor instanceof \DateTimeInterface ? $valor : Carbon::parse($valor));
    }

    private function textoFiltros(): string
    {
        $filtros = array_filter($this->meta['filtros'] ?? [], fn ($valor) => $valor !== null && $valor !== '');
        if ($filtros === []) {
            return 'Filtros: sin filtros, se incluye todo el periodo';
        }

        return 'Filtros: '.collect($filtros)->map(fn ($valor, $etiqueta) => "{$etiqueta}: {$valor}")->implode('  ·  ');
    }

    private function textoGenerado(): string
    {
        $usuario = $this->meta['usuario'] ?? '';
        $registros = $this->filas === 1 ? '1 registro' : "{$this->filas} registros";

        return 'Generado el '.now()->format('d/m/Y H:i').($usuario ? " por {$usuario}" : '')."  ·  {$registros}";
    }

    /** Una celda de texto seguida de nulos para poder combinar toda la fila. */
    private function ancha($valor, int $columnas): array
    {
        $valores = is_array($valor) ? array_values($valor) : [$valor];

        return array_pad(array_slice($valores, 0, $columnas), $columnas, null);
    }

    private function decorar(Worksheet $hoja): void
    {
        $columnas = count($this->cabeceras());
        $ultima = Coordinate::stringFromColumnIndex($columnas);
        $cabecera = $this->filaCabecera;
        $primeraFila = $cabecera + 1;
        $ultimaFila = $cabecera + max($this->filas, 1);
        $filaTotales = $this->filas > 0 && $this->totales() ? $ultimaFila + 1 : null;

        $hoja->setShowGridlines(false);
        foreach (range(1, 5) as $fila) {
            $hoja->mergeCells("A{$fila}:{$ultima}{$fila}");
        }
        $hoja->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => self::COLOR_TITULO]]]);
        $hoja->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '263238']]]);
        $hoja->getStyle('A3')->applyFromArray(['font' => ['size' => 10, 'color' => ['rgb' => '546E7A']]]);
        $hoja->getStyle('A4:A5')->applyFromArray(['font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '78909C']]]);
        $hoja->getRowDimension(1)->setRowHeight(22);
        $hoja->getRowDimension(2)->setRowHeight(18);

        if ($this->indicadores() !== []) {
            $this->decorarIndicadores($hoja, $ultima);
        }

        $hoja->getStyle("A{$cabecera}:{$ultima}{$cabecera}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_CABECERA]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_CABECERA]]],
        ]);
        $hoja->getRowDimension($cabecera)->setRowHeight(26);

        if ($this->filas === 0) {
            $hoja->mergeCells("A{$primeraFila}:{$ultima}{$primeraFila}");
            $hoja->getStyle("A{$primeraFila}")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['rgb' => '90A4AE']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        } else {
            $rango = "A{$primeraFila}:{$ultima}{$ultimaFila}";
            $hoja->getStyle($rango)->applyFromArray([
                'font' => ['size' => 10],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDE]]],
            ]);
            // Rayado sólo en reportes de tamaño razonable: en miles de filas no vale el costo.
            if ($this->filas <= 2000) {
                for ($fila = $primeraFila + 1; $fila <= $ultimaFila; $fila += 2) {
                    $hoja->getStyle("A{$fila}:{$ultima}{$fila}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_CEBRA);
                }
            }
            $hoja->setAutoFilter("A{$cabecera}:{$ultima}{$ultimaFila}");
        }

        if ($filaTotales) {
            $hoja->getStyle("A{$filaTotales}:{$ultima}{$filaTotales}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1B5E20']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_BANDA]],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => self::COLOR_CABECERA]]],
            ]);
        }

        foreach ($this->formatos() as $columna => $formato) {
            $hasta = $filaTotales ?: $ultimaFila;
            $hoja->getStyle("{$columna}{$primeraFila}:{$columna}{$hasta}")->getNumberFormat()->setFormatCode($formato);
        }
        foreach ($this->anchos() as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }

        $hoja->freezePane("A{$primeraFila}");
        $hoja->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($cabecera, $cabecera);
        $hoja->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
        $hoja->getHeaderFooter()->setOddFooter('&L&9'.$this->empresa().'&C&9'.$this->titulo().'&R&9Página &P de &N');
    }

    /** Banda de indicadores: etiquetas arriba, valores abajo, cada uno en su celda. */
    private function decorarIndicadores(Worksheet $hoja, string $ultima): void
    {
        $etiquetas = $this->filaCabecera - 3;
        $valores = $this->filaCabecera - 2;

        $hoja->getStyle("A{$etiquetas}:{$ultima}{$etiquetas}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '546E7A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_BANDA]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $hoja->getStyle("A{$valores}:{$ultima}{$valores}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1B5E20']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDE]]],
        ]);
        $hoja->getRowDimension($valores)->setRowHeight(20);
    }
}
