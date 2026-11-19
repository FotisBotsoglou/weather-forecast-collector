<?php

namespace App\Http\Controllers;

use App\Enums\RunStatus;
use App\Http\Requests\ForecastQueryRequest;
use App\Http\Resources\ForecastSnapshotResource;
use App\Http\Resources\LocationResource;
use App\Models\DailyForecast;
use App\Models\ForecastRun;
use App\Models\Location;
use App\Weather\ProviderRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ForecastController extends Controller
{
    public function daily(ForecastQueryRequest $request, Location $location): AnonymousResourceCollection
    {
        $runs = $this->snapshots($request, $location)
            ->with(['dailyForecasts' => fn ($query) => $query
                ->when($request->validated('from'), fn ($q, $from) => $q->where('forecast_date', '>=', $from))
                ->when($request->validated('to'), fn ($q, $to) => $q->where('forecast_date', '<=', $to))
                ->orderBy('forecast_date')])
            ->get();

        return ForecastSnapshotResource::collection($runs)
            ->additional(['location' => new LocationResource($location)]);
    }

    public function hourly(ForecastQueryRequest $request, Location $location): AnonymousResourceCollection
    {
        // from/to are calendar days in the location's timezone; values are stored in UTC.
        $from = $request->validated('from')
            ? CarbonImmutable::parse($request->validated('from'), $location->timezone)->startOfDay()->utc()
            : null;
        $until = $request->validated('to')
            ? CarbonImmutable::parse($request->validated('to'), $location->timezone)->addDay()->startOfDay()->utc()
            : null;

        $runs = $this->snapshots($request, $location)
            ->with(['hourlyForecasts' => fn ($query) => $query
                ->when($from, fn ($q) => $q->where('valid_at', '>=', $from))
                ->when($until, fn ($q) => $q->where('valid_at', '<', $until))
                ->orderBy('valid_at')])
            ->get();

        return ForecastSnapshotResource::collection($runs)
            ->additional(['location' => new LocationResource($location)]);
    }

    public function revisions(Request $request, Location $location, string $date, ProviderRegistry $registry): JsonResponse
    {
        validator(
            ['date' => $date] + $request->only('provider'),
            ['date' => ['date_format:Y-m-d'], 'provider' => ['nullable', 'string', Rule::in($registry->known())]],
        )->validate();

        $rows = DailyForecast::query()
            ->join('forecast_runs', 'forecast_runs.id', '=', 'daily_forecasts.forecast_run_id')
            ->where('forecast_runs.location_id', $location->id)
            ->where('forecast_runs.status', RunStatus::Succeeded)
            ->where('daily_forecasts.forecast_date', $date)
            ->when($request->query('provider'), fn ($q, $provider) => $q->where('forecast_runs.provider', $provider))
            ->orderBy('forecast_runs.provider')
            ->orderBy('forecast_runs.issue_date')
            ->get(['daily_forecasts.*', 'forecast_runs.provider', 'forecast_runs.issue_date']);

        $target = CarbonImmutable::parse($date);

        $data = $rows->groupBy('provider')->map(fn ($revisions, $provider) => [
            'provider' => $provider,
            'revisions' => $revisions->map(fn (DailyForecast $row) => [
                'issue_date' => $row->issue_date,
                'lead_days' => (int) CarbonImmutable::parse($row->issue_date)->diffInDays($target),
                'temperature_min_c' => $row->temperature_min_c,
                'temperature_max_c' => $row->temperature_max_c,
                'temperature_mean_c' => $row->temperature_mean_c,
                'precipitation_mm' => $row->precipitation_mm,
            ])->values(),
        ])->values();

        return response()->json([
            'location' => new LocationResource($location),
            'date' => $date,
            'data' => $data,
        ]);
    }

    /**
     * Successful runs for the location: either the ones issued on the requested
     * day, or the most recent one per provider.
     */
    private function snapshots(ForecastQueryRequest $request, Location $location): Builder
    {
        return ForecastRun::query()
            ->where('location_id', $location->id)
            ->where('status', RunStatus::Succeeded)
            ->when($request->validated('provider'), fn ($q, $provider) => $q->where('provider', $provider))
            ->when(
                $request->validated('issued_on'),
                fn ($q, $issuedOn) => $q->where('issue_date', $issuedOn),
                fn ($q) => $q->where('issue_date', fn (QueryBuilder $latest) => $latest
                    ->selectRaw('max(issue_date)')
                    ->from('forecast_runs as latest')
                    ->whereColumn('latest.location_id', 'forecast_runs.location_id')
                    ->whereColumn('latest.provider', 'forecast_runs.provider')
                    ->where('latest.status', RunStatus::Succeeded)),
            )
            ->orderBy('provider');
    }
}
