<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    private $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Gera resposta usando IA
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'prompt' => 'required|string',
            'message' => 'required|string',
            'provider' => 'nullable|string|in:ollama,gemini',
            'model' => 'nullable|string',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
            'use_context' => 'nullable|boolean',
            'conversation_history' => 'nullable|array',
        ]);

        try {
            $conversationHistory = [];
            
            // Se use_context está ativo e temos conversation_id, buscar histórico
            if (!empty($validated['use_context']) && !empty($validated['conversation_id'])) {
                $conversationHistory = $this->getConversationHistory($validated['conversation_id']);
            } elseif (!empty($validated['conversation_history'])) {
                // Se histórico foi enviado diretamente, usar
                $conversationHistory = $validated['conversation_history'];
            }

            $response = $this->aiService->generateResponse(
                $validated['prompt'],
                $validated['message'],
                $validated['provider'] ?? null,
                $validated['model'] ?? null,
                $conversationHistory
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'response' => $response,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao gerar resposta com IA', [
                'error' => $e->getMessage(),
                'prompt' => substr($validated['prompt'], 0, 100),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar resposta com IA: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Busca histórico de mensagens da conversa
     */
    private function getConversationHistory(int $conversationId, int $limit = 20): array
    {
        $messages = \App\Models\Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        return $messages->map(function ($msg) {
            return [
                'message' => $msg->message,
                'direction' => $msg->direction,
                'timestamp' => $msg->timestamp->toIso8601String(),
            ];
        })->toArray();
    }
}

