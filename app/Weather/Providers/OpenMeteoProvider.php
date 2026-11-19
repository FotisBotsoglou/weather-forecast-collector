<?php

namespace App\Weather\Providers;

use App\Models\Location;
use App\Weather\Data\DailyValue;
use App\Weather\Data\Forecast;
use App\Weather\Data\HourlyValue;
use App\Weather\Exceptions\ProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

class OpenMeteoProvider extends HttpForecastProvider
{
    private const HOURLY_FIELDS = ['temperature_2m', 'precipitation'];

    private const DAILY_FIELDS = ['temperature_2m_min', 'temperature_2m_max', 'temperature_2m_mean', 'precipitation_sum'];

    public function fetch(Location $location): Forecast
    {
        // With timeformat=unixtime hourly times are exact instants (no DST
        // ambiguity) and daily times are local midnights of the given timezone.
        $data = $this->getJson('forecast', [
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'timezone' => $location->timezone,
            'timeformat' => 'unixtime',
            'forecast_days' => $this->config['forecast_days'],
            'temperature_unit' => 'celsius',
            'precipitation_unit' => 'mm',
            'hourly' => implode(',', self::HOURLY_FIELDS),
            'daily' => implode(',', self::DAILY_FIELDS),
        ]);

        $hourly = [];
        foreach ($this->rows($data, 'hourly', self::HOURLY_FIELDS) as $row) {
            $hourly[] = new HourlyValue(
                CarbonImmutable::createFromTimestampUTC($row['time']),
                $row['temperature_2m'],
                $row['precipitation'],
            );
        }

        $daily = [];
        foreach ($this->rows($data, 'daily', self::DAILY_FIELDS) as $row) {
            $daily[] = new DailyValue(
                CarbonImmutable::createFromTimestamp($row['time'], $location->timezone)->toDateString(),
                $row['temperature_2m_min'],
                $row['temperature_2m_max'],
                $row['temperature_2m_mean'],
                $row['precipitation_sum'],
            );
        }

        return new Forecast($hourly, $daily);
    }

    protected function errorMessage(Response $response): string
    {
        $reason = $response->json('reason');

        return is_string($reason) ? $reason : parent::errorMessage($response);
    }

    /**
     * Open-Meteo returns one array per variable; zip them into rows.
     */
    private function rows(array $data, string $block, array $fields): array
    {
        $times = $data[$block]['time'] ?? null;

        if (! is_array($times) || $times === []) {
            throw ProviderException::unexpectedResponse($this->name, "missing {$block}.time");
        }

        foreach ($fields as $field) {
            $column = $data[$block][$field] ?? null;

            if (! is_array($column) || count($column) !== count($times)) {
                throw ProviderException::unexpectedResponse($this->name, "{$block}.{$field} is missing or incomplete");
            }
        }

        $rows = [];
        foreach (array_keys($times) as $i) {
            $row = ['time' => $this->timestamp($data, "{$block}.time.{$i}")];

            foreach ($fields as $field) {
                $row[$field] = $this->number($data, "{$block}.{$field}.{$i}");
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
