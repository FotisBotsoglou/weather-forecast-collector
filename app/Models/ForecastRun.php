<?php

namespace App\Models;

use App\Enums\RunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForecastRun extends Model
{
    protected $fillable = ['location_id', 'provider', 'issue_date', 'status', 'attempts', 'error', 'fetched_at'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
            'status' => RunStatus::class,
            'attempts' => 'integer',
            'fetched_at' => 'immutable_datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function hourlyForecasts(): HasMany
    {
        return $this->hasMany(HourlyForecast::class);
    }

    public function dailyForecasts(): HasMany
    {
        return $this->hasMany(DailyForecast::class);
    }
}
