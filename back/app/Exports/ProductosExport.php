<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductosExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Collection $products) {}

    public function collection(): Collection
    {
        return $this->products->map(fn ($product) => [
            $product->codigo,
            $product->codigo_barras,
            $product->nombre,
            $product->categoriaRelacion?->nombre ?? $product->categoria,
            $product->unidad,
            $product->precio_compra,
            $product->precio_venta,
            $product->precio_1,
            $product->precio_2,
            $product->precio_3,
            $product->precio_4,
            $product->stock_inicial,
        ]);
    }

    public function headings(): array
    {
        return ['Código', 'Código de barras', 'Producto', 'Categoría', 'Unidad', 'Precio compra', 'Precio venta', 'Precio 1', 'Precio 2', 'Precio 3', 'Precio 4', 'Stock'];
    }
}
