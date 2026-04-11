<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SIGVA - Suma anual de vacaciones
// Ejecutar diariamente a las 00:01 para verificar aniversarios de contrato
Schedule::command('vacaciones:suma-anual')
    ->dailyAt('00:01')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/suma-anual-vacaciones.log'));

// SIGVA - Sincronizacion de sedes maestras desde SIGETH
Schedule::command('sedes:sync-from-core')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sync-sedes-from-core.log'));
