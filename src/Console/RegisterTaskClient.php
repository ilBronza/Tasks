<?php

namespace IlBronza\Tasks\Console;

use IlBronza\Tasks\Sync\TaskSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterTaskClient extends Command
{
    protected $signature = 'tasks:register-client {name} {--rotate : Replace the credential for this installation}';
    protected $description = 'Register a client installation on the Tasks hub';

    public function handle(): int
    {
        if (!TaskSync::hub()) {
            $this->error('Run this command on the Tasks hub.');
            return self::FAILURE;
        }

        $name = trim($this->argument('name'));
        if ($name === '' || mb_strlen($name) > 255) {
            $this->error('Enter an installation name of 1–255 characters.');
            return self::FAILURE;
        }
        $installations = DB::table('tasks__sync_installations');
        $peer = $installations->where('name', $name)->first();
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        if ($peer) {
            if (!$this->option('rotate')) {
                $this->error('Installation already registered. Use --rotate to replace its token.');
                return self::FAILURE;
            }
            $id = $peer->id;
            $installations->update(['token_hash' => $hash]);
        } else {
            $id = (string) Str::uuid();
            $installations->insert([
                'id' => $id,
                'name' => $name,
                'token_hash' => $hash,
            ]);
        }

        $this->line('TASKS_SYNC_INSTALLATION=' . $id);
        $this->line('TASKS_SYNC_TOKEN=' . $token);
        return self::SUCCESS;
    }
}
