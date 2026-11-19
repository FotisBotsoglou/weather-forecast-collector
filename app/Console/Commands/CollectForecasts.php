<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Weather\CollectOutcome;
use App\Weather\CollectResult;
use App\Weather\ForecastCollector;
use App\Weather\ProviderRegistry;
use Illuminate\Console\Command;
use Throwable;

class CollectForecasts extends Command
{
    protected $signature = 'forecasts:collect
        {--provider=* : Only collect from these providers}
        {--location=* : Only collect for these location ids}
        {--force : Fetch again even if today\'s forecast is already stored}';

    protected $description = 'Fetch and store today\'s forecasts for all active locations';

    public function handle(ForecastCollector $collector, ProviderRegistry $registry): int
    {
        $providers = $this->option('provider') ?: $registry->enabled();
        $unknown = array_diff($providers, $registry->known());

        if ($unknown !== []) {
            $this->error('Unknown provider(s): '.implode(', ', $unknown));

            return self::INVALID;
        }

        $locationIds = $this->option('location');

        if (array_filter($locationIds, fn ($id) => ! preg_match('/^[0-9]{1,18}$/', $id)) !== []) {
            $this->error('Location ids must be numeric.');

            return self::INVALID;
        }

        $locations = Location::query()
            ->active()
            ->when($locationIds, fn ($query, $ids) => $query->whereKey($ids))
            ->orderBy('id')
            ->get();

        if ($locations->isEmpty()) {
            $this->warn('No active locations to collect.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($locations as $location) {
            foreach ($providers as $provider) {
                try {
                    $result = $collector->collect($location, $provider, (bool) $this->option('force'));
                } catch (Throwable $e) {
                    report($e);
                    $result = new CollectResult($provider, CollectOutcome::Failed, $e->getMessage());
                }

                $failed = $failed || $result->outcome === CollectOutcome::Failed;

                $line = sprintf('%s / %s: %s (%s)', $location->name, $provider, $result->outcome->value, $result->message);

                match ($result->outcome) {
                    CollectOutcome::Collected => $this->info($line),
                    CollectOutcome::Skipped => $this->line($line),
                    CollectOutcome::Failed => $this->error($line),
                };
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
