<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->date('issue_date');
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestampTz('fetched_at')->nullable();
            $table->timestampsTz();

            $table->unique(['location_id', 'provider', 'issue_date']);
            $table->index(['status', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_runs');
    }
};
