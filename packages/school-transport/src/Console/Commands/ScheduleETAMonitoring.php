<?php

namespace Fleetbase\SchoolTransportEngine\Console\Commands;

use Fleetbase\SchoolTransportEngine\Jobs\MonitorETAAndProximity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleETAMonitoring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'school-transport:schedule-eta-monitoring 
                          {--interval=30 : Interval in seconds between monitoring runs}
                          {--queue= : Queue to dispatch job to}
                          {--delay=0 : Initial delay in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Schedule continuous ETA and proximity monitoring for active school transport trips';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $interval = (int) $this->option('interval');
        $queue = $this->option('queue') ?: 'default';
        $delay = (int) $this->option('delay');

        $this->info('Starting ETA monitoring scheduler...');
        $this->info("Interval: {$interval} seconds");
        $this->info("Queue: {$queue}");
        $this->info("Initial delay: {$delay} seconds");

        // Initial delay if specified
        if ($delay > 0) {
            $this->info("Waiting {$delay} seconds before starting...");
            sleep($delay);
        }

        // Start the monitoring loop
        $jobCount = 0;

        while (true) {
            try {
                $jobCount++;
                $this->line("Dispatching ETA monitoring job #{$jobCount}...");

                // Dispatch the monitoring job
                MonitorETAAndProximity::dispatch()
                    ->onQueue($queue)
                    ->delay(now());

                Log::info("ETA monitoring job dispatched", [
                    'job_number' => $jobCount,
                    'queue' => $queue,
                    'scheduled_at' => now()->toISOString()
                ]);

                // Wait for the specified interval
                sleep($interval);
            } catch (\Exception $e) {
                $this->error("Error dispatching ETA monitoring job: {$e->getMessage()}");
                Log::error('ETA monitoring scheduler error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Wait a bit before retrying
                sleep(30);
            }

            // Check for signals to stop (Ctrl+C)
            if ($this->shouldStop()) {
                break;
            }
        }

        $this->info('ETA monitoring scheduler stopped.');
        return 0;
    }

    /**
     * Check if the command should stop
     *
     * @return bool
     */
    protected function shouldStop(): bool
    {
        // This is a basic implementation - in production you might want
        // to use proper signal handling or a more sophisticated check
        return false;
    }
}
