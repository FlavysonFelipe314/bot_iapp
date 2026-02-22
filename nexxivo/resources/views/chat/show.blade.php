@extends('layouts.app')

@section('title', 'Conversa')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow-lg h-[calc(100vh-200px)] flex flex-col">
        <!-- Header -->
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ $conversation->contact_name ?? $conversation->contact }}
                </h1>
                <p class="text-sm text-gray-500">{{ $conversation->contact }}</p>
            </div>
            <a href="{{ route('chat.index') }}" class="text-gray-500 hover:text-gray-700">
                ← Voltar
            </a>
        </div>

        <!-- Messages Area -->
        <div id="messages-container" class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-50">
            @foreach($conversation->messages as $message)
            <div class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg {{ $message->direction === 'outgoing' ? 'bg-blue-500 text-white' : 'bg-white text-gray-900 border border-gray-200' }}">
                    <p class="text-sm">{{ $message->message }}</p>
                    <p class="text-xs mt-1 opacity-75">
                        {{ $message->created_at->format('H:i') }}
                    </p>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Input Area -->
        <div class="p-6 border-t border-gray-200">
            <form id="message-form" class="flex gap-4">
                <input 
                    type="text" 
                    id="message-input" 
                    placeholder="Digite sua mensagem..." 
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    required
                >
                <button 
                    type="submit" 
                    id="send-button"
                    class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Enviar
                </button>
            </form>
        </div>
    </div>
</div>

<script>
const conversationId = {{ $conversation->id }};
const instanceName = '{{ $conversation->instance_name }}';
const contact = '{{ $conversation->contact }}';

document.getElementById('message-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const input = document.getElementById('message-input');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Adicionar mensagem imediatamente à interface (otimistic update)
    const messagesContainer = document.getElementById('messages-container');
    const messageId = 'temp-' + Date.now();
    const messageDiv = document.createElement('div');
    messageDiv.id = messageId;
    messageDiv.className = 'flex justify-end';
    messageDiv.innerHTML = `
        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg bg-blue-500 text-white">
            <p class="text-sm">${message}</p>
            <p class="text-xs mt-1 opacity-75">Enviando...</p>
        </div>
    `;
    messagesContainer.appendChild(messageDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    
    // Limpar input imediatamente
    input.value = '';
    
    // Desabilitar botão enquanto envia
    const submitButton = document.getElementById('send-button');
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = 'Enviando...';
    
    try {
        const response = await axios.post('/api/bot/send-message', {
            instance_name: instanceName,
            contact: contact,
            message: message
        });
        
        if (response.data.success) {
            // Atualizar mensagem com status de sucesso
            const messageElement = document.getElementById(messageId);
            if (messageElement) {
                messageElement.innerHTML = `
                    <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg bg-blue-500 text-white">
                        <p class="text-sm">${message}</p>
                        <p class="text-xs mt-1 opacity-75">Agora</p>
                    </div>
                `;
            }
        } else {
            throw new Error(response.data.message || 'Erro ao enviar mensagem');
        }
    } catch (error) {
        console.error('Erro ao enviar mensagem:', error);
        
        // Remover mensagem da interface em caso de erro
        const messageElement = document.getElementById(messageId);
        if (messageElement) {
            messageElement.remove();
        }
        
        // Restaurar mensagem no input
        input.value = message;
        
        // Mostrar erro
        let errorMessage = 'Erro ao enviar mensagem. Tente novamente.';
        if (error.response?.data?.error) {
            errorMessage = error.response.data.error;
        } else if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.message) {
            errorMessage = error.message;
        }
        alert(errorMessage);
    } finally {
        // Reabilitar botão
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    }
});

// Auto-scroll para o final
const messagesContainer = document.getElementById('messages-container');

// Último ID de mensagem exibido (para só acrescentar mensagens novas)
let lastMessageId = {{ $conversation->messages->isEmpty() ? 0 : $conversation->messages->max('id') }};

function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}
scrollToBottom();

function renderMessage(msg) {
    const isOutgoing = msg.direction === 'outgoing';
    const timeStr = msg.created_at ? new Date(msg.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
    const div = document.createElement('div');
    div.className = 'flex ' + (isOutgoing ? 'justify-end' : 'justify-start');
    div.dataset.messageId = msg.id;
    div.innerHTML = `
        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg ${isOutgoing ? 'bg-blue-500 text-white' : 'bg-white text-gray-900 border border-gray-200'}">
            <p class="text-sm">${escapeHtml(msg.message || '')}</p>
            <p class="text-xs mt-1 opacity-75">${timeStr}</p>
        </div>
    `;
    return div;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Polling para novas mensagens na conversa aberta (a cada 3s, só quando a aba está visível)
setInterval(async () => {
    if (document.visibilityState !== 'visible') return;
    try {
        const response = await axios.get(`/api/messages?conversation_id=${conversationId}&after_id=${lastMessageId}`);
        const messages = response.data.data || [];
        for (const msg of messages) {
            messagesContainer.appendChild(renderMessage(msg));
            lastMessageId = Math.max(lastMessageId, msg.id);
        }
        if (messages.length) scrollToBottom();
    } catch (err) {
        // Silenciar erros de polling
    }
}, 3000);
</script>
@endsection

