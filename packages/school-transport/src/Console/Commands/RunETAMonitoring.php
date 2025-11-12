<?php

namespace Fleetbase\SchoolTransportEngine\Console\Commands;

use Fleetbase\SchoolTransportEngine\Jobs\MonitorETAAndProximity;
use Illuminate\Console\Command;

class RunETAMonitoring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'school-transport:monitor-eta 
                          {--trip= : Specific trip UUID to monitor}
                          {--sync : Run synchronously instead of dispatching to queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run ETA and proximity monitoring for active trips (one-time execution)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tripId = $this->option('trip');
        $runSync = $this->option('sync');

        if ($tripId) {
            $this->info("Running ETA monitoring for specific trip: {$tripId}");
        } else {
            $this->info('Running ETA monitoring for all active trips...');
        }

        try {
            if ($runSync) {
                // Run synchronously for testing
                $this->info('Running monitoring job synchronously...');
                $job = new MonitorETAAndProximity();
                $job->handle();
                $this->info('✅ ETA monitoring completed successfully');
            } else {
                // Dispatch to queue
                $this->info('Dispatching monitoring job to queue...');
                MonitorETAAndProximity::dispatch();
                $this->info('✅ ETA monitoring job dispatched to queue');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ ETA monitoring failed: {$e->getMessage()}");
            $this->line("Stack trace:");
            $this->line($e->getTraceAsString());

            return 1;
        }
    }
}
