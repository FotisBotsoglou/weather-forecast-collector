<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForecastSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider,
            'issue_date' => $this->issue_date->toDateString(),
            'fetched_at' => $this->fetched_at?->toIso8601ZuluString(),
            'daily' => DailyForecastResource::collection($this->whenLoaded('dailyForecasts')),
            'hourly' => HourlyForecastResource::collection($this->whenLoaded('hourlyForecasts')),
        ];
    }
}
