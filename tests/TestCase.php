<?php

namespace Tests;

use App\Models\Location;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['weather.http.retry_delays_ms' => [0, 0]]);
        Http::preventStrayRequests();
    }

    protected function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__."/Fixtures/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function thessaloniki(): Location
    {
        return Location::query()->create([
            'name' => 'Thessaloniki',
            'latitude' => 40.6401,
            'longitude' => 22.9444,
            'timezone' => 'Europe/Athens',
        ]);
    }
}
