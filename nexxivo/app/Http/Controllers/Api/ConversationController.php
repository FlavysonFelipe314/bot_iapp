<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $instanceName = $request->query('instance_name');
        $contact = $request->query('contact');
        $isArchived = $request->query('is_archived', false);

        $query = Conversation::with('latestMessage')
            ->where('is_archived', $isArchived)
            ->orderBy('last_message_at', 'desc');
        
        // Não filtrar por is_blocked aqui - deixar o bot verificar individualmente

        if ($instanceName) {
            $query->where('instance_name', $instanceName);
        }
        
        if ($contact) {
            $query->where('contact', $contact);
        }

        // Se tem filtros específicos (contact), retornar sem paginação
        if ($contact || $instanceName) {
            $conversations = $query->get();
            return response()->json([
                'success' => true,
                'data' => $conversations,
            ]);
        }

        $conversations = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $conversations->items(),
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $conversation = Conversation::with(['messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])->findOrFail($id);

        return response()->json($conversation);
    }

    public function archive($id)
    {
        $conversation = Conversation::findOrFail($id);
        $conversation->update(['is_archived' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Conversa arquivada',
        ]);
    }

    public function block($id)
    {
        $conversation = Conversation::findOrFail($id);
        $conversation->update(['is_blocked' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Contato bloqueado',
            'data' => $conversation,
        ]);
    }

    /**
     * Limpa todos os contextos (conversas e mensagens)
     * Pode filtrar por instance_name se fornecido
     */
    public function clearAll(Request $request)
    {
        try {
            $instanceName = $request->query('instance_name');
            
            DB::beginTransaction();
            
            // Deletar mensagens primeiro (devido à foreign key)
            if ($instanceName) {
                // Deletar mensagens de conversas específicas da instância
                $conversationIds = Conversation::where('instance_name', $instanceName)
                    ->pluck('id');
                Message::whereIn('conversation_id', $conversationIds)->delete();
                
                // Deletar conversas da instância
                $deletedConversations = Conversation::where('instance_name', $instanceName)->delete();
            } else {
                // Deletar todas as mensagens
                Message::truncate();
                
                // Deletar todas as conversas
                $deletedConversations = Conversation::truncate();
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => $instanceName 
                    ? "Todos os contextos da instância '{$instanceName}' foram limpos" 
                    : 'Todos os contextos foram limpos',
                'deleted_conversations' => $deletedConversations ?? 'all',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao limpar contextos: ' . $e->getMessage(),
            ], 500);
        }
    }
}

