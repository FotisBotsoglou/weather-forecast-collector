<?php

use Illuminate\Support\Facades\Schedule;

// Runs hourly inside the window; providers already collected for the day are
// skipped, so only a failed provider is fetched again.
Schedule::command('forecasts:collect')
    ->hourly()
    ->between(config('weather.schedule.window_start'), config('weather.schedule.window_end'))
    ->timezone(config('weather.schedule.timezone'))
    ->withoutOverlapping(10);
