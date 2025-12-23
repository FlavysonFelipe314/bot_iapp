<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RemarketingController extends Controller
{
    public function send(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|min:1',
            'target' => 'required|string|in:all,novo,em_atendimento,aguardando,fechado,selected',
            'contacts' => 'nullable|array',
            'send_as_audio' => 'nullable|boolean',
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
        $laravelApiUrl = config('app.url', 'http://localhost:8000');

        // Se for enviar como áudio, gerar uma vez para todos
        $audioBase64 = null;
        $audioFormat = null;
        if ($sendAsAudio) {
            try {
                $audioResponse = Http::timeout(60)->post("{$laravelApiUrl}/api/elevenlabs/text-to-speech", [
                    'text' => $validated['message'],
                ]);

                if (!$audioResponse->successful() || !$audioResponse->json()['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Erro ao gerar áudio: ' . ($audioResponse->json()['message'] ?? 'Erro desconhecido'),
                    ], 500);
                }

                $audioData = $audioResponse->json()['data'];
                $audioBase64 = $audioData['audio'];
                $audioFormat = $audioData['format'] ?? 'ogg_opus';
            } catch (\Exception $e) {
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
                    $response = Http::timeout(60)->post("{$botUrl}/send-audio", [
                        'contact' => $contact,
                        'text' => $validated['message'],
                        'audio_base64' => $audioBase64,
                        'audio_format' => $audioFormat,
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
                } else {
                    $errorCount++;
                    $errors[] = [
                        'contact' => $contact,
                        'error' => $response->json() ?? 'Erro desconhecido',
                    ];
                }

                // Pequeno delay entre mensagens para não sobrecarregar
                usleep(500000); // 0.5 segundos
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = [
                    'contact' => $contact,
                    'error' => $e->getMessage(),
                ];
                Log::error('Erro ao enviar mensagem de remarketing', [
                    'contact' => $contact,
                    'error' => $e->getMessage(),
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
