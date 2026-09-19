<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwalkan auto backup media storage public & database ke OneDrive setiap pukul 00:01 WIB
Schedule::command('backup:onedrive')->dailyAt('00:01');

