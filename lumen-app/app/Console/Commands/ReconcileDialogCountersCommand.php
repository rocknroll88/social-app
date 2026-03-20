<?php

namespace App\Console\Commands;

use App\Services\DialogCounterProjectionService;
use Illuminate\Console\Command;

class ReconcileDialogCountersCommand extends Command
{
    protected $signature = 'dialog:counters:reconcile
                            {--owner_user_id= : Reconcile a specific owner user}
                            {--limit=100 : Max number of owners to reconcile in one run}';

    protected $description = 'Rebuild dialog unread counters from dialog_messages and compensate failed sagas';

    public function handle(DialogCounterProjectionService $projectionService): int
    {
        $result = $projectionService->reconcile(
            $this->option('owner_user_id') ?: null,
            (int) $this->option('limit')
        );

        $this->info(sprintf(
            'Owners selected: %d, reconciled: %d',
            $result['owners'],
            $result['reconciled']
        ));

        return self::SUCCESS;
    }
}
