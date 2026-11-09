<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('ydpay:process-orders deposit')->everyMinute()->withoutOverlapping(5)->runInBackground();
Schedule::command('ydpay:process-orders withdraw')->everyMinute()->withoutOverlapping(5)->runInBackground();

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');

Schedule::command('products:sync-status')
    ->dailyAt('00:01')
    ->withoutOverlapping();

Schedule::command('svip:auto-claim-daily-income')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('qc168:sync-pending-recharges')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('qc168:dispatch-approved-withdrawals')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('qc168:sync-pending-withdrawals')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->runInBackground();
