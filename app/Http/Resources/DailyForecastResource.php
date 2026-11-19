<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyForecastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->forecast_date->toDateString(),
            'temperature_min_c' => $this->temperature_min_c,
            'temperature_max_c' => $this->temperature_max_c,
            'temperature_mean_c' => $this->temperature_mean_c,
            'precipitation_mm' => $this->precipitation_mm,
        ];
    }
}
