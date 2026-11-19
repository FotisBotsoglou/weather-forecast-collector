<?php

namespace App\Weather;

use App\Enums\RunStatus;
use App\Models\DailyForecast;
use App\Models\ForecastRun;
use App\Models\HourlyForecast;
use App\Models\Location;
use App\Weather\Data\DailyValue;
use App\Weather\Data\Forecast;
use App\Weather\Exceptions\ProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ForecastCollector
{
    private const INSERT_CHUNK = 500;

    public function __construct(private readonly ProviderRegistry $providers) {}

    /**
     * Stores one forecast snapshot per location, provider and local issue date.
     * Once a day has a successful snapshot it is skipped unless forced, which
     * keeps scheduled retries within the free API quotas.
     */
    public function collect(Location $location, string $provider, bool $force = false): CollectResult
    {
        $lock = Cache::lock("forecast-collect:{$location->id}:{$provider}", 600);

        if (! $lock->get()) {
            return new CollectResult($provider, CollectOutcome::Skipped, 'another collection is in progress');
        }

        try {
            return $this->collectWithLock($location, $provider, $force);
        } finally {
            $lock->release();
        }
    }

    private function collectWithLock(Location $location, string $provider, bool $force): CollectResult
    {
        $key = [
            'location_id' => $location->id,
            'provider' => $provider,
            'issue_date' => CarbonImmutable::now($location->timezone)->toDateString(),
        ];

        $run = ForecastRun::firstOrNew($key);

        if ($run->status === RunStatus::Succeeded && ! $force) {
            return new CollectResult($provider, CollectOutcome::Skipped, "already collected for {$key['issue_date']}");
        }

        $attempts = ($run->attempts ?? 0) + 1;

        try {
            $forecast = $this->providers->get($provider)->fetch($location);
            $this->store($run, $forecast, $attempts);
        } catch (Throwable $e) {
            return $this->fail($key, $attempts, $e);
        }

        return new CollectResult($provider, CollectOutcome::Collected, sprintf(
            '%d hourly and %d daily values for %s',
            count($forecast->hourly),
            count($forecast->daily),
            $key['issue_date'],
        ));
    }

    private function store(ForecastRun $run, Forecast $forecast, int $attempts): void
    {
        DB::transaction(function () use ($run, $forecast, $attempts) {
            $run->fill([
                'status' => RunStatus::Succeeded,
                'attempts' => $attempts,
                'error' => null,
                'fetched_at' => now(),
            ])->save();

            $run->hourlyForecasts()->delete();
            $run->dailyForecasts()->delete();

            // Keyed by time because local hour labels can map to the same instant around DST changes.
            $hourly = [];
            foreach ($forecast->hourly as $value) {
                $validAt = $value->validAt->utc()->format('Y-m-d H:i:sP');
                $hourly[$validAt] = [
                    'forecast_run_id' => $run->id,
                    'valid_at' => $validAt,
                    'temperature_c' => $value->temperatureC,
                    'precipitation_mm' => $value->precipitationMm,
                ];
            }
            $hourly = array_values($hourly);

            $daily = array_map(fn (DailyValue $value) => [
                'forecast_run_id' => $run->id,
                'forecast_date' => $value->date,
                'temperature_min_c' => $value->temperatureMinC,
                'temperature_max_c' => $value->temperatureMaxC,
                'temperature_mean_c' => $value->temperatureMeanC,
                'precipitation_mm' => $value->precipitationMm,
            ], $forecast->daily);

            foreach (array_chunk($hourly, self::INSERT_CHUNK) as $chunk) {
                HourlyForecast::insert($chunk);
            }

            foreach (array_chunk($daily, self::INSERT_CHUNK) as $chunk) {
                DailyForecast::insert($chunk);
            }
        });
    }

    private function fail(array $key, int $attempts, Throwable $e): CollectResult
    {
        if (! $e instanceof ProviderException) {
            report($e);
        }

        Log::warning('Forecast collection failed', $key + ['attempt' => $attempts, 'error' => $e->getMessage()]);

        // A failed forced refresh keeps the snapshot that was already stored for the day.
        $run = ForecastRun::firstOrNew($key);
        $run->attempts = $attempts;

        if ($run->status !== RunStatus::Succeeded) {
            $run->status = RunStatus::Failed;
            $run->error = $e->getMessage();
        }

        $run->save();

        return new CollectResult($key['provider'], CollectOutcome::Failed, $e->getMessage());
    }
}
