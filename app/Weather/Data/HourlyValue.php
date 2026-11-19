<?php

namespace App\Weather\Data;

use Carbon\CarbonImmutable;

final readonly class HourlyValue
{
    public function __construct(
        public CarbonImmutable $validAt,
        public ?float $temperatureC,
        public ?float $precipitationMm,
    ) {}
}
