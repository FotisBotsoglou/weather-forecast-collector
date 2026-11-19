<?php

namespace App\Weather\Data;

final readonly class Forecast
{
    /**
     * @param  list<HourlyValue>  $hourly
     * @param  list<DailyValue>  $daily
     */
    public function __construct(
        public array $hourly,
        public array $daily,
    ) {}
}
