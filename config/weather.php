<?php

use App\Weather\Providers\OpenMeteoProvider;
use App\Weather\Providers\WeatherApiProvider;

return [

    'enabled_providers' => array_filter(array_map(
        'trim',
        explode(',', env('WEATHER_PROVIDERS', 'open_meteo,weatherapi')),
    )),

    'providers' => [
        'open_meteo' => [
            'class' => OpenMeteoProvider::class,
            'base_url' => env('OPEN_METEO_BASE_URL', 'https://api.open-meteo.com/v1'),
            'forecast_days' => (int) env('OPEN_METEO_FORECAST_DAYS', 7),
        ],
        'weatherapi' => [
            'class' => WeatherApiProvider::class,
            'base_url' => env('WEATHERAPI_BASE_URL', 'https://api.weatherapi.com/v1'),
            'key' => env('WEATHERAPI_KEY'),
            // The free plan is limited to 3 forecast days.
            'forecast_days' => (int) env('WEATHERAPI_FORECAST_DAYS', 3),
        ],
    ],

    'http' => [
        'timeout' => (int) env('WEATHER_HTTP_TIMEOUT', 15),
        'retry_delays_ms' => [1000, 3000],
    ],

    'schedule' => [
        'timezone' => env('WEATHER_SCHEDULE_TIMEZONE', 'Europe/Athens'),
        'window_start' => env('WEATHER_SCHEDULE_START', '06:00'),
        'window_end' => env('WEATHER_SCHEDULE_END', '12:00'),
    ],

    'default_location' => [
        'name' => env('WEATHER_LOCATION_NAME', 'Thessaloniki'),
        'latitude' => (float) env('WEATHER_LOCATION_LATITUDE', 40.6401),
        'longitude' => (float) env('WEATHER_LOCATION_LONGITUDE', 22.9444),
        'timezone' => env('WEATHER_LOCATION_TIMEZONE', 'Europe/Athens'),
    ],

];
