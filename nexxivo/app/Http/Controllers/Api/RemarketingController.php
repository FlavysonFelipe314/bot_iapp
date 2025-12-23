<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ElevenLabsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RemarketingController extends Controller
{
    private $elevenLabsService;

    public function __construct(ElevenLabsService $elevenLabsService)
    {
        $this->elevenLabsService = $elevenLabsService;
    }
    public function send(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|min:1',
            'target' => 'required|string|in:all,novo,em_atendimento,aguardando,fechado,selected',
            'contacts' => 'nullable|array',
            'send_as_audio' => 'nullable|boolean',
            'voice_id' => 'nullable|string',
        ]);

        // Validar que a mensagem não está vazia
        if (empty(trim($validated['message']))) {
            return response()->json([
                'success' => false,
                'message' => 'A mensagem não pode estar vazia',
            ], 400);
        }

        // Buscar contatos baseado no target
        $contacts = [];
        
        if ($validated['target'] === 'all') {
            $conversations = Conversation::where('is_archived', false)->get();
            foreach ($conversations as $conversation) {
                $contacts[] = $conversation->contact;
            }
        } elseif ($validated['target'] === 'selected') {
            if (empty($validated['contacts'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum contato selecionado',
                ], 400);
            }
            $contacts = $validated['contacts'];
        } else {
            // Status específico do kanban
            $conversations = Conversation::where('kanban_status', $validated['target'])
                ->where('is_archived', false)
                ->get();
            foreach ($conversations as $conversation) {
                $contacts[] = $conversation->contact;
            }
        }

        if (empty($contacts)) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhum contato encontrado para enviar mensagem',
            ], 400);
        }

        // Remover duplicatas
        $contacts = array_unique($contacts);
        $totalContacts = count($contacts);

        $botUrl = config('services.bot.url', env('BOT_URL', 'http://localhost:3001'));
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        $sendAsAudio = $validated['send_as_audio'] ?? false;

        // Se for enviar como áudio, gerar uma vez para todos
        $audioBase64 = null;
        $audioFormat = null;
        if ($sendAsAudio) {
            try {
                Log::info('Gerando áudio para remarketing', [
                    'message_length' => strlen($validated['message']),
                    'message_preview' => substr($validated['message'], 0, 100),
                ]);

                // Usar o serviço diretamente ao invés de fazer requisição HTTP
                $voiceId = $validated['voice_id'] ?? null;
                $audioResult = $this->elevenLabsService->textToSpeech($validated['message'], $voiceId);

                $audioBase64 = $audioResult['audio'];
                $audioFormat = $audioResult['format'] ?? 'ogg_opus';

                Log::info('Áudio gerado com sucesso para remarketing', [
                    'format' => $audioFormat,
                    'base64_length' => strlen($audioBase64),
                ]);
            } catch (\Exception $e) {
                Log::error('Exceção ao gerar áudio para remarketing', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao gerar áudio: ' . $e->getMessage(),
                ], 500);
            }
        }

        // Enviar mensagem para cada contato
        foreach ($contacts as $contact) {
            try {
                if ($sendAsAudio && $audioBase64) {
                    // Enviar áudio via endpoint especial do bot
                    Log::info('Enviando áudio de remarketing para contato', [
                        'contact' => $contact,
                        'audio_format' => $audioFormat,
                        'audio_base64_length' => strlen($audioBase64),
                        'bot_url' => $botUrl,
                    ]);

                    $response = Http::timeout(60)->post("{$botUrl}/send-audio", [
                        'contact' => $contact,
                        'text' => $validated['message'],
                        'audio_base64' => $audioBase64,
                        'audio_format' => $audioFormat,
                    ]);

                    Log::info('Resposta do bot ao enviar áudio', [
                        'contact' => $contact,
                        'status' => $response->status(),
                        'response' => $response->json(),
                    ]);
                } else {
                    // Enviar como texto normal
                    $response = Http::timeout(30)->post("{$botUrl}/send-message", [
                        'contact' => $contact,
                        'message' => $validated['message'],
                    ]);
                }

                if ($response->successful()) {
                    $successCount++;
                    Log::info('Mensagem de remarketing enviada com sucesso', [
                        'contact' => $contact,
                        'send_as_audio' => $sendAsAudio,
                    ]);
                } else {
                    $errorCount++;
                    $errorResponse = $response->json();
                    $errors[] = [
                        'contact' => $contact,
                        'error' => $errorResponse ?? 'Erro desconhecido',
                    ];
                    Log::warning('Erro ao enviar mensagem de remarketing', [
                        'contact' => $contact,
                        'status' => $response->status(),
                        'error' => $errorResponse,
                        'send_as_audio' => $sendAsAudio,
                    ]);
                }

                // Pequeno delay entre mensagens para não sobrecarregar
                usleep(500000); // 0.5 segundos
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = [
                    'contact' => $contact,
                    'error' => $e->getMessage(),
                ];
                Log::error('Exceção ao enviar mensagem de remarketing', [
                    'contact' => $contact,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'send_as_audio' => $sendAsAudio,
                ]);
            }
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
