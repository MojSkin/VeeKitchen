<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Saturday 07:00 opens the Iranian week, so the mailed window is exactly
// the seven completed days of the week that just ended (Sat → Fri).
Schedule::command('reports:weekly-warehouse')
    ->weeklyOn(6, '07:00')
    ->withoutOverlapping();
