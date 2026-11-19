<?php

namespace App\Weather\Providers;

use App\Models\Location;
use App\Weather\Data\DailyValue;
use App\Weather\Data\Forecast;
use App\Weather\Data\HourlyValue;
use App\Weather\Exceptions\ProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

class WeatherApiProvider extends HttpForecastProvider
{
    public function fetch(Location $location): Forecast
    {
        if (blank($this->config['key'] ?? null)) {
            throw new ProviderException('WEATHERAPI_KEY is not configured');
        }

        $data = $this->getJson('forecast.json', [
            'key' => $this->config['key'],
            'q' => sprintf('%F,%F', $location->latitude, $location->longitude),
            'days' => $this->config['forecast_days'],
            'aqi' => 'no',
            'alerts' => 'no',
        ]);

        $days = $data['forecast']['forecastday'] ?? null;

        if (! is_array($days) || $days === []) {
            throw ProviderException::unexpectedResponse($this->name, 'missing forecast.forecastday');
        }

        $hourly = [];
        $daily = [];

        foreach ($days as $i => $day) {
            $path = "forecast.forecastday.{$i}";
            $date = $day['date'] ?? null;

            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw ProviderException::unexpectedResponse($this->name, "{$path}.date is not a date");
            }

            $daily[] = new DailyValue(
                $date,
                $this->number($data, "{$path}.day.mintemp_c"),
                $this->number($data, "{$path}.day.maxtemp_c"),
                $this->number($data, "{$path}.day.avgtemp_c"),
                $this->number($data, "{$path}.day.totalprecip_mm"),
            );

            if (! is_array($day['hour'] ?? null)) {
                throw ProviderException::unexpectedResponse($this->name, "missing {$path}.hour");
            }

            foreach (array_keys($day['hour']) as $j) {
                $hourly[] = new HourlyValue(
                    CarbonImmutable::createFromTimestampUTC($this->timestamp($data, "{$path}.hour.{$j}.time_epoch")),
                    $this->number($data, "{$path}.hour.{$j}.temp_c"),
                    $this->number($data, "{$path}.hour.{$j}.precip_mm"),
                );
            }
        }

        return new Forecast($hourly, $daily);
    }

    protected function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) ? $message : parent::errorMessage($response);
    }
}
