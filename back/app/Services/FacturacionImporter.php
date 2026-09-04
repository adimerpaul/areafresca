<?php

namespace App\Services;

use App\Models\Facturacion;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Importa el libro de ventas del SIAT (XLSX) a la tabla `facturaciones`.
 *
 * El CUF manda: si ya existe una fila con ese código de autorización —aunque esté
 * eliminada— la fila se cuenta como duplicada y no se inserta ni se actualiza.
 * Así el mismo archivo se puede reimportar sin miedo y meses solapados no chocan.
 *
 * El archivo se lee por bloques de filas (PhpSpreadsheet guarda cada celda como
 * objeto y un libro mensual completo agota los 128 MB por defecto de PHP), así que
 * la memoria usada no depende de cuántas filas traiga el reporte.
 */
class FacturacionImporter
{
    /** Cabeceras exactas del reporte del SIAT, en orden. */
    private const HEADERS = [
        'Nº', 'FECHA DE LA FACTURA', 'Nº DE LA FACTURA', 'CODIGO DE AUTORIZACIÓN', 'NIT / CI CLIENTE',
        'COMPLEMENTO', 'NOMBRE O RAZON SOCIAL', 'IMPORTE TOTAL DE LA VENTA', 'IMPORTE ICE', 'IMPORTE IEHD',
        'IMPORTE IPJ', 'TASAS', 'OTROS NO SUJETOS AL IVA', 'EXPORTACIONES Y OPERACIONES EXENTAS',
        'VENTAS GRAVADAS A TASA CERO', 'SUBTOTAL', 'DESCUENTOS BONIFICACIONES Y REBAJAS SUJETAS AL IVA',
        'IMPORTE GIFT CARD', 'IMPORTE BASE PARA DEBITO FISCAL', 'DEBITO FISCAL', 'ESTADO',
        'CODIGO DE CONTROL', 'TIPO DE VENTA', 'CON DERECHO A CREDITO FISCAL', 'ESTADO CONSOLIDACION',
    ];

    /** Filas del XLSX leídas por vuelta. */
    private const READ_CHUNK = 1000;

    /** Filas por INSERT, para no pasarse del max_allowed_packet de MySQL. */
    private const INSERT_CHUNK = 500;

    private const LAST_COLUMN = 'Y';

    /**
     * Acepta el XLSX suelto o el ZIP tal como lo descarga el SIAT (que trae el XLSX dentro).
     *
     * @return array{total:int, insertados:int, duplicados:int, meses:array<string>}
     */
    public function import(string $path, string $fileName, ?int $userId = null): array
    {
        $extracted = $this->extractSpreadsheet($path);

        try {
            return $this->importSpreadsheet($extracted ?? $path, $fileName, $userId);
        } finally {
            if ($extracted !== null) {
                @unlink($extracted);
            }
        }
    }

