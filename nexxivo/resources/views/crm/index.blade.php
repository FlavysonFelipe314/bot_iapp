@extends('layouts.app')

@section('title', 'CRM Kanban')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent">
            <i class="fas fa-columns mr-3"></i>CRM Kanban
        </h1>
        <div class="flex gap-3">
            <a href="/chat" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                <i class="fas fa-comments mr-2"></i>Ver Conversas
            </a>
            <button onclick="openRemarketingModal()" class="btn-primary text-white px-6 py-2 rounded-lg font-semibold">
                <i class="fas fa-bullhorn mr-2"></i>Remarketing
            </button>
        </div>
    </div>

    <!-- Kanban Board -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        @foreach($statuses as $status)
        <div class="card-modern p-4">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-800 mb-2">
                    @if($status === 'novo')
                        <i class="fas fa-circle text-blue-500 mr-2"></i>Novo
                    @elseif($status === 'em_atendimento')
                        <i class="fas fa-circle text-yellow-500 mr-2"></i>Em Atendimento
                    @elseif($status === 'aguardando')
                        <i class="fas fa-circle text-orange-500 mr-2"></i>Aguardando
                    @else
                        <i class="fas fa-circle text-green-500 mr-2"></i>Fechado
                    @endif
                </h2>
                <span class="text-sm text-gray-500">{{ $conversationsByStatus[$status]->count() }} contatos</span>
            </div>
            
            <div class="space-y-3 max-h-[600px] overflow-y-auto" id="kanban-{{ $status }}" ondrop="drop(event)" ondragover="allowDrop(event)">
                @foreach($conversationsByStatus[$status] as $conversation)
                <div class="bg-white border-2 border-gray-200 rounded-lg p-4 cursor-move hover:border-purple-400 transition" 
                     draggable="true" 
                     ondragstart="drag(event)" 
                     data-conversation-id="{{ $conversation->id }}"
                     data-status="{{ $status }}">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-indigo-500 flex items-center justify-center text-white font-bold mr-2">
                                {{ strtoupper(substr($conversation->contact_name ?? $conversation->contact, 0, 1)) }}
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 text-sm">
                                    {{ $conversation->contact_name ?? $conversation->contact }}
                                </h3>
                                <p class="text-xs text-gray-500">{{ $conversation->contact }}</p>
                            </div>
                        </div>
                    </div>
                    @if($conversation->latestMessage)
                    <p class="text-xs text-gray-600 mt-2 truncate">
                        <i class="fas fa-comment-dots mr-1 text-purple-500"></i>
                        {{ \Illuminate\Support\Str::limit($conversation->latestMessage->message, 50) }}
                    </p>
                    @endif
                    @if($conversation->last_message_at)
                    <p class="text-xs text-gray-400 mt-2">
                        <i class="fas fa-clock mr-1"></i>{{ $conversation->last_message_at->diffForHumans() }}
                    </p>
                    @endif
                    <a href="{{ route('chat.show', $conversation->id) }}" class="text-xs text-purple-600 hover:text-purple-800 mt-2 inline-block">
                        <i class="fas fa-eye mr-1"></i>Ver conversa
                    </a>
                </div>
                @endforeach
                
                @if($conversationsByStatus[$status]->count() === 0)
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p class="text-sm">Nenhum contato</p>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>

<!-- Modal de Remarketing -->
<div id="remarketingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="card-modern p-6 max-w-2xl w-full mx-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-bullhorn mr-2 text-purple-600"></i>Remarketing
            </h2>
            <button onclick="closeRemarketingModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form id="remarketingForm" onsubmit="sendRemarketing(event)">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Mensagem</label>
                <textarea id="remarketingMessage" rows="4" class="w-full border-2 border-gray-300 rounded-lg p-3 focus:border-purple-500 focus:outline-none" placeholder="Digite a mensagem que será enviada..." required></textarea>
            </div>
            
            <div class="mb-4">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="sendAsAudio" name="send_as_audio" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <span class="ml-2 text-sm font-semibold text-gray-700">
                        <i class="fas fa-volume-up mr-1"></i>Enviar como áudio
                    </span>
                </label>
                <p class="text-xs text-gray-500 mt-1 ml-6">A mensagem será convertida em áudio usando ElevenLabs</p>
            </div>
            
            <div id="voiceSelection" class="mb-4 hidden">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-microphone mr-1"></i>Voz
                </label>
                <div class="flex gap-2">
                    <select id="remarketingVoiceId" class="flex-1 border-2 border-gray-300 rounded-lg p-3 focus:border-purple-500 focus:outline-none">
                        <option value="">Carregando vozes...</option>
                    </select>
                    <button type="button" onclick="loadVoices()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300" title="Recarregar vozes">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Selecione a voz que será usada para o áudio</p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Enviar para</label>
                <select id="remarketingTarget" class="w-full border-2 border-gray-300 rounded-lg p-3 focus:border-purple-500 focus:outline-none" required>
                    <option value="all">Todos os contatos</option>
                    <option value="novo">Novo</option>
                    <option value="em_atendimento">Em Atendimento</option>
                    <option value="aguardando">Aguardando</option>
                    <option value="fechado">Fechado</option>
                    <option value="selected">Contatos Selecionados</option>
                </select>
            </div>
            
            <div id="selectedContacts" class="mb-4 hidden">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Selecionar Contatos</label>
                <div class="max-h-48 overflow-y-auto border-2 border-gray-300 rounded-lg p-3">
                    @foreach($statuses as $status)
                        @foreach($conversationsByStatus[$status] as $conversation)
                        <label class="flex items-center mb-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                            <input type="checkbox" name="selected_contacts[]" value="{{ $conversation->contact }}" class="mr-2">
                            <span class="text-sm text-gray-700">{{ $conversation->contact_name ?? $conversation->contact }}</span>
                        </label>
                        @endforeach
                    @endforeach
                </div>
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="btn-primary text-white px-6 py-2 rounded-lg font-semibold flex-1">
                    <i class="fas fa-paper-plane mr-2"></i>Enviar Mensagens
                </button>
                <button type="button" onclick="closeRemarketingModal()" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
