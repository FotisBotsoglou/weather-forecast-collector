<?php

namespace Tests\Feature;

use App\Weather\Exceptions\ProviderException;
use App\Weather\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenMeteoProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalises_the_forecast(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->fixture('open-meteo-forecast'))]);

        $forecast = app(ProviderRegistry::class)->get('open_meteo')->fetch($this->thessaloniki());

        $this->assertCount(48, $forecast->hourly);
        $this->assertSame('2026-11-18T22:00:00+00:00', $forecast->hourly[0]->validAt->toIso8601String());
        $this->assertSame(16.7, $forecast->hourly[0]->temperatureC);
        $this->assertSame(0.0, $forecast->hourly[0]->precipitationMm);
        $this->assertSame('2026-11-20T21:00:00+00:00', $forecast->hourly[47]->validAt->toIso8601String());

        $this->assertCount(2, $forecast->daily);
        $this->assertSame('2026-11-19', $forecast->daily[0]->date);
        $this->assertSame(14.2, $forecast->daily[0]->temperatureMinC);
        $this->assertSame(23.2, $forecast->daily[0]->temperatureMaxC);
        $this->assertSame(18.6, $forecast->daily[0]->temperatureMeanC);
        $this->assertSame(0.0, $forecast->daily[0]->precipitationMm);
        $this->assertSame('2026-11-20', $forecast->daily[1]->date);

        Http::assertSent(fn (Request $request) => $request['latitude'] == 40.6401
            && $request['longitude'] == 22.9444
            && $request['timezone'] === 'Europe/Athens'
            && $request['timeformat'] === 'unixtime'
            && $request['forecast_days'] == 7
            && $request['hourly'] === 'temperature_2m,precipitation'
            && $request['daily'] === 'temperature_2m_min,temperature_2m_max,temperature_2m_mean,precipitation_sum');
    }

    public function test_null_values_are_kept_as_missing_data(): void
    {
        $payload = $this->fixture('open-meteo-forecast');
        $payload['hourly']['temperature_2m'][5] = null;
        Http::fake(['api.open-meteo.com/*' => Http::response($payload)]);

        $forecast = app(ProviderRegistry::class)->get('open_meteo')->fetch($this->thessaloniki());

        $this->assertNull($forecast->hourly[5]->temperatureC);
    }

    public function test_api_errors_carry_the_provider_reason(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response(['error' => true, 'reason' => 'Latitude must be in range of -90 to 90°.'], 400)]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('open_meteo responded with HTTP 400: Latitude must be in range of -90 to 90°.');

        try {
            app(ProviderRegistry::class)->get('open_meteo')->fetch($this->thessaloniki());
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_server_errors_are_retried(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::sequence()
            ->push('upstream error', 502)
            ->push('', 429)
            ->push($this->fixture('open-meteo-forecast'))]);

        $forecast = app(ProviderRegistry::class)->get('open_meteo')->fetch($this->thessaloniki());

        $this->assertCount(48, $forecast->hourly);
        Http::assertSentCount(3);
    }

    public function test_it_gives_up_after_the_configured_retries(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response('unavailable', 503)]);

        try {
            app(ProviderRegistry::class)->get('open_meteo')->fetch($this->thessaloniki());
            $this->fail('Expected a ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame('open_meteo responded with HTTP 503: unavailable', $e->getMessage());
        }

        Http::assertSentCount(3);
    }

    public function test_columns_of_different_length_are_rejected(): void
    {
        $payload = $this->fixture('open-meteo-forecast');
        array_pop($payload['hourly']['precipitation']);
        Http::fake(['api.open-meteo.com/*' => Http::response($payload)]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('hourly.precipitation is missing or incomplete');

        app(ProviderRegistry::class)->get('open_meteo')->fetch($this->thessaloniki());
    }

    public function test_non_numeric_values_are_rejected(): void
    {
        $payload = $this->fixture('open-meteo-forecast');
        $payload['daily']['precipitation_sum'][1] = 'n/a';
        Http::fake(['api.open-meteo.com/*' => Http::response($payload)]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('daily.precipitation_sum.1 is not a number');

        app(ProviderRegistry::class)->get('open_meteo')->fetch($this->thessaloniki());
    }
}
