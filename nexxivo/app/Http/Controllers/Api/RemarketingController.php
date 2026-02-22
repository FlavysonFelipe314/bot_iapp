<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotInstance;
use App\Models\Conversation;
use App\Services\EvolutionApiService;
use App\Services\ElevenLabsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RemarketingController extends Controller
{
    public function __construct(
        private ElevenLabsService $elevenLabsService,
        private EvolutionApiService $evolution
    ) {}

    public function send(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|min:1',
            'target' => 'required|string|in:all,novo,em_atendimento,aguardando,fechado,selected',
            'contacts' => 'nullable|array',
            'send_as_audio' => 'nullable|boolean',
            'voice_id' => 'nullable|string',
        ]);

        if (empty(trim($validated['message']))) {
            return response()->json([
                'success' => false,
                'message' => 'A mensagem não pode estar vazia',
            ], 400);
        }

        $instanceNames = auth()->check() ? BotInstance::where('user_id', auth()->id())->pluck('instance_name')->toArray() : [];

        $conversations = collect();
        if ($validated['target'] === 'all') {
            $conversations = Conversation::where('is_archived', false)
                ->when(count($instanceNames) > 0, fn ($q) => $q->whereIn('instance_name', $instanceNames))
                ->get();
        } elseif ($validated['target'] === 'selected') {
            if (empty($validated['contacts'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum contato selecionado',
                ], 400);
            }
            $conversations = Conversation::whereIn('contact', $validated['contacts'])
                ->where('is_archived', false)
                ->when(count($instanceNames) > 0, fn ($q) => $q->whereIn('instance_name', $instanceNames))
                ->get();
        } else {
            $conversations = Conversation::where('kanban_status', $validated['target'])
                ->where('is_archived', false)
                ->when(count($instanceNames) > 0, fn ($q) => $q->whereIn('instance_name', $instanceNames))
                ->get();
        }

        if ($conversations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhum contato encontrado para enviar mensagem',
            ], 400);
        }

        $totalContacts = $conversations->count();
        $botUrl = config('services.bot.url', env('BOT_URL', 'http://localhost:3001'));
        $useEvolution = (bool) config('services.evolution.apikey');
        $sendAsAudio = $validated['send_as_audio'] ?? false;

        $audioBase64 = null;
        $audioFormat = null;
        if ($sendAsAudio) {
            try {
                $voiceId = $validated['voice_id'] ?? null;
                $audioResult = $this->elevenLabsService->textToSpeech($validated['message'], $voiceId);
                $audioBase64 = $audioResult['audio'];
                $audioFormat = $audioResult['format'] ?? 'ogg_opus';
            } catch (\Exception $e) {
                Log::error('Erro ao gerar áudio remarketing', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao gerar áudio: ' . $e->getMessage(),
                ], 500);
            }
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($conversations as $conversation) {
            $contact = $conversation->contact;
            $instanceName = $conversation->instance_name;
            $isEvolutionInstance = $useEvolution && BotInstance::where('instance_name', $instanceName)->exists();

            try {
                if ($isEvolutionInstance) {
                    $this->evolution->sendText($instanceName, $contact, $validated['message']);
                    $successCount++;
                } else {
                    if ($sendAsAudio && $audioBase64) {
                        $response = Http::timeout(60)->post("{$botUrl}/send-audio", [
                            'contact' => $contact,
                            'text' => $validated['message'],
                            'audio_base64' => $audioBase64,
                            'audio_format' => $audioFormat,
                        ]);
                    } else {
                        $response = Http::timeout(30)->post("{$botUrl}/send-message", [
                            'contact' => $contact,
                            'message' => $validated['message'],
                        ]);
                    }
                    if ($response->successful()) {
                        $successCount++;
                    } else {
                        $errorCount++;
                        $errors[] = ['contact' => $contact, 'error' => $response->json()];
                    }
                }
            } catch (\Throwable $e) {
                $errorCount++;
                $errors[] = ['contact' => $contact, 'error' => $e->getMessage()];
                Log::warning('Remarketing send failed', ['contact' => $contact, 'instance' => $instanceName, 'error' => $e->getMessage()]);
            }

            usleep(500000);
        }

        return response()->json([
            'success' => true,
            'message' => "Mensagens enviadas: {$successCount} sucesso, {$errorCount} erros",
            'data' => [
                'total' => $totalContacts,
                'success' => $successCount,
                'errors' => $errorCount,
                'error_details' => $errors,
            ],
        ]);
    }
}
