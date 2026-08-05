<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('siat:renovar-cufd')
    ->dailyAt('02:54')
    ->timezone('America/La_Paz')
    ->withoutOverlapping(30);
