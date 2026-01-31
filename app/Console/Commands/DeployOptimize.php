<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeployOptimize extends Command
{
    protected $signature = 'deploy:optimize {--seed : Run database seeders}';

    protected $description = 'Run all production optimization commands';

    public function handle(): int
    {
        $this->info('Running deployment optimizations...');

        $this->call('migrate', ['--force' => true]);
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');
        $this->call('event:cache');

        if ($this->option('seed')) {
            $this->call('db:seed', ['--force' => true]);
        }

        $this->info('Deployment optimizations complete.');

        return Command::SUCCESS;
    }
}
