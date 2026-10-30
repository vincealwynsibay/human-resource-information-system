<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// The public demo is rebuilt from scratch every night, so whatever visitors changed is gone by morning.
if (config('demo.enabled')) {
    Schedule::command('hris:demo --fresh')->dailyAt(config('demo.reset_at'))->timezone('Asia/Manila')->withoutOverlapping();
}
