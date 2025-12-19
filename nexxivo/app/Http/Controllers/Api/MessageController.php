<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $query = Message::with('conversation')
            ->orderBy('created_at', 'asc'); // Ordenar do mais antigo para o mais recente para chat

        if ($conversationId) {
            $query->where('conversation_id', $conversationId);
        }

        $messages = $query->paginate(100); // Aumentar limite para chat

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

