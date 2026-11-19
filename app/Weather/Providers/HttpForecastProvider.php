<?php

namespace App\Weather\Providers;

use App\Weather\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

abstract class HttpForecastProvider implements ForecastProvider
{
    public function __construct(
        protected readonly string $name,
        protected readonly array $config,
    ) {}

    protected function getJson(string $path, array $query): array
    {
        try {
            $response = Http::baseUrl($this->config['base_url'])
                ->acceptJson()
                ->timeout(config('weather.http.timeout'))
                ->retry(config('weather.http.retry_delays_ms'), when: fn (Throwable $e) => $this->isTransient($e))
                ->get($path, $query);
        } catch (ConnectionException $e) {
            throw new ProviderException("Could not reach {$this->name}: ".$this->redact($e->getMessage()), previous: $e);
        } catch (RequestException $e) {
            throw new ProviderException(
                "{$this->name} responded with HTTP {$e->response->status()}: ".$this->errorMessage($e->response),
                previous: $e,
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw ProviderException::unexpectedResponse($this->name, 'body is not a JSON object');
        }

        return $data;
    }

    protected function errorMessage(Response $response): string
    {
        return Str::limit(trim($response->body()), 300);
    }

    protected function number(array $data, string $path): ?float
    {
        if (! Arr::has($data, $path)) {
            throw ProviderException::unexpectedResponse($this->name, "missing {$path}");
        }

        $value = Arr::get($data, $path);

        if ($value !== null && ! is_int($value) && ! is_float($value)) {
            throw ProviderException::unexpectedResponse($this->name, "{$path} is not a number");
        }

        return $value === null ? null : (float) $value;
    }

    protected function timestamp(array $data, string $path): int
    {
        $value = Arr::get($data, $path);

        if (! is_int($value)) {
            throw ProviderException::unexpectedResponse($this->name, "{$path} is not a unix timestamp");
        }

        return $value;
    }

    private function isTransient(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        return $e instanceof RequestException
            && ($e->response->serverError() || $e->response->status() === 429);
    }

    private function redact(string $message): string
    {
        return preg_replace('/([?&]key=)[^&\s]+/i', '$1***', $message);
    }
}