    /**
     * Si el archivo es un ZIP con el reporte dentro, deja el XLSX en un temporal y devuelve su ruta.
     * Devuelve null cuando ya es un XLSX (que también es un ZIP, pero trae `[Content_Types].xml`).
     */
    private function extractSpreadsheet(string $path): ?string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;    // no es un ZIP: será un XLS antiguo y el lector se encarga
        }

        if ($zip->locateName('[Content_Types].xml') !== false) {
            $zip->close();

            return null;    // es el propio XLSX
        }

        $entry = null;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if (preg_match('/\.(xlsx|xls)$/i', $name)) {
                $entry = $name;
                break;
            }
        }

        if ($entry === null) {
            $zip->close();
            throw new RuntimeException('El archivo comprimido no contiene ningún Excel del libro de ventas.');
        }

        $base = tempnam(sys_get_temp_dir(), 'facturacion');
        @unlink($base);
        $target = $base.'.xlsx';

        $source = $zip->getStream($entry);
        if ($source === false || ! @copy('zip://'.$path.'#'.$entry, $target)) {
            $zip->close();
            throw new RuntimeException("No se pudo extraer «{$entry}» del archivo comprimido.");
        }
        if (is_resource($source)) {
            fclose($source);
        }
        $zip->close();

        return $target;
    }

    /**
     * @return array{total:int, insertados:int, duplicados:int, meses:array<string>}
     */
    private function importSpreadsheet(string $path, string $fileName, ?int $userId): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $info = $reader->listWorksheetInfo($path);
        } catch (Throwable $exception) {
            throw new RuntimeException('No se pudo leer el archivo Excel: '.$exception->getMessage());
        }

        $lastRow = (int) ($info[0]['totalRows'] ?? 0);
        $filter = new class implements IReadFilter
        {
            public int $from = 1;

            public int $to = 1;

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row >= $this->from && $row <= $this->to;
            }
        };
        $reader->setReadFilter($filter);

        $seen = [];
        $months = [];
        $total = 0;
        $inserted = 0;
        $pending = [];
        $headersChecked = false;

        for ($from = 1; $from <= $lastRow; $from += self::READ_CHUNK) {
            $to = min($from + self::READ_CHUNK - 1, $lastRow);
            $filter->from = $from;
            $filter->to = $to;

            try {
                $spreadsheet = $reader->load($path);
                $chunk = $spreadsheet->getSheet(0)
                    ->rangeToArray('A'.$from.':'.self::LAST_COLUMN.$to, null, true, false, false);
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            } catch (Throwable $exception) {
                throw new RuntimeException('No se pudo leer el archivo Excel: '.$exception->getMessage());
            }

            foreach ($chunk as $offset => $row) {
                $number = $from + $offset;
                if ($number === 1) {
                    $this->assertHeaders($row);
                    $headersChecked = true;

                    continue;
                }

                $cuf = trim((string) ($row[3] ?? ''));
                if ($cuf === '' || isset($seen[$cuf])) {
                    continue;   // pie del reporte, fila vacía o repetida dentro del propio archivo
                }
                $seen[$cuf] = true;
                $total++;

                $date = $this->parseDate($row[1] ?? null, $number);
                $months[$date->format('Y-m')] = true;
                $pending[$cuf] = $this->mapRow($row, $cuf, $date, $fileName, $userId);

                if (count($pending) >= self::INSERT_CHUNK) {
                    $inserted += $this->insertNew($pending);
                    $pending = [];
                }
            }

            unset($chunk);
        }

        if (! $headersChecked) {
            throw new RuntimeException('El archivo no tiene las columnas del libro de ventas del SIAT.');
        }

        if ($pending !== []) {
            $inserted += $this->insertNew($pending);
        }

        $months = array_keys($months);
        sort($months);

        return [
            'total' => $total,
            'insertados' => $inserted,
            'duplicados' => $total - $inserted,
            'meses' => $months,
        ];
    }

    /**
     * Inserta sólo los CUF que todavía no están en la base.
     *
     * withTrashed: un CUF borrado sigue ocupando el índice único, así que también se salta.
     */
    private function insertNew(array $rows): int
    {
        $existing = Facturacion::withTrashed()->whereIn('cuf', array_keys($rows))->pluck('cuf')->all();
        $new = array_values(array_diff_key($rows, array_flip($existing)));
        if ($new === []) {
            return 0;
        }
        Facturacion::insert($new);

        return count($new);
    }

    private function mapRow(array $row, string $cuf, Carbon $date, string $fileName, ?int $userId): array
    {
        return [
            'nro' => is_numeric($row[0] ?? null) ? (int) $row[0] : null,
            'fecha_factura' => $date->toDateString(),
            'numero_factura' => $this->text($row[2] ?? null, 30) ?? '',
            'cuf' => $cuf,
            'nit_ci_cliente' => $this->text($row[4] ?? null, 30),
            'complemento' => $this->text($row[5] ?? null, 10),
            'razon_social' => $this->text($row[6] ?? null, 255),
            'importe_total' => $this->amount($row[7] ?? null),
            'importe_ice' => $this->amount($row[8] ?? null),
            'importe_iehd' => $this->amount($row[9] ?? null),
            'importe_ipj' => $this->amount($row[10] ?? null),
            'tasas' => $this->amount($row[11] ?? null),
            'otros_no_sujetos_iva' => $this->amount($row[12] ?? null),
            'exportaciones_exentas' => $this->amount($row[13] ?? null),
            'ventas_tasa_cero' => $this->amount($row[14] ?? null),
            'subtotal' => $this->amount($row[15] ?? null),
            'descuentos' => $this->amount($row[16] ?? null),
            'importe_gift_card' => $this->amount($row[17] ?? null),
            'importe_base_debito_fiscal' => $this->amount($row[18] ?? null),
            'debito_fiscal' => $this->amount($row[19] ?? null),
            'estado' => mb_strtoupper($this->text($row[20] ?? null, 20) ?? 'VALIDA'),
            'codigo_control' => $this->text($row[21] ?? null, 30),
            'tipo_venta' => $this->text($row[22] ?? null, 30),
            'credito_fiscal' => $this->text($row[23] ?? null, 5),
            'estado_consolidacion' => $this->text($row[24] ?? null, 30),
            'archivo_origen' => mb_substr($fileName, 0, 255),
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function assertHeaders(array $row): void
    {
        $found = array_map(fn ($value) => $this->normalizeHeader($value), array_slice($row, 0, count(self::HEADERS)));
        $expected = array_map(fn ($value) => $this->normalizeHeader($value), self::HEADERS);

        if ($found !== $expected) {
            throw new RuntimeException('El archivo no tiene las columnas del libro de ventas del SIAT.');
        }
    }

    private function normalizeHeader(mixed $value): string
    {
        return preg_replace('/\s+/u', ' ', trim(mb_strtoupper((string) $value)));
    }

    private function text(mixed $value, int $length): ?string
    {
        $text = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $text === '' ? null : mb_substr($text, 0, $length);
    }

    private function amount(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }
        // El reporte a veces trae los importes como texto con separador de miles.
        $clean = str_replace([' ', ','], '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }

    private function parseDate(mixed $value, int $row): Carbon
    {
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            }

            return Carbon::createFromFormat('d/m/Y', trim((string) $value))->startOfDay();
        } catch (Throwable) {
            throw new RuntimeException("Fila {$row}: fecha de la factura inválida ({$value}).");
        }
    }
}
