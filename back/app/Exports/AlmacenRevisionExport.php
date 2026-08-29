<?php

namespace App\Exports;

use App\Models\Almacen;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Libro completo de una revisión: el detalle producto por producto, los lotes
 * contados y el avance de cada persona. Es el mismo archivo para la pantalla de
 * llenado y para la de avance; sólo cambian los filtros con que se pide.
 */
class AlmacenRevisionExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Almacen $almacen,
        private readonly Collection $detalles,
        private readonly array $resumen,
        private readonly array $meta = [],
    ) {}

    public function sheets(): array
    {
        $hojas = [new AlmacenDetalleExport($this->almacen, $this->detalles, $this->resumen, $this->meta)];

        if ($this->detalles->contains(fn ($detalle) => $detalle->conteos->isNotEmpty())) {
            $hojas[] = new AlmacenLotesExport($this->almacen, $this->detalles, $this->meta);
        }
        $hojas[] = new AlmacenUsuariosExport($this->almacen, $this->detalles, $this->meta);

        return $hojas;
    }
}
