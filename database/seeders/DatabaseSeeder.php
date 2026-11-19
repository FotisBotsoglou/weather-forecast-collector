<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $location = config('weather.default_location');

        Location::query()->firstOrCreate(
            ['latitude' => $location['latitude'], 'longitude' => $location['longitude']],
            ['name' => $location['name'], 'timezone' => $location['timezone']],
        );
    }
}
