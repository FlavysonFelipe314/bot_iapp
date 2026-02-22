<?php

namespace App\Console\Commands;

use App\Services\EvolutionApiService;
use Illuminate\Console\Command;

class EvolutionSetWebhookCommand extends Command
{
    protected $signature = 'evolution:set-webhook {instance : Nome da instância (ex: user-1-suporte)} {--url= : URL base (ex: http://host.docker.internal para porta 80)}';

    protected $description = 'Configura o webhook da Evolution API para uma instância existente (para receber MESSAGES_UPSERT)';

    public function handle(EvolutionApiService $evolution): int
    {
        $instanceName = $this->argument('instance');
        $baseUrl = $this->option('url') ?: config('services.evolution.webhook_url') ?: config('app.url');
        $webhookUrl = rtrim($baseUrl, '/') . '/api/webhooks/evolution';
        $events = ['QRCODE_UPDATED', 'CONNECTION_UPDATE', 'MESSAGES_UPSERT'];

        $this->info("Configurando webhook para {$instanceName}: {$webhookUrl}");

        try {
            $evolution->setWebhook($instanceName, $webhookUrl, $events);
            $this->info('Webhook configurado com sucesso. Envie uma mensagem para o número e verifique o Chat.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Falha: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
