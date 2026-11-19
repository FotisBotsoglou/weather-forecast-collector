<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyForecast extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'forecast_run_id',
        'forecast_date',
        'temperature_min_c',
        'temperature_max_c',
        'temperature_mean_c',
        'precipitation_mm',
    ];

    protected function casts(): array
    {
        return [
            'forecast_date' => 'date:Y-m-d',
            'temperature_min_c' => 'float',
            'temperature_max_c' => 'float',
            'temperature_mean_c' => 'float',
            'precipitation_mm' => 'float',
        ];
    }

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }
}
