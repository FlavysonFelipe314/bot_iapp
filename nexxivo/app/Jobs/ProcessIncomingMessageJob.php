<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Flow;
use App\Models\FlowExecution;
use App\Models\Message;
use App\Services\AIService;
use App\Services\EvolutionApiService;
use App\Services\ElevenLabsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tempo máximo (segundos). Ollama pode levar 2-3 min. */
    public int $timeout = 300;
    /** Tentativas antes de failed_jobs */
    public int $tries = 3;

    public function __construct(
        public string $instanceName,
        public string $contact,
        public string $remoteJid,
        public string $messageText,
        public int $messageId
    ) {}

    public function handle(EvolutionApiService $evolution, AIService $aiService, ElevenLabsService $elevenLabs): void
    {
        Log::info('ProcessIncomingMessageJob iniciado', [
            'instance' => $this->instanceName,
            'contact' => $this->contact,
            'text_preview' => substr($this->messageText, 0, 80),
        ]);
        $flows = Flow::where('is_active', true)
            ->where(function ($q) {
                $q->where('instance_name', $this->instanceName)->orWhereNull('instance_name');
            })
            ->orderBy('priority', 'desc')
            ->get();

        $executed = false;
        $flowThatFailed = null;
        foreach ($flows as $flow) {
            if (! $this->matchFlow($flow)) {
                continue;
            }

            try {
                $this->executeFlow($flow, $evolution, $aiService, $elevenLabs);
                FlowExecution::create([
                    'flow_id' => $flow->id,
                    'contact' => $this->contact,
                    'trigger_message' => $this->messageText,
                ]);
                $executed = true;
            } catch (\Throwable $e) {
                $flowThatFailed = $flow;
                Log::error('ProcessIncomingMessageJob flow error', [
                    'flow_id' => $flow->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            break;
        }

        // Se o fluxo deu erro (ex: timeout Ollama, falha ao enviar), enviar mensagem de erro ao usuário
        if (! $executed && $flowThatFailed) {
            $fallback = $this->getFlowErrorMessage($flowThatFailed);
            if ($fallback !== '') {
                try {
                    $evolution->sendText($this->instanceName, $this->contact, $fallback);
                } catch (\Throwable $e) {
                    Log::error('ProcessIncomingMessageJob falha ao enviar mensagem de erro', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (! $executed && ! $flowThatFailed) {
            Log::info('Nenhum fluxo correspondeu à mensagem', [
                'message_preview' => strlen($this->messageText) > 60 ? substr($this->messageText, 0, 60) . '...' : $this->messageText,
            ]);
        }
    }

    private function matchFlow(Flow $flow): bool
    {
        $triggers = $flow->triggers ?? [];
        if (count($triggers) === 0) {
            return false;
        }

        $text = mb_strtolower($this->messageText);

        foreach ($triggers as $trigger) {
            $type = $trigger['type'] ?? '';
            $value = $trigger['value'] ?? '';

            if ($type === 'catch_all') {
                return true;
            }
            if ($type === 'exact' && $text === mb_strtolower($value)) {
                return true;
            }
            if ($type === 'contains' && str_contains($text, mb_strtolower($value))) {
                return true;
            }
            if ($type === 'starts_with' && str_starts_with($text, mb_strtolower($value))) {
                return true;
            }
        }

        return false;
    }

    private function getFlowErrorMessage(Flow $flow): string
    {
        $actions = $flow->actions ?? [];
        foreach ($actions as $action) {
            if (($action['type'] ?? '') === 'ai_response') {
                $msg = trim((string) ($action['error_message'] ?? ''));
                return $msg !== '' ? $msg : 'Desculpe, não consegui processar. Tente de novo.';
            }
        }
        return 'Desculpe, não consegui processar. Tente de novo.';
    }

    private function executeFlow(Flow $flow, EvolutionApiService $evolution, AIService $aiService, ElevenLabsService $elevenLabs): void
    {
        $actions = $flow->actions ?? [];
        $message = Message::find($this->messageId);
        $conversationId = $message?->conversation_id;

        foreach ($actions as $action) {
            $type = $action['type'] ?? '';

            if ($type === 'send_message') {
                $content = trim((string) ($action['content'] ?? ''));
                if ($content !== '') {
                    $evolution->sendText($this->instanceName, $this->contact, $content);
                }
                continue;
            }

            if ($type === 'wait') {
                $duration = (int) ($action['duration'] ?? 1000);
                usleep($duration * 1000);
                continue;
            }

            if ($type === 'ai_response') {
                $this->executeAiResponse($action, $evolution, $aiService, $elevenLabs, $conversationId);
                continue;
            }

            if ($type === 'conditional') {
                $this->executeConditional($action, $evolution, $aiService, $elevenLabs, $conversationId);
            }
        }
    }

    private function executeAiResponse(array $action, EvolutionApiService $evolution, AIService $aiService, ElevenLabsService $elevenLabs, ?int $conversationId): void
    {
        $prompt = $action['prompt'] ?? 'Responda de forma útil e amigável: {message}';
        $prompt = str_replace('{message}', $this->messageText, $prompt);
        $provider = $action['provider'] ?? null;
        $model = $action['model'] ?? null;
        $useContext = isset($action['use_context']) ? ! empty($action['use_context']) : true;

        $history = [];
        if ($useContext && $conversationId) {
            $messages = Message::where('conversation_id', $conversationId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->reverse()->values();
            foreach ($messages as $msg) {
                $history[] = [
                    'message' => $msg->message,
                    'direction' => $msg->direction,
                    'timestamp' => $msg->timestamp?->toIso8601String(),
                ];
            }
        }

        try {
            $response = $aiService->generateResponse($prompt, $this->messageText, $provider, $model, $history);
            $response = trim($response);
            if ($response !== '') {
                $sendAudio = ! empty($action['send_audio']) || ($action['response_type'] ?? '') === 'audio';
                if ($sendAudio) {
                    $audioBase64 = $elevenLabs->textToSpeech($response);
                    if ($audioBase64 !== '') {
                        $evolution->sendAudio($this->instanceName, $this->contact, $audioBase64);
                    } else {
                        $evolution->sendText($this->instanceName, $this->contact, $response);
                    }
                } else {
                    $evolution->sendText($this->instanceName, $this->contact, $response);
                }
                if ($conversationId) {
                    Message::create([
                        'conversation_id' => $conversationId,
                        'instance_name' => $this->instanceName,
                        'message_id' => 'out_' . uniqid('', true),
                        'from' => $this->instanceName,
                        'to' => $this->remoteJid,
                        'message' => $response,
                        'direction' => 'outgoing',
                        'timestamp' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AI response failed in flow', ['error' => $e->getMessage()]);
            $fallback = $action['error_message'] ?? 'Desculpe, não consegui processar. Tente de novo.';
            if ($fallback !== '') {
                $evolution->sendText($this->instanceName, $this->contact, $fallback);
            }
        }
    }

    private function executeConditional(array $action, EvolutionApiService $evolution, AIService $aiService, ElevenLabsService $elevenLabs, ?int $conversationId): void
    {
        $conditions = $action['conditions'] ?? [];
        $text = mb_strtolower($this->messageText);

        foreach ($conditions as $condition) {
            if (! empty($condition['default'])) {
                continue;
            }
            $type = $condition['type'] ?? '';
            $value = $condition['value'] ?? '';
            $matches = false;
            if ($type === 'contains' && str_contains($text, mb_strtolower($value))) {
                $matches = true;
            }
            if ($type === 'exact' && $text === mb_strtolower($value)) {
                $matches = true;
            }
            if ($type === 'starts_with' && str_starts_with($text, mb_strtolower($value))) {
                $matches = true;
            }
            if ($matches && ! empty($condition['actions'])) {
                foreach ($condition['actions'] as $sub) {
                    if (($sub['type'] ?? '') === 'send_message' && trim((string) ($sub['content'] ?? '')) !== '') {
                        $evolution->sendText($this->instanceName, $this->contact, trim($sub['content']));
                    }
                    if (($sub['type'] ?? '') === 'ai_response') {
                        $this->executeAiResponse($sub, $evolution, $aiService, $elevenLabs, $conversationId);
                    }
                }
                return;
            }
        }

        $default = collect($conditions)->firstWhere('default', true);
        if ($default && ! empty($default['actions'])) {
            foreach ($default['actions'] as $sub) {
                if (($sub['type'] ?? '') === 'send_message' && trim((string) ($sub['content'] ?? '')) !== '') {
                    $evolution->sendText($this->instanceName, $this->contact, trim($sub['content']));
                }
                if (($sub['type'] ?? '') === 'ai_response') {
                    $this->executeAiResponse($sub, $evolution, $aiService, $elevenLabs, $conversationId);
                }
            }
        }
    }
}
