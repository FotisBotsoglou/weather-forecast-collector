<?php

namespace Tests\Feature;

use App\Enums\RunStatus;
use App\Models\DailyForecast;
use App\Models\ForecastRun;
use App\Models\HourlyForecast;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CollectForecastsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-19 08:00', 'Europe/Athens'));
        $this->location = $this->thessaloniki();
    }

    public function test_it_stores_a_snapshot_per_provider(): void
    {
        $this->fakeProviders();

        $this->artisan('forecasts:collect')
            ->expectsOutputToContain('Thessaloniki / open_meteo: collected (48 hourly and 2 daily values for 2026-11-19)')
            ->expectsOutputToContain('Thessaloniki / weatherapi: collected (4 hourly and 2 daily values for 2026-11-19)')
            ->assertSuccessful();

        $runs = ForecastRun::query()->orderBy('provider')->get();
        $this->assertSame(['open_meteo', 'weatherapi'], $runs->pluck('provider')->all());
        $this->assertTrue($runs->every(fn (ForecastRun $run) => $run->status === RunStatus::Succeeded
            && $run->issue_date->toDateString() === '2026-11-19'
            && $run->attempts === 1
            && $run->error === null));

        $this->assertSame(48, $runs[0]->hourlyForecasts()->count());
        $this->assertSame(4, $runs[1]->hourlyForecasts()->count());

        $first = $runs[0]->hourlyForecasts()->orderBy('valid_at')->first();
        $this->assertSame('2026-11-18T22:00:00Z', $first->valid_at->toIso8601ZuluString());
        $this->assertSame(16.7, $first->temperature_c);

        $daily = $runs[1]->dailyForecasts()->orderBy('forecast_date')->first();
        $this->assertSame('2026-11-19', $daily->forecast_date->toDateString());
        $this->assertSame(13.9, $daily->temperature_min_c);
        $this->assertSame(22.8, $daily->temperature_max_c);
        $this->assertSame(0.4, $daily->precipitation_mm);
    }

    public function test_a_day_already_collected_is_not_fetched_again(): void
    {
        $this->fakeProviders();
        $this->artisan('forecasts:collect')->assertSuccessful();

        $this->artisan('forecasts:collect')
            ->expectsOutputToContain('open_meteo: skipped (already collected for 2026-11-19)')
            ->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertSame(52, HourlyForecast::query()->count());
    }

    public function test_the_next_day_creates_a_new_snapshot_and_keeps_the_previous_one(): void
    {
        $this->fakeProviders();
        $this->artisan('forecasts:collect')->assertSuccessful();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00', 'Europe/Athens'));
        $this->artisan('forecasts:collect')->assertSuccessful();

        $this->assertSame(
            ['2026-11-19', '2026-11-20'],
            ForecastRun::query()->where('provider', 'open_meteo')->orderBy('issue_date')->get()
                ->map(fn (ForecastRun $run) => $run->issue_date->toDateString())->all(),
        );
        $this->assertSame(104, HourlyForecast::query()->count());
    }

    public function test_one_failing_provider_does_not_stop_the_other(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response('down', 503),
            'api.weatherapi.com/*' => Http::response($this->fixture('weatherapi-forecast')),
        ]);

        $this->artisan('forecasts:collect')
            ->expectsOutputToContain('open_meteo: failed (open_meteo responded with HTTP 503: down)')
            ->expectsOutputToContain('weatherapi: collected')
            ->assertFailed();

        $failed = ForecastRun::query()->where('provider', 'open_meteo')->sole();
        $this->assertSame(RunStatus::Failed, $failed->status);
        $this->assertSame('open_meteo responded with HTTP 503: down', $failed->error);
        $this->assertSame(0, $failed->hourlyForecasts()->count());

        $this->assertSame(RunStatus::Succeeded, ForecastRun::query()->where('provider', 'weatherapi')->sole()->status);
    }

    public function test_a_failed_run_is_retried_on_the_same_row(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::sequence()
            ->push('down', 503)->push('down', 503)->push('down', 503)
            ->push($this->fixture('open-meteo-forecast'))]);

        $this->artisan('forecasts:collect', ['--provider' => ['open_meteo']])->assertFailed();
        $this->artisan('forecasts:collect', ['--provider' => ['open_meteo']])->assertSuccessful();

        $run = ForecastRun::query()->sole();
        $this->assertSame(RunStatus::Succeeded, $run->status);
        $this->assertSame(2, $run->attempts);
        $this->assertNull($run->error);
        $this->assertSame(48, $run->hourlyForecasts()->count());
    }

    public function test_force_replaces_the_snapshot_instead_of_duplicating_it(): void
    {
        $changed = $this->fixture('open-meteo-forecast');
        $changed['hourly']['temperature_2m'][0] = 12.3;

        Http::fake(['api.open-meteo.com/*' => Http::sequence()
            ->push($this->fixture('open-meteo-forecast'))
            ->push($changed)]);

        $this->artisan('forecasts:collect', ['--provider' => ['open_meteo']])->assertSuccessful();
        $this->artisan('forecasts:collect', ['--provider' => ['open_meteo'], '--force' => true])->assertSuccessful();

        $run = ForecastRun::query()->sole();
        $this->assertSame(2, $run->attempts);
        $this->assertSame(48, $run->hourlyForecasts()->count());
        $this->assertSame(2, $run->dailyForecasts()->count());
        $this->assertSame(12.3, $run->hourlyForecasts()->orderBy('valid_at')->first()->temperature_c);
    }

    public function test_a_failed_forced_refresh_keeps_the_stored_snapshot(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::sequence()
            ->push($this->fixture('open-meteo-forecast'))
            ->push(['error' => true, 'reason' => 'Invalid request'], 400)]);

        $this->artisan('forecasts:collect', ['--provider' => ['open_meteo']])->assertSuccessful();
        $this->artisan('forecasts:collect', ['--provider' => ['open_meteo'], '--force' => true])->assertFailed();

        $run = ForecastRun::query()->sole();
        $this->assertSame(RunStatus::Succeeded, $run->status);
        $this->assertNull($run->error);
        $this->assertSame(48, $run->hourlyForecasts()->count());
    }

    public function test_an_invalid_response_does_not_leave_partial_data(): void
    {
        $payload = $this->fixture('open-meteo-forecast');
        $payload['daily']['temperature_2m_max'][1] = 'broken';
        Http::fake(['api.open-meteo.com/*' => Http::response($payload)]);

        $this->artisan('forecasts:collect', ['--provider' => ['open_meteo']])->assertFailed();

        $this->assertSame(RunStatus::Failed, ForecastRun::query()->sole()->status);
        $this->assertSame(0, HourlyForecast::query()->count());
        $this->assertSame(0, DailyForecast::query()->count());
    }

    public function test_a_collection_already_in_progress_is_skipped(): void
    {
        Http::fake();
        $lock = Cache::lock("forecast-collect:{$this->location->id}:open_meteo", 60);
        $lock->get();

        $this->artisan('forecasts:collect', ['--provider' => ['open_meteo']])
            ->expectsOutputToContain('open_meteo: skipped (another collection is in progress)')
            ->assertSuccessful();

        $lock->release();
        Http::assertNothingSent();
        $this->assertSame(0, ForecastRun::query()->count());
    }

    public function test_inactive_locations_are_ignored(): void
    {
        Http::fake();
        $this->location->update(['is_active' => false]);

        $this->artisan('forecasts:collect')
            ->expectsOutputToContain('No active locations to collect.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_broken_location_does_not_stop_the_others(): void
    {
        $this->fakeProviders();
        $this->location->update(['timezone' => 'Europe/Athen']);
        $larissa = Location::query()->create([
            'name' => 'Larissa',
            'latitude' => 39.6390,
            'longitude' => 22.4191,
            'timezone' => 'Europe/Athens',
        ]);

        $this->artisan('forecasts:collect', ['--provider' => ['open_meteo']])
            ->expectsOutputToContain('Thessaloniki / open_meteo: failed (Unknown or bad timezone (Europe/Athen))')
            ->expectsOutputToContain('Larissa / open_meteo: collected')
            ->assertFailed();

        $this->assertSame(1, ForecastRun::query()->where('location_id', $larissa->id)->count());
    }

    public function test_duplicate_hours_from_a_provider_are_stored_once(): void
    {
        $payload = $this->fixture('weatherapi-forecast');
        $payload['forecast']['forecastday'][0]['hour'][1]['time_epoch'] = $payload['forecast']['forecastday'][0]['hour'][0]['time_epoch'];
        Http::fake(['api.weatherapi.com/*' => Http::response($payload)]);

        $this->artisan('forecasts:collect', ['--provider' => ['weatherapi']])->assertSuccessful();

        $this->assertSame(3, HourlyForecast::query()->count());
    }

    public function test_non_numeric_location_ids_are_rejected(): void
    {
        Http::fake();

        $this->artisan('forecasts:collect', ['--location' => ['abc']])
            ->expectsOutputToContain('Location ids must be numeric.')
            ->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        Http::fake();

        $this->artisan('forecasts:collect', ['--provider' => ['accuweather']])
            ->expectsOutputToContain('Unknown provider(s): accuweather')
            ->assertExitCode(2);

        Http::assertNothingSent();
    }

    private function fakeProviders(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->fixture('open-meteo-forecast')),
            'api.weatherapi.com/*' => Http::response($this->fixture('weatherapi-forecast')),
        ]);
    }
}
