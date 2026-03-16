<?php

namespace App\Console\Commands;

use App\Helpers\CaseCodeHelper;
use App\Models\ImmigrationCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegenerateCaseNumbers extends Command
{
    protected $signature = 'cases:regenerate-numbers
                            {--dry-run : Show the proposed changes without applying them}
                            {--tenant= : Restrict regeneration to a specific tenant_id}';

    protected $description = 'Regenerate all case numbers using the new nemonic format: YY-TYPE-LAST4-SEQUENCE';

    public function handle(): int
    {
        $dryRun   = $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $query = ImmigrationCase::with(['caseType', 'client'])
            ->orderBy('created_at');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $cases = $query->get();

        if ($cases->isEmpty()) {
            $this->warn('No se encontraron expedientes.');
            return self::SUCCESS;
        }

        $this->info("Procesando {$cases->count()} expedientes" . ($tenantId ? " del tenant {$tenantId}" : '') . '...');

        // Build new numbers preserving chronological order for sequence assignment.
        // The counter key is "YY-TYPE-SLUG" so all clients with the same slug
        // in the same year+type share a single consecutive — matching live behavior.
        $counters = [];
        $changes  = [];

        foreach ($cases as $case) {
            if (! $case->caseType || ! $case->client) {
                $this->warn("  ⚠  Expediente #{$case->id} omitido: tipo o cliente no encontrado.");
                continue;
            }

            $year2 = $case->created_at->format('y');
            $slug  = CaseCodeHelper::normalizeLastName($case->client->last_name);
            $key   = "{$year2}-{$case->caseType->code}-{$slug}";

            if (! isset($counters[$key])) {
                $counters[$key] = 1;
            }

            $newNumber = sprintf('%s-%04d', $key, $counters[$key]++);

            $changes[] = [
                'id'  => $case->id,
                'old' => $case->case_number,
                'new' => $newNumber,
            ];
        }

        if ($dryRun) {
            $this->table(['ID', 'Código anterior', 'Código nuevo'], $changes);
            $this->warn('--dry-run activo: no se aplicaron cambios.');
            return self::SUCCESS;
        }

        // Detect duplicate new numbers before writing (safety check)
        $newNumbers = array_column($changes, 'new');
        if (count($newNumbers) !== count(array_unique($newNumbers))) {
            $this->error('Se detectaron duplicados en los nuevos códigos. Operación cancelada.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $change) {
                ImmigrationCase::withTrashed()
                    ->where('id', $change['id'])
                    ->update(['case_number' => $change['new']]);
            }
        });

        $this->info("✅ {$cases->count()} códigos regenerados exitosamente.");

        if ($this->getOutput()->isVerbose()) {
            $this->table(['ID', 'Código anterior', 'Código nuevo'], $changes);
        }

        return self::SUCCESS;
    }
}