let draggedElement = null;

function allowDrop(ev) {
    ev.preventDefault();
}

function drag(ev) {
    draggedElement = ev.target;
    ev.dataTransfer.effectAllowed = "move";
}

function drop(ev) {
    ev.preventDefault();
    const targetColumn = ev.currentTarget;
    const status = targetColumn.id.replace('kanban-', '');
    
    if (draggedElement) {
        const conversationId = draggedElement.getAttribute('data-conversation-id');
        const oldStatus = draggedElement.getAttribute('data-status');
        
        if (oldStatus !== status) {
            // Atualizar status no backend
            axios.post(`/crm/conversations/${conversationId}/status`, {
                kanban_status: status
            })
            .then(response => {
                // Mover o elemento para a nova coluna
                targetColumn.appendChild(draggedElement);
                draggedElement.setAttribute('data-status', status);
                
                // Atualizar contadores
                updateCounters();
            })
            .catch(error => {
                console.error('Erro ao atualizar status:', error);
                alert('Erro ao mover contato. Tente novamente.');
            });
        }
    }
}

function updateCounters() {
    // Recarregar a página após 500ms para atualizar contadores
    setTimeout(() => {
        location.reload();
    }, 500);
}

function openRemarketingModal() {
    document.getElementById('remarketingModal').classList.remove('hidden');
    // Carregar vozes se o checkbox de áudio estiver marcado
    if (document.getElementById('sendAsAudio').checked) {
        loadVoices();
    }
}

function closeRemarketingModal() {
    document.getElementById('remarketingModal').classList.add('hidden');
    document.getElementById('remarketingForm').reset();
    document.getElementById('selectedContacts').classList.add('hidden');
    document.getElementById('voiceSelection').classList.add('hidden');
}

// Carregar vozes disponíveis do ElevenLabs
function loadVoices() {
    const voiceSelect = document.getElementById('remarketingVoiceId');
    voiceSelect.innerHTML = '<option value="">Carregando vozes...</option>';
    voiceSelect.disabled = true;
    
    axios.get('/api/elevenlabs/voices')
        .then(response => {
            if (response.data.success && response.data.data) {
                const voices = response.data.data;
                voiceSelect.innerHTML = '<option value="">Usar voz padrão</option>';
                
                voices.forEach(voice => {
                    const option = document.createElement('option');
                    option.value = voice.voice_id;
                    option.textContent = `${voice.name}${voice.labels?.accent ? ' (' + voice.labels.accent + ')' : ''}`;
                    voiceSelect.appendChild(option);
                });
                
                voiceSelect.disabled = false;
            } else {
                voiceSelect.innerHTML = '<option value="">Erro ao carregar vozes</option>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar vozes:', error);
            voiceSelect.innerHTML = '<option value="">Erro ao carregar vozes. Use a voz padrão.</option>';
            voiceSelect.disabled = false;
        });
}

// Mostrar/esconder seleção de voz quando checkbox de áudio é alterado
document.getElementById('sendAsAudio').addEventListener('change', function() {
    const voiceSelection = document.getElementById('voiceSelection');
    if (this.checked) {
        voiceSelection.classList.remove('hidden');
        loadVoices();
    } else {
        voiceSelection.classList.add('hidden');
    }
});

document.getElementById('remarketingTarget').addEventListener('change', function() {
    if (this.value === 'selected') {
        document.getElementById('selectedContacts').classList.remove('hidden');
    } else {
        document.getElementById('selectedContacts').classList.add('hidden');
    }
});

function sendRemarketing(event) {
    event.preventDefault();
    
    const message = document.getElementById('remarketingMessage').value;
    const target = document.getElementById('remarketingTarget').value;
    const sendAsAudio = document.getElementById('sendAsAudio').checked;
    const selectedContacts = [];
    
    if (target === 'selected') {
        const checkboxes = document.querySelectorAll('input[name="selected_contacts[]"]:checked');
        checkboxes.forEach(cb => selectedContacts.push(cb.value));
        
        if (selectedContacts.length === 0) {
            alert('Selecione pelo menos um contato.');
            return;
        }
    }
    
    const messageType = sendAsAudio ? 'áudio' : 'texto';
    if (!confirm(`Tem certeza que deseja enviar esta mensagem como ${messageType} para ${target === 'all' ? 'todos os contatos' : target === 'selected' ? selectedContacts.length + ' contatos selecionados' : 'contatos em "' + target + '"'}?`)) {
        return;
    }
    
    // Obter voice_id selecionado (se houver)
    const voiceId = sendAsAudio ? document.getElementById('remarketingVoiceId').value : null;
    
    // Enviar para o backend
    axios.post('/api/remarketing/send', {
        message: message,
        target: target,
        contacts: selectedContacts,
        send_as_audio: sendAsAudio,
        voice_id: voiceId || null
    })
    .then(response => {
        alert('Mensagens enviadas com sucesso!');
        closeRemarketingModal();
    })
    .catch(error => {
        console.error('Erro ao enviar mensagens:', error);
        alert('Erro ao enviar mensagens. Verifique o console para mais detalhes.');
    });
}
</script>
@endsection

