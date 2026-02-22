<?php

namespace App\Console\Commands;

use App\Services\EvolutionApiService;
use Illuminate\Console\Command;

class EvolutionDebugConnect extends Command
{
    protected $signature = 'evolution:debug-connect {instance : Nome da instância (ex: user-1-dddddddddddd)}';

    protected $description = 'Chama GET /instance/connect e mostra as chaves do JSON (para saber qual usar no Laravel).';

    public function handle(EvolutionApiService $evolution): int
    {
        $instanceName = $this->argument('instance');
        $data = $evolution->fetchInstanceConnect($instanceName);

        if (! is_array($data)) {
            $this->error('Resposta vazia ou não-JSON. Verifique EVOLUTION_API_URL e apikey.');
            return self::FAILURE;
        }

        $this->info('Chaves no primeiro nível: ' . implode(', ', array_keys($data)));

        $hasBase64 = isset($data['base64']) && is_string($data['base64']);
        $hasQrcode = isset($data['qrcode']);
        $qrcodeKeys = $hasQrcode && is_array($data['qrcode']) ? array_keys($data['qrcode']) : [];
        $hasPairing = isset($data['pairingCode']) && is_string($data['pairingCode']);

        $this->table(
            ['Campo', 'Presente', 'Tipo/Valor'],
            [
                ['base64', $hasBase64 ? 'Sim' : 'Não', $hasBase64 ? 'string (' . strlen($data['base64']) . ' chars)' : '-'],
                ['qrcode', $hasQrcode ? 'Sim' : 'Não', $hasQrcode ? (is_array($data['qrcode']) ? 'array [' . implode(', ', $qrcodeKeys) . ']' : gettype($data['qrcode'])) : '-'],
                ['pairingCode', $hasPairing ? 'Sim' : 'Não', $hasPairing ? $data['pairingCode'] : '-'],
            ]
        );

        if ($hasQrcode && is_array($data['qrcode']) && isset($data['qrcode']['base64'])) {
            $this->info('qrcode.base64 existe: Sim (' . strlen($data['qrcode']['base64']) . ' chars)');
        }

        $this->newLine();
        $this->comment('Cole o JSON completo (ou esta saída) para ajustar o código Laravel.');
        return self::SUCCESS;
    }
}
