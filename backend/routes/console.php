<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Osilib qolgan buyurtmalarni yopish (server'da `php artisan schedule:work`).
Schedule::command('orders:expire')->everyFiveMinutes()->withoutOverlapping();
