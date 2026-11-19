<?php

namespace App\Weather\Providers;

use App\Models\Location;
use App\Weather\Data\Forecast;
use App\Weather\Exceptions\ProviderException;

interface ForecastProvider
{
    /**
     * Values are normalised to °C and mm, hourly times to UTC and
     * daily dates to the location's local calendar day.
     *
     * @throws ProviderException
     */
    public function fetch(Location $location): Forecast;
}
