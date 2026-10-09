<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:google-drive')
    ->dailyAt('01:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(65)
    ->onOneServer();

Schedule::command('pulse:check --once')
    ->everyMinute()
    ->withoutOverlapping();
