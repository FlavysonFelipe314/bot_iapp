<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotInstance;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'instance_name' => 'required|string',
            'message_id' => 'required|string',
            'from' => 'required|string',
            'to' => 'nullable|string',
            'message' => 'required|string',
            'timestamp' => 'required|integer',
            'direction' => 'nullable|string|in:incoming,outgoing',
            'raw_message' => 'nullable|array',
        ]);

        // VALIDAÇÃO CRÍTICA: Rejeitar mensagens vazias ou com placeholder de mensagem vazia
        $messageText = trim($validated['message']);
        if (empty($messageText) || 
            $messageText === '[Mensagem vazia]' || 
            $messageText === '[Erro ao processar áudio]' ||
            $messageText === '[Áudio não disponível]' ||
            $messageText === '[Áudio não transcrito]') {
            Log::warning('Tentativa de salvar mensagem vazia bloqueada', [
                'instance' => $validated['instance_name'],
                'from' => $validated['from'],
                'message_preview' => substr($validated['message'], 0, 50),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Mensagens vazias não podem ser salvas',
            ], 400);
        }

        $direction = $validated['direction'] ?? 'incoming';
        $contact = $direction === 'incoming' ? $validated['from'] : ($validated['to'] ?? $validated['from']);

        // Buscar ou criar conversa
        $conversation = Conversation::firstOrCreate(
            [
                'instance_name' => $validated['instance_name'],
                'contact' => $contact,
            ],
            [
                'last_message_at' => now(),
            ]
        );

        // Atualizar última mensagem
        $conversation->update(['last_message_at' => now()]);

        // Criar mensagem
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'instance_name' => $validated['instance_name'],
            'message_id' => $validated['message_id'],
            'from' => $validated['from'],
            'to' => $validated['to'] ?? null,
            'message' => $validated['message'],
            'direction' => $direction,
            'raw_data' => $validated['raw_message'] ?? null,
            'timestamp' => \Carbon\Carbon::createFromTimestamp($validated['timestamp']),
        ]);

        // LÓGICA DE MOVIMENTAÇÃO AUTOMÁTICA NO KANBAN
        // Se a mensagem é incoming (usuário respondeu)
        if ($direction === 'incoming') {
            // Verificar se a última mensagem anterior foi outgoing (da IA/bot)
            $lastOutgoingMessage = Message::where('conversation_id', $conversation->id)
                ->where('direction', 'outgoing')
                ->where('id', '<', $message->id)
                ->orderBy('id', 'desc')
                ->first();

            // Se encontrou mensagem da IA antes desta resposta do usuário
            if ($lastOutgoingMessage) {
                // Mover para "em_atendimento" quando usuário responde à IA
                if ($conversation->kanban_status !== 'em_atendimento') {
                    $conversation->update(['kanban_status' => 'em_atendimento']);
                    Log::info('Conversa movida para em_atendimento', [
                        'conversation_id' => $conversation->id,
                        'contact' => $conversation->contact,
                    ]);
                }
            }
        }

        Log::info('Mensagem recebida', [
            'instance' => $validated['instance_name'],
            'from' => $validated['from'],
            'message' => substr($validated['message'], 0, 50),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mensagem salva',
            'data' => $message,
        ]);
    }

    public function index(Request $request)
    {
        $conversationId = $request->query('conversation_id');
        $afterId = $request->query('after_id');

        $query = Message::with('conversation')
            ->orderBy('created_at', 'asc');

        if ($conversationId) {
            $user = $request->user();
            if (! $user) {
                return response()->json(['success' => false, 'message' => 'Não autorizado'], 401);
            }
            $instanceNames = BotInstance::where('user_id', $user->id)->pluck('instance_name');
            $allowed = Conversation::whereIn('instance_name', $instanceNames)->where('id', $conversationId)->exists();
            if (! $allowed) {
                return response()->json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
            }
            $query->where('conversation_id', $conversationId);
        }

        // Polling: só mensagens novas (id > after_id)
        if ($afterId !== null && $afterId !== '') {
            $query->where('id', '>', (int) $afterId);
        }

        $messages = $query->paginate(100);

        return response()->json([
            'success' => true,
            'data' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }
}

