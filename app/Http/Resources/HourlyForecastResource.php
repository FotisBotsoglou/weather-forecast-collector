<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HourlyForecastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'time' => $this->valid_at->toIso8601ZuluString(),
            'temperature_c' => $this->temperature_c,
            'precipitation_mm' => $this->precipitation_mm,
        ];
    }
}
