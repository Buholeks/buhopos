<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('stripe:conciliar-suscripciones')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('facturacion:vencer-solicitudes-pago')->hourly()->withoutOverlapping();
