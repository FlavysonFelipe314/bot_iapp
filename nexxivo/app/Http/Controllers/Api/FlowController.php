<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flow;
use Illuminate\Http\Request;

class FlowController extends Controller
{
    public function index()
    {
        $flows = Flow::orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $flows,
        ]);
    }

    public function active()
    {
        $flows = Flow::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $flows,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'triggers' => 'required|array|min:1',
                'triggers.*.type' => 'required|string|in:exact,contains,starts_with,catch_all',
                'triggers.*.value' => 'nullable|string', // Pode ser vazio para catch_all
                'actions' => 'required|array|min:1',
                'actions.*.type' => 'required|string|in:send_message,wait,ai_response,conditional',
                'actions.*.content' => 'nullable|string',
                'actions.*.duration' => 'nullable|integer|min:0',
                'actions.*.prompt' => 'nullable|string',
                'actions.*.provider' => 'nullable|string|in:ollama,gemini',
                'actions.*.model' => 'nullable|string',
                'actions.*.show_typing' => 'nullable|boolean',
                'actions.*.use_context' => 'nullable|boolean',
                'actions.*.use_audio' => 'nullable|boolean',
                'actions.*.voice_id' => 'nullable|string',
                'actions.*.error_message' => 'nullable|string',
                'actions.*.sensitive_keywords' => 'nullable',
                'is_active' => 'sometimes|boolean',
                'priority' => 'nullable|integer|min:0',
            ], [
                'triggers.required' => 'É necessário adicionar pelo menos um gatilho.',
                'triggers.min' => 'É necessário adicionar pelo menos um gatilho.',
                'actions.required' => 'É necessário adicionar pelo menos uma ação.',
                'actions.min' => 'É necessário adicionar pelo menos uma ação.',
                'triggers.*.type.required' => 'O tipo do gatilho é obrigatório.',
                'triggers.*.value.required' => 'O valor do gatilho é obrigatório.',
                'actions.*.type.required' => 'O tipo da ação é obrigatório.',
            ]);

            // Validação customizada para actions
            foreach ($validated['actions'] as $index => $action) {
                if ($action['type'] === 'send_message' && empty($action['content'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A ação "Enviar Mensagem" requer um conteúdo.',
                        'errors' => [
                            "actions.{$index}.content" => ['O conteúdo da mensagem é obrigatório quando o tipo é "send_message".']
                        ]
                    ], 422);
                }
                
                if ($action['type'] === 'wait' && (empty($action['duration']) || $action['duration'] < 0)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A ação "Aguardar" requer uma duração válida (em milissegundos).',
                        'errors' => [
                            "actions.{$index}.duration" => ['A duração é obrigatória e deve ser maior ou igual a 0 quando o tipo é "wait".']
                        ]
                    ], 422);
                }
                
                if ($action['type'] === 'ai_response' && empty($action['prompt'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A ação "Resposta com IA" requer um prompt.',
                        'errors' => [
                            "actions.{$index}.prompt" => ['O prompt é obrigatório quando o tipo é "ai_response".']
                        ]
                    ], 422);
                }
            }

            // Limpar campos não necessários e processar sensitive_keywords
            foreach ($validated['actions'] as &$action) {
                if ($action['type'] === 'send_message') {
                    unset($action['duration']);
                } else if ($action['type'] === 'wait') {
                    unset($action['content']);
                }
                
                // Processar sensitive_keywords: se for string, converter para array
                if (isset($action['sensitive_keywords'])) {
                    if (is_string($action['sensitive_keywords'])) {
                        // Se for string separada por vírgula, converter para array
                        $keywords = array_map('trim', explode(',', $action['sensitive_keywords']));
                        $keywords = array_filter($keywords, function($k) { return !empty($k); });
                        $action['sensitive_keywords'] = !empty($keywords) ? $keywords : null;
                    } elseif (is_array($action['sensitive_keywords'])) {
                        // Se já for array, limpar valores vazios
                        $action['sensitive_keywords'] = array_filter(array_map('trim', $action['sensitive_keywords']), function($k) { return !empty($k); });
                        $action['sensitive_keywords'] = !empty($action['sensitive_keywords']) ? array_values($action['sensitive_keywords']) : null;
                    } else {
                        $action['sensitive_keywords'] = null;
                    }
                }
            }

            $flow = Flow::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Fluxo criado com sucesso',
                'data' => $flow,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $flow = Flow::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'triggers' => 'sometimes|required|array|min:1',
                'triggers.*.type' => 'required|string|in:exact,contains,starts_with',
                'triggers.*.value' => 'required|string',
                'actions' => 'sometimes|required|array|min:1',
                'actions.*.type' => 'required|string|in:send_message,wait,ai_response,conditional',
                'actions.*.content' => 'nullable|string',
                'actions.*.duration' => 'nullable|integer|min:0',
                'actions.*.prompt' => 'nullable|string',
                'actions.*.provider' => 'nullable|string|in:ollama,gemini',
                'actions.*.model' => 'nullable|string',
                'actions.*.show_typing' => 'nullable|boolean',
                'actions.*.use_context' => 'nullable|boolean',
                'actions.*.use_audio' => 'nullable|boolean',
                'actions.*.voice_id' => 'nullable|string',
                'actions.*.error_message' => 'nullable|string',
                'actions.*.sensitive_keywords' => 'nullable',
                'is_active' => 'sometimes|boolean',
                'priority' => 'nullable|integer|min:0',
            ], [
                'triggers.required' => 'É necessário adicionar pelo menos um gatilho.',
                'triggers.min' => 'É necessário adicionar pelo menos um gatilho.',
                'actions.required' => 'É necessário adicionar pelo menos uma ação.',
                'actions.min' => 'É necessário adicionar pelo menos uma ação.',
            ]);

            // Validação customizada para actions
            if (isset($validated['actions'])) {
                foreach ($validated['actions'] as $index => $action) {
                    if ($action['type'] === 'send_message' && empty($action['content'])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'A ação "Enviar Mensagem" requer um conteúdo.',
                            'errors' => [
                                "actions.{$index}.content" => ['O conteúdo da mensagem é obrigatório quando o tipo é "send_message".']
                            ]
                        ], 422);
                    }
                    
                    if ($action['type'] === 'wait' && (empty($action['duration']) || $action['duration'] < 0)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'A ação "Aguardar" requer uma duração válida (em milissegundos).',
                            'errors' => [
                                "actions.{$index}.duration" => ['A duração é obrigatória e deve ser maior ou igual a 0 quando o tipo é "wait".']
                            ]
                        ], 422);
                    }
                }

                // Limpar campos não necessários e processar sensitive_keywords
                foreach ($validated['actions'] as &$action) {
                    if ($action['type'] === 'send_message') {
                        unset($action['duration'], $action['prompt'], $action['provider'], $action['model']);
                    } else if ($action['type'] === 'wait') {
                        unset($action['content'], $action['prompt'], $action['provider'], $action['model']);
                    } else if ($action['type'] === 'ai_response') {
                        // Processar sensitive_keywords: se for string, converter para array
                        if (isset($action['sensitive_keywords'])) {
                            if (is_string($action['sensitive_keywords'])) {
                                // Se for string separada por vírgula, converter para array
                                $keywords = array_map('trim', explode(',', $action['sensitive_keywords']));
                                $keywords = array_filter($keywords, function($k) { return !empty($k); });
                                $action['sensitive_keywords'] = !empty($keywords) ? array_values($keywords) : null;
                            } elseif (is_array($action['sensitive_keywords'])) {
                                // Se já for array, limpar valores vazios
                                $action['sensitive_keywords'] = array_filter(array_map('trim', $action['sensitive_keywords']), function($k) { return !empty($k); });
                                $action['sensitive_keywords'] = !empty($action['sensitive_keywords']) ? array_values($action['sensitive_keywords']) : null;
                            } else {
                                $action['sensitive_keywords'] = null;
                            }
                        }
                        unset($action['content'], $action['duration']);
                    }
                }
            }

            $flow->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Fluxo atualizado com sucesso',
                'data' => $flow,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        $flow = Flow::findOrFail($id);
        $flow->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fluxo deletado com sucesso',
        ]);
    }
}

