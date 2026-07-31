<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $catalog = [
            'POLLOS' => ['Pollo entero', 'Pechuga de pollo', 'Pierna con encuentro', 'Alitas de pollo', 'Filete de pechuga', 'Menudencia de pollo', 'Muslo de pollo', 'Milanesa de pollo', 'Pollo deshuesado', 'Cuarto trasero'],
            'EMBUTIDOS' => ['Salchicha viena', 'Salchicha parrillera', 'Chorizo criollo', 'Chorizo argentino', 'Chorizo ahumado', 'Longaniza', 'Morcilla', 'Butifarra', 'Salame', 'Pepperoni'],
            'JAMONES' => ['Jamón cocido', 'Jamón ahumado', 'Jamón de pollo', 'Jamón de pavo', 'Jamón serrano', 'Jamón sandwichero', 'Jamón inglés', 'Jamón primavera', 'Jamón picnic', 'Jamón artesanal'],
            'CARNES' => ['Carne molida especial', 'Lomo de res', 'Bife de chorizo', 'Costilla de res', 'Chuleta de cerdo', 'Costilla de cerdo', 'Matambre', 'Picaña', 'Pulpa especial', 'Osobuco'],
            'HAMBURGUESAS' => ['Hamburguesa clásica', 'Hamburguesa de pollo', 'Hamburguesa parrillera', 'Hamburguesa con queso', 'Hamburguesa premium', 'Hamburguesa mini', 'Hamburguesa de cerdo', 'Hamburguesa ahumada', 'Hamburguesa casera', 'Hamburguesa doble'],
            'CONGELADOS' => ['Nuggets de pollo', 'Papas prefritas', 'Aros de cebolla', 'Milanesa congelada', 'Empanadas de pollo', 'Empanadas de carne', 'Croquetas de pollo', 'Deditos de queso', 'Pollo broaster', 'Alitas picantes'],
            'QUESOS' => ['Queso mozzarella', 'Queso cheddar', 'Queso criollo', 'Queso gouda', 'Queso sandwichero', 'Queso provolone', 'Queso parmesano', 'Queso fundido', 'Queso dambo', 'Queso ahumado'],
            'SALSAS' => ['Mayonesa', 'Kétchup', 'Mostaza', 'Salsa golf', 'Salsa barbacoa', 'Salsa picante', 'Chimichurri', 'Salsa de ajo', 'Salsa tártara', 'Salsa para pollo'],
            'PARRILLA' => ['Carbón vegetal', 'Leña para parrilla', 'Sal parrillera', 'Condimento para pollo', 'Condimento para carne', 'Brochetas de bambú', 'Papel aluminio', 'Encendedor para carbón', 'Bandeja de aluminio', 'Guantes parrilleros'],
            'COMBOS' => ['Combo familiar', 'Combo parrillero', 'Combo pollo', 'Combo hamburguesa', 'Combo choripán', 'Combo fin de semana', 'Combo económico', 'Combo premium', 'Combo cumpleaños', 'Combo empresarial'],
        ];
        $colors = ['deep-orange', 'orange', 'amber', 'brown', 'red', 'blue-grey', 'yellow-9', 'pink', 'grey-9', 'primary'];

        DB::transaction(function () use ($catalog, $colors) {
            DB::table('productos')->delete();
            DB::table('categorias')->delete();
            $sequence = 1;

            foreach ($catalog as $categoryIndex => $items) {
                $categoryId = DB::table('categorias')->insertGetId([
                    'nombre' => $categoryIndex,
                    'color' => $colors[array_search($categoryIndex, array_keys($catalog), true)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($items as $itemIndex => $name) {
                    $weighted = in_array($categoryIndex, ['POLLOS', 'EMBUTIDOS', 'JAMONES', 'CARNES', 'QUESOS'], true);
                    $purchasePrice = round(12 + fmod($sequence * 1.17, 48), 2);
                    DB::table('productos')->insert([
                        'codigo' => sprintf('BEAN-%04d', $sequence),
                        'codigo_barras' => sprintf('780100%06d', $sequence),
                        'nombre' => mb_strtoupper($name),
                        'categoria' => $categoryIndex,
                        'categoria_id' => $categoryId,
                        'unidad' => $weighted ? 'KG' : 'PZS',
                        'precio_compra' => $purchasePrice,
                        'precio_venta' => round($purchasePrice * 1.32, 2),
                        'stock_inicial' => $weighted ? (15000 + $itemIndex * 2750) : (15 + $itemIndex * 4),
                        'foto' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $sequence++;
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('productos')->where('codigo', 'like', 'BEAN-%')->delete();
    }
};
