<?php

namespace App\Http\Controllers;

use App\Models\BotInstance;
use App\Services\EvolutionApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstanceController extends Controller
{
    public function index()
    {
        $instances = BotInstance::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('instances.index', compact('instances'));
    }

    public function store(Request $request, EvolutionApiService $evolution)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                'regex:/^[-a-zA-Z0-9_]+$/',
            ],
        ], [
            'name.required' => 'Informe o nome da instância.',
            'name.regex' => 'O nome só pode conter letras, números, hífen (-) e underscore (_).',
        ]);

        $name = trim($request->input('name'));
        $slug = Str::slug($name) ?: Str::random(8);
        $instanceName = 'user-' . auth()->id() . '-' . $slug;

        if (BotInstance::where('instance_name', $instanceName)->exists()) {
            return back()->withErrors(['name' => 'Já existe uma instância com esse nome.']);
        }

        $baseUrl = config('services.evolution.webhook_url') ?: config('app.url');
        $webhookUrl = rtrim($baseUrl, '/') . '/api/webhooks/evolution';
        $events = ['QRCODE_UPDATED', 'CONNECTION_UPDATE', 'MESSAGES_UPSERT'];

        try {
            $createResponse = $evolution->createInstance($instanceName, $webhookUrl, $events);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['name' => 'Não foi possível criar na Evolution API: ' . $e->getMessage()]);
        }

        $qrcodeBase64 = $this->extractQrcodeFromEvolutionResponse($createResponse);

        try {
            $evolution->setWebhook($instanceName, $webhookUrl, $events);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Evolution setWebhook após criar instância falhou', [
                'instance' => $instanceName,
                'message' => $e->getMessage(),
            ]);
        }

        $instance = BotInstance::create([
            'user_id' => auth()->id(),
            'instance_name' => $instanceName,
            'status' => 'disconnected',
            'qrcode' => $qrcodeBase64,
            'qrcode_generated_at' => $qrcodeBase64 ? now() : null,
        ]);

        if (! $qrcodeBase64) {
            $this->tryFetchQrFromEvolution($instance, $evolution);
        }

        return redirect()->route('instances.index')->with('success', 'Instância criada. Escaneie o QR Code.');
    }

    public function destroy(BotInstance $instance, EvolutionApiService $evolution)
    {
        if ($instance->user_id !== auth()->id()) {
            abort(403);
        }

        // Tenta remover na Evolution (404 = já foi apagada; não bloqueia)
        try {
            $evolution->delete($instance->instance_name);
        } catch (\Throwable $e) {
            // ignora: instância pode já ter sido apagada na Evolution
        }

        // Sempre remove no Laravel para não ficar registro fantasma
        $instance->delete();

        return redirect()->route('instances.index')->with('success', 'Instância removida.');
    }

    /**
     * Extrai o base64 do QR da resposta do POST /instance/create (Evolution pode devolver qrcode.base64).
     */
    private function extractQrcodeFromEvolutionResponse(array $data): ?string
    {
        $qrcodeObj = $data['qrcode'] ?? null;
        $qrcode = (is_array($qrcodeObj) ? ($qrcodeObj['base64'] ?? $qrcodeObj['image'] ?? null) : $qrcodeObj)
            ?? $data['base64'] ?? $data['base64Image'] ?? $data['qrCode'] ?? null;
        if (is_array($qrcode)) {
            $qrcode = $qrcode['base64'] ?? $qrcode['image'] ?? null;
        }
        if (! is_string($qrcode) || strlen($qrcode) < 50 || strpos($qrcode, '@') !== false) {
            return null;
        }
        if (preg_match('#^data:image/[^;]+;base64,(.+)$#', $qrcode, $m)) {
            $qrcode = $m[1];
        }
        return preg_match('/^[A-Za-z0-9+\/=]+$/', $qrcode) ? $qrcode : null;
    }

    /**
     * Polling: retorna apenas o que está no banco (sem chamar a Evolution).
     * Formato esperado pelo frontend: qrcode.base64 ou status aguardando.
     */
    public function evolutionConnectRaw(BotInstance $instance)
    {
        if ($instance->user_id !== auth()->id()) {
            abort(403);
        }

        if ($instance->qrcode) {
            return response()->json(['qrcode' => ['base64' => $instance->qrcode]]);
        }

        if ($instance->pairing_code) {
            return response()->json([
                'pairingCode' => $instance->pairing_code,
                'pairing_code' => $instance->pairing_code,
            ]);
        }

        return response()->json(['status' => 'aguardando']);
    }

    /**
     * Salva QR ou pairing code encontrado pelo frontend (para não depender do backend adivinhar a chave).
     */
    public function saveQrFromFrontend(Request $request, BotInstance $instance)
    {
        if ($instance->user_id !== auth()->id()) {
            abort(403);
        }
        $qrcode = $request->input('qrcode');
        $pairingCode = $request->input('pairing_code');
        $updates = [];
        if (is_string($qrcode) && strlen($qrcode) > 50 && strpos($qrcode, '@') === false) {
            if (preg_match('#^data:image/[^;]+;base64,(.+)$#', $qrcode, $m)) {
                $qrcode = $m[1];
            }
            if (preg_match('/^[A-Za-z0-9+\/=]+$/', $qrcode)) {
                $updates['qrcode'] = $qrcode;
                $updates['qrcode_generated_at'] = now();
            }
        }
        if (is_string($pairingCode) && strlen($pairingCode) >= 4 && strlen($pairingCode) <= 32) {
            $updates['pairing_code'] = $pairingCode;
        }
        if (! empty($updates)) {
            $instance->update($updates);
        }
        return response()->json(['ok' => true]);
    }

    /**
     * Fallback: busca QR na Evolution API quando o webhook não entrega.
     * Retorna JSON para o frontend poder fazer polling.
     */
    public function refreshQr(BotInstance $instance, EvolutionApiService $evolution)
    {
        if ($instance->user_id !== auth()->id()) {
            abort(403);
        }

        if ($instance->qrcode) {
            return response()->json(['qrcode' => $instance->qrcode, 'pairing_code' => null]);
        }

        try {
            $data = $evolution->fetchInstanceConnect($instance->instance_name);
        } catch (\Throwable $e) {
            return response()->json(['qrcode' => null]);
        }

        if (! is_array($data)) {
            return response()->json(['qrcode' => null, 'pairing_code' => $instance->pairing_code]);
        }

        // Evolution v2 / Atendai: base64 direto ou em qrcode.base64 (forçar leitura do QR)
        $base64 = $data['base64'] ?? $data['qrcode']['base64'] ?? $data['qrcode']['image'] ?? null;
        if (! $base64 && isset($data['qrcode']) && is_array($data['qrcode'])) {
            $base64 = $data['qrcode']['base64'] ?? $data['qrcode']['image'] ?? null;
        }
        $flat = array_merge($data, is_array($data['data'] ?? null) ? $data['data'] : []);
        $qrcode = $base64 ?: ($flat['qrcode'] ?? $flat['base64'] ?? $flat['base64Image'] ?? $flat['qrCode'] ?? $flat['code'] ?? null);
        if (is_array($qrcode)) {
            $qrcode = $qrcode['base64'] ?? $qrcode['image'] ?? null;
        }
        if ($qrcode && is_string($qrcode) && strlen($qrcode) > 50 && strpos($qrcode, '@') === false) {
            if (preg_match('#^data:image/[^;]+;base64,(.+)$#', $qrcode, $m)) {
                $qrcode = $m[1];
            }
            if (preg_match('/^[A-Za-z0-9+\/=]+$/', $qrcode)) {
                $instance->update([
                    'qrcode' => $qrcode,
                    'qrcode_generated_at' => now(),
                    'status' => 'disconnected',
                ]);
                return response()->json(['qrcode' => $qrcode, 'pairing_code' => null]);
            }
        }

        $pairingCode = $data['pairingCode'] ?? $flat['pairingCode'] ?? $flat['pairing_code'] ?? null;
        if ($pairingCode && is_string($pairingCode) && strlen($pairingCode) >= 4 && strlen($pairingCode) <= 32) {
            $instance->update(['pairing_code' => $pairingCode]);
            return response()->json(['qrcode' => null, 'pairing_code' => $pairingCode]);
        }

        return response()->json(['qrcode' => null, 'pairing_code' => $instance->pairing_code]);
    }

    /**
     * Tenta obter o QR ou código de pareamento da Evolution logo após criar a instância.
     * Várias tentativas com delay (Evolution pode demorar a gerar).
     */
    private function tryFetchQrFromEvolution(BotInstance $instance, EvolutionApiService $evolution): void
    {
        $delays = [2, 3, 4, 5, 6, 7];
        foreach ($delays as $i => $sec) {
            sleep($sec);
            try {
                $data = $evolution->fetchInstanceConnect($instance->instance_name);
            } catch (\Throwable $e) {
                continue;
            }
            if (! is_array($data)) {
                continue;
            }
            $base64 = $data['base64'] ?? $data['qrcode']['base64'] ?? $data['qrcode']['image'] ?? null;
            if (! $base64 && isset($data['qrcode']) && is_array($data['qrcode'])) {
                $base64 = $data['qrcode']['base64'] ?? $data['qrcode']['image'] ?? null;
            }
            $flat = array_merge($data, is_array($data['data'] ?? null) ? $data['data'] : []);
            $qrcode = $base64 ?: ($flat['qrcode'] ?? $flat['base64'] ?? $flat['base64Image'] ?? $flat['qrCode'] ?? $flat['code'] ?? null);
            if (is_array($qrcode)) {
                $qrcode = $qrcode['base64'] ?? $qrcode['image'] ?? null;
            }
            if ($qrcode && is_string($qrcode) && strlen($qrcode) > 50 && strpos($qrcode, '@') === false) {
                if (preg_match('#^data:image/[^;]+;base64,(.+)$#', $qrcode, $m)) {
                    $qrcode = $m[1];
                }
                if (preg_match('/^[A-Za-z0-9+\/=]+$/', $qrcode)) {
                    $instance->update([
                        'qrcode' => $qrcode,
                        'qrcode_generated_at' => now(),
                        'status' => 'disconnected',
                    ]);
                    return;
                }
            }
            $pairingCode = $data['pairingCode'] ?? $flat['pairingCode'] ?? $flat['pairing_code'] ?? null;
            if ($pairingCode && is_string($pairingCode) && strlen($pairingCode) >= 4 && strlen($pairingCode) <= 32) {
                $instance->update(['pairing_code' => $pairingCode]);
            }
        }
    }
}
