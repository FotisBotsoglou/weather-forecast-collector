<?php

namespace App\Weather\Data;

final readonly class DailyValue
{
    public function __construct(
        public string $date,
        public ?float $temperatureMinC,
        public ?float $temperatureMaxC,
        public ?float $temperatureMeanC,
        public ?float $precipitationMm,
    ) {}
}
