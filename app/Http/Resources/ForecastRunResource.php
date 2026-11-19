<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForecastRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'provider' => $this->provider,
            'issue_date' => $this->issue_date->toDateString(),
            'status' => $this->status,
            'attempts' => $this->attempts,
            'error' => $this->error,
            'fetched_at' => $this->fetched_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
