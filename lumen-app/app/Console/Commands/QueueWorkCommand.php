<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Symfony\Component\Console\Input\InputOption;

class QueueWorkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:work
                            {--connection= : The name of the queue connection to work}
                            {--queue= : The names of the queues to work}
                            {--once : Only process the next job on the queue}
                            {--stop-when-empty : Stop when the queue is empty}
                            {--delay=0 : The number of seconds to delay failed jobs}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=1 : Number of times to attempt a job before logging it failed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start processing jobs on the queue as a daemon';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = $this->option('connection') ?: $this->laravel['config']['queue.default'];

        $queue = $this->option('queue') ?: null;

        $options = new WorkerOptions(
            $this->option('delay'),
            $this->option('memory'),
            $this->option('timeout'),
            $this->option('sleep'),
            $this->option('tries'),
            $this->option('force'),
            $this->option('once'),
            $this->option('stop-when-empty')
        );

        // Create worker with manual dependency injection to avoid maintenance mode issues
        $worker = new Worker(
            $this->laravel['queue'],
            $this->laravel['events'],
            $this->laravel['Illuminate\Contracts\Debug\ExceptionHandler'],
            function () {
                return false; // Always return false - not in maintenance mode
            }
        );

        if ($this->option('once')) {
            $worker->runNextJob($connection, $queue, $options);
        } else {
            $worker->daemon($connection, $queue, $options);
        }

        return Command::SUCCESS;
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['connection', null, InputOption::VALUE_OPTIONAL, 'The queue instance to work'],
            ['queue', null, InputOption::VALUE_OPTIONAL, 'The queue instance to work'],
            ['once', null, InputOption::VALUE_NONE, 'Only process the next job on the queue'],
            ['stop-when-empty', null, InputOption::VALUE_NONE, 'Stop when the queue is empty'],
            ['delay', null, InputOption::VALUE_OPTIONAL, 'The number of seconds to delay failed jobs', 0],
            ['force', null, InputOption::VALUE_NONE, 'Force the worker to run even in maintenance mode'],
            ['memory', null, InputOption::VALUE_OPTIONAL, 'The memory limit in megabytes', 128],
            ['sleep', null, InputOption::VALUE_OPTIONAL, 'Number of seconds to sleep when no job is available', 3],
            ['timeout', null, InputOption::VALUE_OPTIONAL, 'The number of seconds a child process can run', 60],
            ['tries', null, InputOption::VALUE_OPTIONAL, 'Number of times to attempt a job before logging it failed', 1],
        ];
    }
}
