<?php

namespace Tests\Feature;

use App\Enums\RunStatus;
use App\Models\ForecastRun;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastApiTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->location = $this->thessaloniki();

        $this->snapshot('open_meteo', '2026-11-18', ['2026-11-19' => [10.0, 20.0, 1.5]]);
        $this->snapshot('open_meteo', '2026-11-19', ['2026-11-19' => [11.0, 21.0, 0.5], '2026-11-20' => [12.0, 22.0, 0.0]]);
        $this->snapshot('weatherapi', '2026-11-19', ['2026-11-19' => [9.5, 19.5, 2.0]]);
        $this->snapshot('weatherapi', '2026-11-20', [], RunStatus::Failed);
    }

    public function test_locations_are_listed(): void
    {
        $this->getJson('/api/locations')
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $this->location->id,
                'name' => 'Thessaloniki',
                'latitude' => 40.6401,
                'longitude' => 22.9444,
                'timezone' => 'Europe/Athens',
                'is_active' => true,
            ]]]);
    }

    public function test_daily_returns_the_latest_successful_snapshot_of_each_provider(): void
    {
        $this->getJson("/api/locations/{$this->location->id}/forecasts/daily")
            ->assertOk()
            ->assertJsonPath('location.name', 'Thessaloniki')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.provider', 'open_meteo')
            ->assertJsonPath('data.0.issue_date', '2026-11-19')
            ->assertJsonPath('data.0.daily', [
                ['date' => '2026-11-19', 'temperature_min_c' => 11, 'temperature_max_c' => 21, 'temperature_mean_c' => null, 'precipitation_mm' => 0.5],
                ['date' => '2026-11-20', 'temperature_min_c' => 12, 'temperature_max_c' => 22, 'temperature_mean_c' => null, 'precipitation_mm' => 0],
            ])
            ->assertJsonPath('data.1.provider', 'weatherapi')
            ->assertJsonPath('data.1.issue_date', '2026-11-19')
            ->assertJsonMissingPath('data.0.hourly');
    }

    public function test_daily_can_be_filtered_by_issue_date_provider_and_range(): void
    {
        $this->getJson("/api/locations/{$this->location->id}/forecasts/daily?issued_on=2026-11-18")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.issue_date', '2026-11-18')
            ->assertJsonPath('data.0.daily.0.temperature_max_c', 20);

        $this->getJson("/api/locations/{$this->location->id}/forecasts/daily?provider=open_meteo&from=2026-11-20&to=2026-11-20")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.daily')
            ->assertJsonPath('data.0.daily.0.date', '2026-11-20');
    }

    public function test_hourly_range_uses_the_location_calendar_day(): void
    {
        $run = ForecastRun::query()->where(['provider' => 'open_meteo', 'issue_date' => '2026-11-19'])->sole();
        $run->hourlyForecasts()->createMany([
            ['valid_at' => '2026-11-19 21:00:00+00', 'temperature_c' => 15.0, 'precipitation_mm' => 0.0],
            ['valid_at' => '2026-11-19 22:00:00+00', 'temperature_c' => 14.5, 'precipitation_mm' => 0.1],
        ]);

        $this->getJson("/api/locations/{$this->location->id}/forecasts/hourly?provider=open_meteo&from=2026-11-19&to=2026-11-19")
            ->assertOk()
            ->assertJsonPath('data.0.hourly', [
                ['time' => '2026-11-19T21:00:00Z', 'temperature_c' => 15, 'precipitation_mm' => 0],
            ]);
    }

    public function test_revisions_show_how_the_forecast_for_a_day_changed(): void
    {
        $this->getJson("/api/locations/{$this->location->id}/forecasts/daily/2026-11-19/revisions")
            ->assertOk()
            ->assertJsonPath('date', '2026-11-19')
            ->assertJsonPath('data.0.provider', 'open_meteo')
            ->assertJsonPath('data.0.revisions.0.issue_date', '2026-11-18')
            ->assertJsonPath('data.0.revisions.0.lead_days', 1)
            ->assertJsonPath('data.0.revisions.0.temperature_max_c', 20)
            ->assertJsonPath('data.0.revisions.1.issue_date', '2026-11-19')
            ->assertJsonPath('data.0.revisions.1.lead_days', 0)
            ->assertJsonPath('data.1.provider', 'weatherapi')
            ->assertJsonCount(1, 'data.1.revisions');
    }

    public function test_forecast_runs_can_be_filtered_by_status(): void
    {
        $this->getJson('/api/forecast-runs?status=failed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider', 'weatherapi')
            ->assertJsonPath('data.0.issue_date', '2026-11-20')
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.error', 'HTTP 503');
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->getJson("/api/locations/{$this->location->id}/forecasts/daily?provider=unknown&from=2026-11-20&to=2026-11-19")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['provider', 'to']);

        $this->getJson("/api/locations/{$this->location->id}/forecasts/daily/2026-02-30/revisions")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);

        $this->getJson('/api/forecast-runs?status=pending')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_an_unknown_location_returns_404(): void
    {
        $this->getJson('/api/locations/999/forecasts/daily')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.']);

        $this->get('/api/unknown')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.']);

        $this->getJson('/api/locations/abc/forecasts/daily')->assertNotFound();
        $this->getJson('/api/locations/99999999999999999999/forecasts/hourly')->assertNotFound();
    }

    public function test_forecast_run_filters_are_validated(): void
    {
        $this->getJson('/api/forecast-runs?provider=unknown&location_id=99999999999999999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['provider', 'location_id']);
    }

    /**
     * @param  array<string, array{float, float, float}>  $days  date => [min, max, precipitation]
     */
    private function snapshot(string $provider, string $issueDate, array $days, RunStatus $status = RunStatus::Succeeded): void
    {
        $run = ForecastRun::query()->create([
            'location_id' => $this->location->id,
            'provider' => $provider,
            'issue_date' => $issueDate,
            'status' => $status,
            'attempts' => 1,
            'error' => $status === RunStatus::Failed ? 'HTTP 503' : null,
            'fetched_at' => $status === RunStatus::Succeeded ? now() : null,
        ]);

        foreach ($days as $date => [$min, $max, $precipitation]) {
            $run->dailyForecasts()->create([
                'forecast_date' => $date,
                'temperature_min_c' => $min,
                'temperature_max_c' => $max,
                'precipitation_mm' => $precipitation,
            ]);
        }
    }
}
