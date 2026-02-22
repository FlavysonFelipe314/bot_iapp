<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionApiService
{
    private string $baseUrl;

    private string $apikey;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.evolution.url');
        $this->apikey = config('services.evolution.apikey');
        $this->timeout = config('services.evolution.timeout', 30);
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withHeaders(['apikey' => $this->apikey])
            ->baseUrl($this->baseUrl);
    }

    public function createInstance(string $instanceName, ?string $webhookUrl = null, array $events = []): array
    {
        $payload = [
            'instanceName' => $instanceName,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
        ];

        if ($webhookUrl && count($events) > 0) {
            $payload['webhook'] = [
                'url' => $webhookUrl,
                'byEvents' => false,
                'base64' => true,
                'webhookBase64' => true,
                'events' => $events,
            ];
        }

        $response = $this->client()->post('/instance/create', $payload);

        if (! $response->successful()) {
            Log::warning('Evolution createInstance failed', [
                'instance' => $instanceName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Falha ao criar instância: '.$response->body());
        }

        return $response->json();
    }

    public function setWebhook(string $instanceName, string $url, array $events): array
    {
        // webhookByEvents: true faz a Evolution enviar cada evento para URL/evento (ex: .../evolution/messages-upsert) com payload completo
        $webhook = [
            'url' => $url,
            'enabled' => true,
            'events' => $events,
            'webhookByEvents' => false,
            'webhookBase64' => true,
        ];
        $payload = ['webhook' => $webhook];

        $response = $this->client()->post("/webhook/set/{$instanceName}", $payload);

        if (! $response->successful()) {
            Log::warning('Evolution setWebhook failed', [
                'instance' => $instanceName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Falha ao configurar webhook');
        }

        return $response->json();
    }

    public function connectionState(string $instanceName): ?array
    {
        $response = $this->client()->get("/instance/connectionState/{$instanceName}");

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Gera/obtém QR de conexão (fallback quando o webhook não chega).
     * Retorna base64 do QR se a API devolver em algum campo.
     * Aceita: qrcode, base64, base64Image, pairingCode, pairing_code, code (se for base64 longo).
     */
    public function fetchInstanceConnect(string $instanceName): ?array
    {
        $response = $this->client()->get("/instance/connect/{$instanceName}");

        if ($response->status() === 404 || ! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (is_array($data) && config('app.debug')) {
            Log::debug('Evolution fetchInstanceConnect response keys', [
                'instance' => $instanceName,
                'keys' => array_keys($data),
            ]);
        }
        return $data;
    }

    public function sendText(string $instanceName, string $number, string $text): array
    {
        $number = preg_replace('/[^\d]/', '', $number);
        if (strlen($number) >= 10 && ! str_starts_with($number, '55')) {
            $number = '55' . $number;
        }

        $response = $this->client()->post("/message/sendText/{$instanceName}", [
            'number' => $number,
            'text' => $text,
        ]);

        if (! $response->successful()) {
            Log::warning('Evolution sendText failed', [
                'instance' => $instanceName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Falha ao enviar mensagem: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Envia áudio (voice note) para o WhatsApp. Base64 do áudio (ex.: MP3 do ElevenLabs).
     */
    public function sendAudio(string $instanceName, string $number, string $audioBase64): array
    {
        $number = preg_replace("/[^\\d]/", "", $number);
        if (strlen($number) >= 10 && ! str_starts_with($number, "55")) {
            $number = "55" . $number;
        }
        $response = $this->client()->post("/message/sendWhatsAppAudio/" . $instanceName, [
            "number" => $number,
            "audioMessage" => [
                "audio" => $audioBase64,
            ],
        ]);
        if (! $response->successful()) {
            Log::warning("Evolution sendAudio failed", [
                "instance" => $instanceName,
                "status" => $response->status(),
                "body" => $response->body(),
            ]);
            throw new \RuntimeException("Falha ao enviar áudio: " . $response->body());
        }
        return $response->json();
    }


    public function logout(string $instanceName): array
    {
        $response = $this->client()->delete("/instance/logout/{$instanceName}");
        return $response->json();
    }

    public function delete(string $instanceName): array
    {
        $response = $this->client()->delete("/instance/delete/{$instanceName}");
        return $response->json();
    }

    public function fetchInstances(): array
    {
        $response = $this->client()->get('/instance/fetchInstances');
        if (! $response->successful()) {
            return [];
        }
        $data = $response->json();
        return $data['instance'] ?? [];
    }
}
