<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Database backup - Every day at 12:00 AM
Schedule::command('app:db-backup')->dailyAt('00:00')->withoutOverlapping();

// Complete project backup - Every Sunday at 12:00 AM
Schedule::command('app:whole-project-server-backup')->weeklyOn(0, '00:00')->withoutOverlapping();