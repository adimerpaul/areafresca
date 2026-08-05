<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Producto;

class ProductCategoryClassifier
{
    private const CATEGORIES = [
        'POLLO Y AVES' => 'red', 'CARNE DE RES' => 'deep-orange', 'CARNE DE CERDO' => 'pink',
        'PESCADOS Y MARISCOS' => 'light-blue', 'EMBUTIDOS' => 'purple',
        'HAMBURGUESAS Y PREPARADOS' => 'orange', 'QUESOS Y LACTEOS' => 'amber',
        'PANADERIA Y PASTAS' => 'brown', 'SALSAS Y CONDIMENTOS' => 'red',
        'FRUTAS, VERDURAS Y CONSERVAS' => 'green', 'CONGELADOS' => 'cyan',
        'BEBIDAS' => 'blue', 'POSTRES Y DULCES' => 'pink',
        'ALIMENTOS PARA MASCOTAS' => 'blue-grey', 'HUEVOS' => 'amber', 'ABARROTES' => 'teal', 'OTROS' => 'grey',
    ];

    public function classifyAll(): int
    {
        $ids = [];
        foreach (self::CATEGORIES as $name => $color) {
            $category = Categoria::withTrashed()->firstOrCreate(['nombre' => $name], ['color' => $color]);
            if ($category->trashed()) $category->restore();
            $ids[$name] = $category->id;
        }

        $count = 0;
        Producto::query()->orderBy('id')->chunkById(200, function ($products) use ($ids, &$count) {
            foreach ($products as $product) {
                $category = $this->classify($product->nombre);
                $product->updateQuietly(['categoria' => $category, 'categoria_id' => $ids[$category]]);
                $count++;
            }
        });
        return $count;
    }

    public function classify(string $productName): string
    {
        $name = mb_strtoupper($productName);
        return match (true) {
            $this->has($name, ['PODIUM', 'DOG STAR', 'CANINO', 'FELINO', 'MICAN', 'CACHORRO RAZA', 'ALIMENTO FELINO']) => 'ALIMENTOS PARA MASCOTAS',
            $this->has($name, ['HUEVO ']) => 'HUEVOS',
            $this->has($name, ['HAMB', 'ALBONDIGA', 'MILANESA', 'NUGGET', 'NUGUET', 'TENDER', 'CORDON BLEU', 'SILPANCHO', 'SOFIPOCA']) => 'HAMBURGUESAS Y PREPARADOS',
            $this->has($name, ['CHORIZO', 'SALCHICHA', 'MORTADELA', 'JAMON', 'SALAME', 'MORCILLA', 'PATE ', 'PERNIL AHUMADO', 'TOCINO', 'TOCINETA', 'ARROLLADO', 'ENROLLADO']) => 'EMBUTIDOS',
            $this->has($name, ['TRUCHA', 'ATUN', 'SALMON', 'MERLUZA', 'PAICHE', 'TILAPIA', 'PACU', 'TAMBAQUI', 'LANGOST', 'MARISCO', 'PULPO', 'QUINCHO', 'FILETE RETAIL']) => 'PESCADOS Y MARISCOS',
            $this->has($name, ['QUESO', 'MOZZARELLA', 'MOZARELLA', 'MANTEQUILLA']) => 'QUESOS Y LACTEOS',
            $this->has($name, ['SALSA', 'KETCHUP', 'MAYONESA', 'MOSTAZA', 'CHIMICHURRI', 'LLAJUA', 'CREMA DE AJI', 'CURRY', 'PAPRIKA', 'HIERBAS', 'SEASONING', 'VINAGRE', 'EXTRACTO DE TOMATE', 'DIP ']) => 'SALSAS Y CONDIMENTOS',
            $this->has($name, ['PAN ', 'PRE PIZZA', 'TACO ', 'LASAGNA', 'SPAGUETTI', 'TALLARIN', 'RIGATE', 'TAPAS DE EMPANADA']) => 'PANADERIA Y PASTAS',
            $this->has($name, ['VINO ', 'JUGO ', 'CAFE ', 'SULTANA']) => 'BEBIDAS',
            $this->has($name, ['GELATINA', 'MIEL ', 'MERMELADA', 'DELIFRUTA']) => 'POSTRES Y DULCES',
            $this->has($name, ['MINUTO VERDE', 'PAPA TRADICIONAL', 'PAPAS CRINKLE', 'PAPAS GRINKLE']) => 'CONGELADOS',
            $this->has($name, ['ACEITUNA', 'CHUCRUT', 'JALAPE', 'PALMITO', 'PEPINILLO', 'LOMITOS DE ACEITE', 'MARKA INDUSTRIAL']) => 'FRUTAS, VERDURAS Y CONSERVAS',
            $this->has($name, ['POLLO', 'PATO', 'CODORNIZ', 'CUY', 'ALAS ', 'ALITAS', 'PECHUGA', 'MUSLO', 'MENUDENCIA']) => 'POLLO Y AVES',
            $this->has($name, ['CERDO', 'PORK', 'PANCETA', 'BONDIOLA', 'MATAMBRE', 'CHULETA', 'CHICHARRON']) => 'CARNE DE CERDO',
            $this->has($name, ['RES ', ' DE RES', 'BIFE', 'CUADRIL', 'LOMITO', 'COSTILLA', 'PUNTA DE S', 'POLLERITA', 'TIRA ESPECIAL', 'CARNE MOLIDA']) => 'CARNE DE RES',
            $this->has($name, ['SOPA ', 'ACEITE ', 'CARBONELL VIRGEN', 'CORN FLAKES', 'AVENA', 'GALLETA', 'FINGERS']) => 'ABARROTES',
            default => 'OTROS',
        };
    }

    private function has(string $name, array $terms): bool
    {
        foreach ($terms as $term) if (str_contains($name, $term)) return true;
        return false;
    }
}
