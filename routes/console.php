<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('tenants:run carts:purge')->daily();

Schedule::command('tenants:run shop:sync-prices --tenants=g3')->dailyAt('03:00')->withoutOverlapping();

