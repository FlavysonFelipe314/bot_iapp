<?php

namespace App\Console\Commands;

use App\Models\BotInstance;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;

class EvolutionCleanupOrphanInstances extends Command
{
    protected $signature = 'evolution:cleanup-orphans
                            {--dry-run : Só listar, não apagar}';

    protected $description = 'Remove na Evolution API as instâncias que não existem mais no Laravel (para parar webhooks).';

    public function handle(EvolutionApiService $evolution): int
    {
        $dryRun = $this->option('dry-run');
        $ourNames = BotInstance::pluck('instance_name')->all();

        $list = $evolution->fetchInstances();
        if (empty($list)) {
            $this->info('Nenhuma instância na Evolution API.');
            return self::SUCCESS;
        }

        $toDelete = [];
        foreach ($list as $item) {
            $name = is_array($item) ? ($item['instanceName'] ?? $item['name'] ?? null) : $item;
            if ($name && ! in_array($name, $ourNames, true)) {
                $toDelete[] = $name;
            }
        }

        if (empty($toDelete)) {
            $this->info('Nenhuma instância órfã (todas existem no Laravel).');
            return self::SUCCESS;
        }

        $this->warn('Instâncias na Evolution que não estão no Laravel: ' . implode(', ', $toDelete));
        if ($dryRun) {
            $this->info('Dry-run: nenhuma exclusão feita. Rode sem --dry-run para remover.');
            return self::SUCCESS;
        }

        foreach ($toDelete as $name) {
            try {
                $evolution->delete($name);
                $this->line("Removida na Evolution: {$name}");
            } catch (\Throwable $e) {
                $this->error("Falha ao remover {$name}: " . $e->getMessage());
            }
        }

        $this->info('Limpeza concluída.');
        return self::SUCCESS;
    }
}
