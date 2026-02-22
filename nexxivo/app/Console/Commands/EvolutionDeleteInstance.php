<?php

namespace App\Console\Commands;

use App\Services\EvolutionApiService;
use Illuminate\Console\Command;

class EvolutionDeleteInstance extends Command
{
    protected $signature = 'evolution:delete-instance {name : Nome da instância (ex: user-1-teste)}';

    protected $description = 'Remove uma instância na Evolution API (use se travar "Já existe uma instância com esse nome").';

    public function handle(EvolutionApiService $evolution): int
    {
        $name = $this->argument('name');
        try {
            $evolution->delete($name);
            $this->info("Instância {$name} removida na Evolution.");
        } catch (\Throwable $e) {
            $this->error("Falha: " . $e->getMessage());
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
