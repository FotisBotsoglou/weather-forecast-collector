<?php

namespace Tests\Feature;

use App\Weather\Exceptions\ProviderException;
use App\Weather\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherApiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalises_the_forecast(): void
    {
        Http::fake(['api.weatherapi.com/*' => Http::response($this->fixture('weatherapi-forecast'))]);

        $forecast = app(ProviderRegistry::class)->get('weatherapi')->fetch($this->thessaloniki());

        $this->assertCount(4, $forecast->hourly);
        $this->assertSame('2026-11-18T22:00:00+00:00', $forecast->hourly[0]->validAt->toIso8601String());
        $this->assertSame(16.1, $forecast->hourly[0]->temperatureC);
        $this->assertSame(0.2, $forecast->hourly[1]->precipitationMm);
        $this->assertSame('2026-11-19T22:00:00+00:00', $forecast->hourly[2]->validAt->toIso8601String());

        $this->assertCount(2, $forecast->daily);
        $this->assertSame('2026-11-19', $forecast->daily[0]->date);
        $this->assertSame(13.9, $forecast->daily[0]->temperatureMinC);
        $this->assertSame(22.8, $forecast->daily[0]->temperatureMaxC);
        $this->assertSame(17.6, $forecast->daily[0]->temperatureMeanC);
        $this->assertSame(0.4, $forecast->daily[0]->precipitationMm);

        Http::assertSent(fn (Request $request) => $request['key'] === 'test-key'
            && $request['q'] === '40.640100,22.944400'
            && $request['days'] == 3
            && $request['aqi'] === 'no'
            && $request['alerts'] === 'no');
    }

    public function test_a_missing_api_key_fails_without_calling_the_api(): void
    {
        config(['weather.providers.weatherapi.key' => null]);
        Http::fake();

        try {
            app(ProviderRegistry::class)->get('weatherapi')->fetch($this->thessaloniki());
            $this->fail('Expected a ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame('WEATHERAPI_KEY is not configured', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_client_errors_are_not_retried(): void
    {
        Http::fake(['api.weatherapi.com/*' => Http::response(['error' => ['code' => 2007, 'message' => 'API key has exceeded calls per month quota.']], 403)]);

        try {
            app(ProviderRegistry::class)->get('weatherapi')->fetch($this->thessaloniki());
            $this->fail('Expected a ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame('weatherapi responded with HTTP 403: API key has exceeded calls per month quota.', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_connection_errors_do_not_leak_the_api_key(): void
    {
        Http::fake(['api.weatherapi.com/*' => Http::failedConnection()]);

        try {
            app(ProviderRegistry::class)->get('weatherapi')->fetch($this->thessaloniki());
            $this->fail('Expected a ProviderException');
        } catch (ProviderException $e) {
            $this->assertStringStartsWith('Could not reach weatherapi:', $e->getMessage());
            $this->assertStringContainsString('key=***', $e->getMessage());
            $this->assertStringNotContainsString('test-key', $e->getMessage());
        }

        Http::assertSentCount(3);
    }

    public function test_missing_fields_are_rejected(): void
    {
        $payload = $this->fixture('weatherapi-forecast');
        unset($payload['forecast']['forecastday'][1]['hour'][0]['temp_c']);
        Http::fake(['api.weatherapi.com/*' => Http::response($payload)]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Unexpected response from weatherapi: missing forecast.forecastday.1.hour.0.temp_c');

        app(ProviderRegistry::class)->get('weatherapi')->fetch($this->thessaloniki());
    }

    public function test_an_empty_forecast_is_rejected(): void
    {
        Http::fake(['api.weatherapi.com/*' => Http::response(['forecast' => ['forecastday' => []]])]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('missing forecast.forecastday');

        app(ProviderRegistry::class)->get('weatherapi')->fetch($this->thessaloniki());
    }
}
