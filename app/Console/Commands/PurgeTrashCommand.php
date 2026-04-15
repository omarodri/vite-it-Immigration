<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Companion;
use App\Models\Document;
use App\Models\ImmigrationCase;
use Illuminate\Console\Command;

class PurgeTrashCommand extends Command
{
    protected $signature = 'trash:purge
                            {--days=30 : Purge items older than N days (0 = all)}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Permanently delete items in trash older than N days';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = $days > 0 ? now()->subDays($days) : null;

        $models = [
            'Documents'  => Document::class,
            'Companions' => Companion::class,
            'Cases'      => ImmigrationCase::class,
            'Clients'    => Client::class,
        ];

        $total = 0;

        foreach ($models as $label => $modelClass) {
            $query = $modelClass::onlyTrashed();
            if ($cutoff) {
                $query->where('deleted_at', '<=', $cutoff);
            }
            $count = $query->count();
            $total += $count;

            $this->line("  {$label}: {$count} items");

            if (! $dryRun && $count > 0) {
                $query->get()->each->forceDelete();
            }
        }

        if ($dryRun) {
            $this->info("[DRY RUN] Would permanently delete {$total} items.");
        } else {
            $this->info("Permanently deleted {$total} items from trash.");
        }

        return Command::SUCCESS;
    }
}
