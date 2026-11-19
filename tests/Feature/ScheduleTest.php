<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public static function times(): array
    {
        return [
            'before the window' => ['05:00', false],
            'start of the window' => ['06:00', true],
            'end of the window' => ['12:00', true],
            'after the window' => ['13:00', false],
            'not on the hour' => ['08:30', false],
        ];
    }

    #[DataProvider('times')]
    public function test_collection_runs_hourly_within_the_morning_window(string $time, bool $expected): void
    {
        // The window is evaluated against the time the schedule is defined, as
        // happens on every schedule:run, so the app is booted at the given time.
        $this->travelTo(CarbonImmutable::parse("2026-11-19 {$time}", 'Europe/Athens'));
        $this->refreshApplication();
        $this->app->make(Kernel::class)->bootstrap();

        $event = collect($this->app->make(Schedule::class)->events())
            ->sole(fn (Event $event) => str_contains($event->command, 'forecasts:collect'));

        $this->assertSame('0 * * * *', $event->expression);
        $this->assertSame('Europe/Athens', $event->timezone);
        $this->assertSame($expected, $event->isDue($this->app) && $event->filtersPass($this->app));
    }
}
