<?php

namespace App\Weather;

use App\Weather\Providers\ForecastProvider;
use InvalidArgumentException;

class ProviderRegistry
{
    /**
     * @return list<string>
     */
    public function enabled(): array
    {
        return array_values(config('weather.enabled_providers'));
    }

    /**
     * @return list<string>
     */
    public function known(): array
    {
        return array_keys(config('weather.providers'));
    }

    public function get(string $name): ForecastProvider
    {
        $config = config("weather.providers.{$name}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown weather provider [{$name}].");
        }

        return new $config['class']($name, $config);
    }
}
