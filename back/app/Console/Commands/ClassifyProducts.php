<?php

namespace App\Console\Commands;

use App\Services\ProductCategoryClassifier;
use Illuminate\Console\Command;

class ClassifyProducts extends Command
{
    protected $signature = 'inventory:classify';
    protected $description = 'Clasifica los productos de Area Fresca segun su nombre';

    public function handle(ProductCategoryClassifier $classifier): int
    {
        $this->info($classifier->classifyAll().' productos clasificados correctamente.');
        return self::SUCCESS;
    }
}
