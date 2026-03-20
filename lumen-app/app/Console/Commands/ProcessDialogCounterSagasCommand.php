<?php

namespace App\Console\Commands;

use App\Services\DialogCounterProjectionService;
use Illuminate\Console\Command;

class ProcessDialogCounterSagasCommand extends Command
{
    protected $signature = 'dialog:saga:process {--limit=100 : Max number of saga events to process}';

    protected $description = 'Apply pending dialog counter saga events to the unread counters projection';

    public function handle(DialogCounterProjectionService $projectionService): int
    {
        $result = $projectionService->processPendingSagas((int) $this->option('limit'));

        $this->info(sprintf(
            'Claimed: %d, processed: %d, failed: %d',
            $result['claimed'],
            $result['processed'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
