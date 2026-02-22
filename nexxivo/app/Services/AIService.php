<?php
/**
 * Serviço genérico de IA: Ollama (local), Google Gemini. Multi-provedor.
 * Provedor padrão vem do banco (ai_settings.default_provider).
 * NÃO contém persona: prompt vem do Fluxo (banco).
 * Contexto central (system + memória) é montado antes de rotear; Ollama e Gemini recebem o mesmo.
 * Áudio (voz): fluxo pode usar send_audio/response_type=audio → ElevenLabsService + Evolution sendAudio.
 */

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
        $raw = AISetting::get('ollama_url', config('services.ai.ollama_url', env('OLLAMA_URL', 'http://localhost:11434')));
        $this->ollamaUrl = str_replace('http://localhost', 'http://127.0.0.1', $raw);
        $this->geminiApiKey = AISetting::get('gemini_api_key', config('services.ai.gemini_key', env('GEMINI_API_KEY', '')));
        $this->defaultModel = AISetting::get('default_provider', config('services.ai.default_model', env('AI_DEFAULT_MODEL', 'ollama')));
    }

    /**
     * Gera resposta. Contexto central (agnóstico a provedor) é construído antes de rotear para Ollama ou Gemini.
     */
    public function generateResponse(string $prompt, string $userMessage, string $provider = null, string $model = null, array $conversationHistory = []): string
    {
        $provider = $provider ?? $this->defaultModel;
        $context = $this->buildCentralContext($prompt, $userMessage, $conversationHistory);

        try {
            if ($provider === 'ollama') {
                return $this->generateWithOllama($context, $model);
            }
            if ($provider === 'gemini') {
                return $this->generateWithGemini($context, $model);
            }
            throw new \Exception("Provedor de IA não suportado: {$provider}");
        } catch (\Exception $e) {
            Log::error('Erro ao gerar resposta com IA', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Gera resposta usando Ollama (streaming para evitar timeout com modelo frio)
     */
    private function generateWithOllama(array $context, ?string $model = null): string
    {
        $defaultModel = AISetting::get('ollama_model', config('services.ai.ollama_model', env('OLLAMA_MODEL', 'llama2')));
        $model = trim((string) ($model ?? $defaultModel));
        if ($model === '') {
            $model = $defaultModel;
        }

        $messages = $context['messages'] ?? [];
        if (empty($messages)) {
            $messages = [
                ['role' => 'system', 'content' => $context['system']],
                ['role' => 'user', 'content' => trim($context['prompt'])],
            ];
        }
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'options' => ['temperature' => 0.1, 'top_p' => 0.5],
        ];
        Log::info('Payload enviado ao Ollama:', ['messages' => $messages]);
        $url = rtrim($this->ollamaUrl, '/') . '/api/chat';
        $payloadJson = json_encode($payload);
        $tmpFile = tempnam(sys_get_temp_dir(), 'ollama_');
        file_put_contents($tmpFile, $payloadJson);
        $cmd = sprintf(
            "curl -s -N -X POST %s -H 'Content-Type: application/json' -d @%s --max-time 180 2>&1",
            escapeshellarg($url),
            escapeshellarg($tmpFile)
        );
        $handle = popen($cmd, 'r');
        register_shutdown_function(function () use ($tmpFile) { @unlink($tmpFile); });
        if (!$handle) {
            throw new \Exception('Não foi possível invocar Ollama (curl).');
        }
        $buffer = '';
        $responseText = '';
        while (!feof($handle)) {
            $buffer .= fread($handle, 8192);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line === '') continue;
                $data = json_decode($line, true);
                if (is_array($data) && isset($data['error'])) {
                    pclose($handle);
                    throw new \Exception('Ollama: ' . ($data['error'] ?? 'erro desconhecido'));
                }
                if (is_array($data) && isset($data['message']['content'])) {
                    $responseText .= $data['message']['content'];
                }
                if (is_array($data) && !empty($data['done'])) {
                    break 2;
                }
            }
        }
        pclose($handle);
        $responseText = trim($responseText);
        if ($responseText === '') {
            throw new \Exception('Resposta vazia recebida do Ollama.');
        }
        return $this->sanitizeResponseForChat($responseText);
    }

    /**
     * Gera resposta usando Google Gemini (formato oficial da API)
     */
    private function generateWithGemini(array $context, ?string $model = null): string
    {
        $apiKey = AISetting::get('gemini_api_key', '') ?: $this->geminiApiKey;
        if (empty($apiKey)) {
            throw new \Exception("Chave da API do Gemini não configurada. Configure em Configurações IA.");
        }

        $model = $model ?? AISetting::get('gemini_model', config('services.ai.gemini_model', env('GEMINI_MODEL', 'gemini-2.0-flash')));
        $model = trim((string) $model);
        if ($model === '' || $model === 'gemini-pro' || $model === 'gemini-1.5-flash') {
            $model = 'gemini-2.0-flash';
        }

        // Mesmo contexto que Ollama: systemInstruction + contents (user prompt). Temperatura baixa para PT-BR estável.
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $context['system']]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $context['prompt']]]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'topP' => 0.9,
            ],
        ];

        $response = Http::timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                $payload
            );

        $body = $response->body();
        if (!$response->successful()) {
            $errMsg = $body;
            $decoded = json_decode($body, true);
            if (!empty($decoded['error']['message'])) {
                $errMsg = $decoded['error']['message'];
            }
            Log::warning('Gemini API erro', ['status' => $response->status(), 'body' => substr($body, 0, 500)]);
            throw new \Exception("Gemini: " . $errMsg);
        }

        $data = $response->json();
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $blockReason = $data['candidates'][0]['finishReason'] ?? ($data['candidates'][0]['safetyRatings'] ?? 'desconhecido');
            Log::warning('Gemini resposta sem texto', ['data_keys' => array_keys($data)]);
            throw new \Exception("Resposta inválida do Gemini (finishReason: " . json_encode($blockReason) . ")");
        }

        $responseText = trim($data['candidates'][0]['content']['parts'][0]['text']);
        if ($responseText === '') {
            throw new \Exception("Resposta vazia recebida do Gemini");
        }

        return $this->sanitizeResponseForChat($responseText);
    }

    /**
     * Remove da resposta trechos que parecem vazamento de prompt (regras, exemplos).
     */
    private function stripLeakedPromptFromResponse(string $text): string
    {
        $lower = mb_strtolower($text);
        $markers = [
            'regras de ouro', 'as regras de ouro', 'golden rules', 'aqui está um exemplo',
            'exemplo de como você pode responder', 'exemplo de como', 'como você pode responder',
            'nunca ignore', 'nunca repita', 'nunca mostre', '[crítico]', 'sua resposta deve conter apenas',
            'aqui está o roteiro', 'roteiro para você', 'aguarde resposta', 'estado 1', 'estado 2', 'pare aqui',
        ];
        $hasLeak = false;
        foreach ($markers as $m) {
            if (strpos($lower, $m) !== false) {
                $hasLeak = true;
                break;
            }
        }
        if (!$hasLeak) {
            return $text;
        }
        if (stripos($text, 'Laura:') !== false) {
            $parts = preg_split('/Laura\s*:\s*/ui', $text, -1, PREG_SPLIT_NO_EMPTY);
            $last = trim(end($parts));
            if ($last !== '' && strlen($last) < 2000) {
                return $last;
            }
        }
        $lines = preg_split('/\r?\n/', $text);
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') continue;
            if (preg_match('/^\d+\.\s/u', $t)) continue;
            if (preg_match('/^(regras|as regras|exemplo|aqui está|golden)/ui', $t)) continue;
            if (strlen($t) > 20 && strlen($t) < 1500) {
                return $t;
            }
        }
        return $text;
    }

    /**
     * Remove da resposta: instruções de cena (**smiles**), placeholders {img002}, linhas internas (ESTADO X:, PARE AQUI).
     */
    private function sanitizeResponseForChat(string $text): string
    {
        $text = trim($text);
        if ($text === '') return $text;
        // Remover vazamento de prompt: blocos com regras, exemplos ou instruções
        $text = $this->stripLeakedPromptFromResponse($text);
        $text = preg_replace('/\bsmile\b\s*/', '', $text);
        $text = preg_replace('/\*\*[^*]+\*\*/u', '', $text);
        $text = preg_replace('/\*[^*]+\*/u', '', $text);
        $text = preg_replace('/\{img[a-zA-Z0-9]*\}/u', '', $text);
        $lines = explode("\n", $text);
        $out = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') continue;
            if (preg_match('/^ESTADO\s+\d+/ui', $t)) continue;
            if (preg_match('/^PARE\s+AQUI/ui', $t) || preg_match('/^Gatilho\s*:/ui', $t)) continue;
            if (preg_match('/^NUNCA\s+(ignore|repite|mostre|cite)/ui', $t)) continue;
            if (preg_match('/^(Send|Ask)\s*:\s*\{/ui', $t)) continue;
            if (preg_match('/^Waiting for the response/ui', $t)) continue;
            if (preg_match('/roteiro\s+para\s+você|aqui está o roteiro|aguarde\s+(resposta|a resposta)/ui', $t)) continue;
            $out[] = $line;
        }
        $text = implode("\n", $out);
        $text = trim($text);
        $text = $this->replaceEnglishWithPortuguese($text);
        return trim($text);
    }

    private function replaceEnglishWithPortuguese(string $text): string
    {
        $r = [
            'Hello! smile I\'m Laura' => 'Oi! Sou a Laura',
            'Hello! I\'m Laura, from Viu One.' => 'Oi! Sou a Laura da Viu One.',
            'Hello there!' => 'Olá!',
            'Hello! I\'m Laura from Viu One' => 'Oi! Sou a Laura da Viu One',
            'and I\'m here to help you with your landing page today!' => 'e estou aqui para te ajudar com sua landing page hoje!',
            'So, what can I do for you?' => 'O que posso fazer por você?',
            'Do you have any questions about our plans or something else?' => 'Tem alguma dúvida sobre nossos planos?',
            'Let me know, and I\'ll be happy to assist you!' => 'Me diga que terei prazer em te ajudar!',
            'Great choice!' => 'Ótima escolha!',
            'I\'m Laura from Viu One' => 'Sou a Laura da Viu One',
            'It\'s nice to meet you!' => 'Prazer em falar com você!',
            'Which plan do you think makes more sense for you?' => 'Qual dos planos faz mais sentido para você?',
            'Qual do seu choices makes the most sense for you?' => 'Qual dos planos faz mais sentido para você?',
            'makes the most sense for you' => 'faz mais sentido para você',
            'choices' => 'planos',
            'I\'ll put your project in line' => 'Já coloquei seu projeto na fila',
            'send you a preview in 24 hours after payment' => 'te mando a prévia em até 24 horas após o pagamento',
            'feel free to ask' => 'pode perguntar',
            '$197' => 'cento e noventa e sete reais',
            '$397' => 'trezentos e noventa e sete reais',
            'Basic plan' => 'plano Básico',
            'Pro plan' => 'plano PRO',
            'Ah, I see!' => 'Entendi!',
            'Charming!' => 'Ótimo!',
            'Awaiting your response!' => 'Aguardando sua resposta!',
            'Pergunte:' => '',
            'Enviar uma imagem aqui:' => '',
            'Enviará uma imagem para você' => '',
            'na lenda' => 'na landing',
        ];
        foreach ($r as $en => $pt) {
            $text = str_ireplace($en, $pt, $text);
        }
        return $text;
    }



    /**
     * Regras de papel (system) fixas: persona, idioma, proibições. Sempre o primeiro bloco do system.
     */
    private function getFixedSystemRulesForChat(): string
    {
        return "Você é Laura, atendente da Viu One. Fale EXCLUSIVAMENTE em Português do Brasil (PT-BR). NUNCA use palavras em inglês.\n"
            . "REGRA CRÍTICA: O usuário enviará o histórico da conversa. Se você já se apresentou no histórico, NÃO SE APRESENTE NOVAMENTE. Vá direto ao ponto e responda a dúvida atual.\n"
            . "Você NÃO é uma inteligência artificial. Nunca mencione que é uma IA.\n"
            . "Seja curta, amigável e persuasiva. Não envie blocos de texto gigantes.\n"
            . "NUNCA envie tags internas, notas de roteiro ou instruções como (Aguarde resposta do cliente) ou (Envie imagem {img002}). Aja naturalmente. Sua resposta deve conter APENAS o texto que o cliente lê.\n\n";
    }

    /**
     * Remove do prompt do fluxo qualquer roteiro/instruções de palco para a IA não "ler em voz alta".
     */
    private function stripRoteiroFromFlowPrompt(string $promptTemplate): string
    {
        $lines = preg_split('/\r?\n/', $promptTemplate);
        $out = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') continue;
            if (preg_match('/^ESTADO\s+\d+/ui', $t)) continue;
            if (preg_match('/PARE\s+AQUI/ui', $t)) continue;
            if (preg_match('/Aguarde\s+(resposta|a resposta)/ui', $t)) continue;
            if (preg_match('/\{img[a-zA-Z0-9]*\}/u', $t)) continue;
            if (preg_match('/Enviar\s+imagem|roteiro\s+para\s+você/ui', $t)) continue;
            if (preg_match('/^[\d]+\.\s*(Sem símbolos|Valores|Chave PIX|PROIBIDO|NUNCA)/ui', $t)) continue;
            $out[] = $line;
        }
        $cleaned = implode("\n", $out);
        return trim(preg_replace('/\n{3,}/', "\n\n", $cleaned));
    }

    /**
     * Contexto central: system (regras + contexto limpo) e prompt (conversa Cliente/Laura). Usado por Ollama e Gemini.
     */
    private function buildCentralContext(string $promptTemplate, string $userMessage, array $conversationHistory = []): array
    {
        return $this->buildSystemAndUserPrompt($promptTemplate, $userMessage, $conversationHistory);
    }

    /**
     * Para Ollama/Gemini: separa instruções (system) da conversa (prompt). Evita modelo vazar o prompt.
     */
    private function buildSystemAndUserPrompt(string $promptTemplate, string $userMessage, array $conversationHistory = []): array
    {
        $fixedRules = $this->getFixedSystemRulesForChat();
        $contextFromFlow = $this->stripRoteiroFromFlowPrompt($promptTemplate);
        $outputOnlyRule = "\n\n[CRÍTICO] Sua resposta deve conter APENAS a mensagem que você envia ao cliente. NUNCA repita, cite ou liste as regras. NUNCA mostre títulos como Regras de ouro, ESTADO ou Exemplo. Uma única mensagem natural.";
        $system = $fixedRules . ($contextFromFlow !== '' ? "Contexto útil (use apenas para orientar suas respostas, não repita isso ao cliente):\n" . $contextFromFlow . "\n\n" : '') . $outputOnlyRule;
        $userPrompt = "Cliente: " . trim($userMessage) . "\n\nLaura:";
        if (!empty($conversationHistory)) {
            $hist = "";
            foreach ($conversationHistory as $msg) {
                $msgText = trim($msg["message"] ?? "");
                if ($msgText === "" || strpos($msgText, "[Mensagem vazia]") !== false) continue;
                $sender = ($msg["direction"] ?? "") === "incoming" ? "Cliente" : "Laura";
                $hist .= $sender . ": " . $msgText . "\n";
            }
            $userPrompt = trim($hist) . "\n\nCliente: " . trim($userMessage) . "\n\nLaura:";
        }
        $messages = [
            ['role' => 'system', 'content' => $system],
        ];
        foreach ($conversationHistory as $msg) {
            $msgText = trim($msg['message'] ?? '');
            if ($msgText === '' || strpos($msgText, '[Mensagem vazia]') !== false) continue;
            $role = ($msg['direction'] ?? '') === 'incoming' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $msgText];
        }
        $messages[] = ['role' => 'user', 'content' => trim($userMessage)];
        return ['system' => $system, 'prompt' => $userPrompt, 'messages' => $messages];
    }

    /**
     * Constrói o prompt completo substituindo variáveis e incluindo histórico
     */
    private function buildPrompt(string $promptTemplate, string $userMessage, array $conversationHistory = []): string
    {
        $promptTemplate = $this->stripRoteiroFromFlowPrompt($promptTemplate);
        $languageRule = "REGRAS DE IDIOMA E FORMATAÇÃO (OBRIGATÓRIO): Responda SEMPRE em português do Brasil. É PROIBIDO usar inglês na resposta ao cliente. Não inclua instruções de cena como **smiles** ou **nodding**; escreva apenas o texto natural que o cliente deve ler.\n\n";
        // Substituir variáveis no prompt
        $prompt = str_replace('{message}', $userMessage, $promptTemplate);
        $prompt = str_replace('{user_message}', $userMessage, $prompt);
        
        // Adicionar histórico de conversa se disponível
        if (!empty($conversationHistory)) {
            Log::info('Construindo prompt com histórico', [
                'history_count' => count($conversationHistory),
                'last_messages' => array_slice($conversationHistory, -3),
            ]);
            
            $historyText = "\n\n--- Histórico da Conversa (IMPORTANTE: Use este contexto para responder) ---\n";
            foreach ($conversationHistory as $msg) {
                // FILTRAR mensagens vazias do histórico
                $msgText = trim($msg['message'] ?? '');
                if (empty($msgText) || 
                    $msgText === '[Mensagem vazia]' || 
                    $msgText === '[Erro ao processar áudio]' ||
                    $msgText === '[Áudio não disponível]' ||
                    $msgText === '[Áudio não transcrito]') {
                    continue; // Pular mensagens vazias
                }
                
                $sender = $msg['direction'] === 'incoming' ? 'Cliente' : 'Atendente';
                // Limpar prefixos [Áudio] do histórico também
                $cleanMessage = preg_replace('/^(\[Áudio\]|\[Audio\]|audio:|áudio:|Audio:|Áudio:)\s*/i', '', $msgText);
                $cleanMessage = preg_replace('/^(audio|áudio)\s*:?\s*/i', '', $cleanMessage);
                
                // Validar que ainda há conteúdo após limpeza
                if (empty(trim($cleanMessage))) {
                    continue; // Pular se ficou vazio após limpeza
                }
                
                $historyText .= "{$sender}: {$cleanMessage}\n";
            }
            $historyText .= "--- Fim do Histórico ---\n\n";
            $historyText .= "IMPORTANTE: Baseie sua resposta no histórico acima. NÃO repita perguntas que já foram respondidas. Continue a conversa de forma natural.\n\n";
            
            // Inserir histórico antes da mensagem atual
            $prompt = str_replace('{message}', $historyText . "Cliente: {$userMessage}", $promptTemplate);
            $prompt = str_replace('{user_message}', $historyText . "Cliente: {$userMessage}", $promptTemplate);
            
            // Se não tinha variáveis, adicionar histórico no início
            if ($prompt === $promptTemplate) {
                $prompt = $historyText . $prompt . "\n\nCliente: {$userMessage}";
            }
        } else {
            Log::warning('Prompt sendo construído SEM histórico de conversa', [
                'user_message_preview' => substr($userMessage, 0, 50),
            ]);
        }

        $finalReminder = "\n\n[LEMBRETE OBRIGATÓRIO: Responda SOMENTE em português do Brasil. É PROIBIDO escrever em inglês.]"; return $languageRule . $prompt . $finalReminder;
    }
}

