<?php

namespace IlBronza\Tasks\Console;

use IlBronza\Tasks\Sync\ClientSynchronizer;
use Illuminate\Console\Command;

class SyncTasks extends Command
{
    protected $signature = 'tasks:sync';
    protected $description = 'Send pending tasks and shared messages, then receive portal changes';

    public function handle(ClientSynchronizer $sync): int
    {
        $result = $sync->run();
        foreach ($result['errors'] as $error)
            $this->error($error);
        $this->info("Sent {$result['sent']}, received {$result['received']}.");
        return $result['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
