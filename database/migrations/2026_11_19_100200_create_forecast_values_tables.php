<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hourly_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_run_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('valid_at');
            $table->decimal('temperature_c', 5, 2)->nullable();
            $table->decimal('precipitation_mm', 7, 2)->nullable();

            $table->unique(['forecast_run_id', 'valid_at']);
        });

        Schema::create('daily_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_run_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date');
            $table->decimal('temperature_min_c', 5, 2)->nullable();
            $table->decimal('temperature_max_c', 5, 2)->nullable();
            $table->decimal('temperature_mean_c', 5, 2)->nullable();
            $table->decimal('precipitation_mm', 7, 2)->nullable();

            $table->unique(['forecast_run_id', 'forecast_date']);
            $table->index('forecast_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_forecasts');
        Schema::dropIfExists('hourly_forecasts');
    }
};
