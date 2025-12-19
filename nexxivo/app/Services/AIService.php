<?php

namespace App\Services;

use App\Models\AISetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    private $ollamaUrl;
    private $geminiApiKey;
    private $defaultModel;

    public function __construct()
    {
        // Buscar configurações do banco de dados, com fallback para .env
        $this->ollamaUrl = AISetting::get('ollama_url', config('services.ai.ollama_url', env('OLLAMA_URL', 'http://localhost:11434')));
        $this->geminiApiKey = AISetting::get('gemini_api_key', config('services.ai.gemini_key', env('GEMINI_API_KEY', '')));
        $this->defaultModel = AISetting::get('default_provider', config('services.ai.default_model', env('AI_DEFAULT_MODEL', 'ollama')));
    }

    /**
     * Gera resposta usando IA
     */
    public function generateResponse(string $prompt, string $userMessage, string $provider = null, string $model = null, array $conversationHistory = []): string
    {
        $provider = $provider ?? $this->defaultModel;

        try {
            if ($provider === 'ollama') {
                return $this->generateWithOllama($prompt, $userMessage, $model, $conversationHistory);
            } elseif ($provider === 'gemini') {
                return $this->generateWithGemini($prompt, $userMessage, $model, $conversationHistory);
            } else {
                throw new \Exception("Provedor de IA não suportado: {$provider}");
            }
        } catch (\Exception $e) {
            Log::error('Erro ao gerar resposta com IA', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Gera resposta usando Ollama
     */
    private function generateWithOllama(string $prompt, string $userMessage, ?string $model = null, array $conversationHistory = []): string
    {
        $model = $model ?? AISetting::get('ollama_model', config('services.ai.ollama_model', env('OLLAMA_MODEL', 'llama2')));

        $fullPrompt = $this->buildPrompt($prompt, $userMessage, $conversationHistory);

        $response = Http::timeout(60)->post("{$this->ollamaUrl}/api/generate", [
            'model' => $model,
            'prompt' => $fullPrompt,
            'stream' => false,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Erro ao conectar com Ollama: " . $response->body());
        }

        $data = $response->json();

        if (!isset($data['response'])) {
            throw new \Exception("Resposta inválida do Ollama");
        }

        return trim($data['response']);
    }

    /**
     * Gera resposta usando Google Gemini
     */
    private function generateWithGemini(string $prompt, string $userMessage, ?string $model = null, array $conversationHistory = []): string
    {
        if (empty($this->geminiApiKey)) {
            throw new \Exception("Chave da API do Gemini não configurada");
        }

        $model = $model ?? AISetting::get('gemini_model', config('services.ai.gemini_model', env('GEMINI_MODEL', 'gemini-pro')));
        
        // Usar API key do banco se disponível
        $apiKey = AISetting::get('gemini_api_key', '');
        if (!empty($apiKey)) {
            $this->geminiApiKey = $apiKey;
        }

        // Construir histórico para Gemini (formato de mensagens)
        $contents = [];
        
        // Adicionar histórico se disponível
        if (!empty($conversationHistory)) {
            foreach ($conversationHistory as $msg) {
                $role = $msg['direction'] === 'incoming' ? 'user' : 'model';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg['message']]]
                ];
            }
        }
        
        // Adicionar prompt e mensagem atual
        $fullPrompt = $this->buildPrompt($prompt, $userMessage, $conversationHistory);
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $fullPrompt]]
        ];

        $response = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->geminiApiKey}",
            [
                'contents' => $contents,
            ]
        );

        if (!$response->successful()) {
            throw new \Exception("Erro ao conectar com Gemini: " . $response->body());
        }

        $data = $response->json();

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception("Resposta inválida do Gemini");
        }

        return trim($data['candidates'][0]['content']['parts'][0]['text']);
    }

    /**
     * Constrói o prompt completo substituindo variáveis e incluindo histórico
     */
    private function buildPrompt(string $promptTemplate, string $userMessage, array $conversationHistory = []): string
    {
        // Substituir variáveis no prompt
        $prompt = str_replace('{message}', $userMessage, $promptTemplate);
        $prompt = str_replace('{user_message}', $userMessage, $prompt);
        
        // Adicionar histórico de conversa se disponível
        if (!empty($conversationHistory)) {
            $historyText = "\n\n--- Histórico da Conversa ---\n";
            foreach ($conversationHistory as $msg) {
                $sender = $msg['direction'] === 'incoming' ? 'Cliente' : 'Atendente';
                $historyText .= "{$sender}: {$msg['message']}\n";
            }
            $historyText .= "--- Fim do Histórico ---\n\n";
            
            // Inserir histórico antes da mensagem atual
            $prompt = str_replace('{message}', $historyText . "Cliente: {$userMessage}", $promptTemplate);
            $prompt = str_replace('{user_message}', $historyText . "Cliente: {$userMessage}", $promptTemplate);
            
            // Se não tinha variáveis, adicionar histórico no início
            if ($prompt === $promptTemplate) {
                $prompt = $historyText . $prompt . "\n\nCliente: {$userMessage}";
            }
        }
        
        return $prompt;
    }
}

