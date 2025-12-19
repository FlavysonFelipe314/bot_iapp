@extends('layouts.app')

@section('title', 'Editar Fluxo')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-900">Editar Fluxo</h1>
        </div>

        <form id="flow-form" class="p-6 space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nome do Fluxo</label>
                <input type="text" name="name" value="{{ $flow->name }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">{{ $flow->description }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Prioridade</label>
                <input type="number" name="priority" value="{{ $flow->priority }}" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">Gatilhos</label>
                    <button type="button" onclick="addTrigger()" class="text-sm text-blue-600 hover:text-blue-800">+ Adicionar</button>
                </div>
                <div id="triggers-container" class="space-y-2">
                    @foreach($flow->triggers as $index => $trigger)
                    <div class="flex gap-2 items-center p-3 border border-gray-300 rounded-lg">
                        <select name="triggers[{{ $index }}][type]" id="trigger-type-{{ $index }}" class="px-3 py-2 border border-gray-300 rounded-lg" onchange="updateTriggerInput({{ $index }}, this.value)">
                            <option value="exact" {{ $trigger['type'] === 'exact' ? 'selected' : '' }}>Exato</option>
                            <option value="contains" {{ $trigger['type'] === 'contains' ? 'selected' : '' }}>Contém</option>
                            <option value="starts_with" {{ $trigger['type'] === 'starts_with' ? 'selected' : '' }}>Começa com</option>
                            <option value="catch_all" {{ $trigger['type'] === 'catch_all' ? 'selected' : '' }}>Qualquer Mensagem</option>
                        </select>
                        <input type="text" name="triggers[{{ $index }}][value]" id="trigger-value-{{ $index }}" value="{{ $trigger['value'] ?? '' }}" placeholder="Texto do gatilho" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg" {{ $trigger['type'] === 'catch_all' ? 'disabled' : 'required' }}>
                        <button type="button" onclick="this.parentElement.remove()" class="px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">Remover</button>
                    </div>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">Ações</label>
                    <button type="button" onclick="addAction()" class="text-sm text-blue-600 hover:text-blue-800">+ Adicionar</button>
                </div>
                <div id="actions-container" class="space-y-2">
                    @foreach($flow->actions as $index => $action)
                    <div class="border border-gray-300 rounded-lg p-4 space-y-3">
                        <div class="flex gap-2 items-center">
                            <select name="actions[{{ $index }}][type]" id="action-type-{{ $index }}" class="px-3 py-2 border border-gray-300 rounded-lg" onchange="updateActionType({{ $index }})">
                                <option value="send_message" {{ $action['type'] === 'send_message' ? 'selected' : '' }}>Enviar Mensagem</option>
                                <option value="wait" {{ $action['type'] === 'wait' ? 'selected' : '' }}>Aguardar</option>
                                <option value="ai_response" {{ $action['type'] === 'ai_response' ? 'selected' : '' }}>Resposta com IA</option>
                                <option value="conditional" {{ $action['type'] === 'conditional' ? 'selected' : '' }}>Ação Condicional (Se/Então)</option>
                            </select>
                            <button type="button" onclick="this.closest('.border').remove()" class="px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">Remover</button>
                        </div>
                        <div class="flex-1" id="action-content-{{ $index }}">
                            @if($action['type'] === 'send_message')
                            <input type="text" name="actions[{{ $index }}][content]" value="{{ $action['content'] ?? '' }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            @elseif($action['type'] === 'wait')
                            <input type="number" name="actions[{{ $index }}][duration]" value="{{ $action['duration'] ?? 0 }}" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            @elseif($action['type'] === 'ai_response')
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Prompt da IA</label>
                                    <textarea name="actions[{{ $index }}][prompt]" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg">{{ $action['prompt'] ?? '' }}</textarea>
                                    <p class="text-xs text-gray-500 mt-1">Use {message} para incluir a mensagem do usuário</p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Provedor</label>
                                        <select name="actions[{{ $index }}][provider]" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                            <option value="ollama" {{ ($action['provider'] ?? 'ollama') === 'ollama' ? 'selected' : '' }}>Ollama</option>
                                            <option value="gemini" {{ ($action['provider'] ?? '') === 'gemini' ? 'selected' : '' }}>Google Gemini</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Modelo (opcional)</label>
                                        <input type="text" name="actions[{{ $index }}][model]" value="{{ $action['model'] ?? '' }}" placeholder="Ex: llama2, gemini-pro" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="actions[{{ $index }}][show_typing]" id="show_typing_{{ $index }}" {{ ($action['show_typing'] ?? true) ? 'checked' : '' }} class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="show_typing_{{ $index }}" class="ml-2 block text-sm text-gray-700">Mostrar "digitando..." enquanto processa</label>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mensagem de erro (opcional)</label>
                                    <input type="text" name="actions[{{ $index }}][error_message]" value="{{ $action['error_message'] ?? '' }}" placeholder="Mensagem a enviar se a IA falhar" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">📝 Palavras-chave sensíveis (opcional)</label>
                                    <input type="text" name="actions[{{ $index }}][sensitive_keywords]" value="{{ isset($action['sensitive_keywords']) ? (is_array($action['sensitive_keywords']) ? implode(', ', $action['sensitive_keywords']) : $action['sensitive_keywords']) : '' }}" placeholder="Ex: chave pix, link pix, código" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <p class="text-xs text-gray-500 mt-1">Separe por vírgula. Conteúdo contendo essas palavras será sempre enviado como texto (não áudio)</p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center">
                <input type="checkbox" name="is_active" id="is_active" {{ $flow->is_active ? 'checked' : '' }} class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="is_active" class="ml-2 block text-sm text-gray-700">Ativar fluxo</label>
            </div>

            <div class="flex gap-4">
                <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    Salvar Alterações
                </button>
                <a href="{{ route('flows.index') }}" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
let triggerCount = {{ count($flow->triggers) }};
let actionCount = {{ count($flow->actions) }};
const defaultProvider = @json($defaultProvider ?? 'ollama');

// Inicializar campos quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    // Atualizar campos de trigger para catch_all
    @foreach($flow->triggers as $index => $trigger)
        @if($trigger['type'] === 'catch_all')
            updateTriggerInput({{ $index }}, 'catch_all');
        @endif
    @endforeach
    
    // Atualizar campos de ação baseado no tipo
    @foreach($flow->actions as $index => $action)
        updateActionType({{ $index }});
    @endforeach
});

function updateTriggerInput(triggerIndex, type) {
    const valueInput = document.getElementById(`trigger-value-${triggerIndex}`);
    if (!valueInput) return;
    
    if (type === 'catch_all') {
        valueInput.disabled = true;
        valueInput.required = false;
        valueInput.placeholder = 'Aplicado a qualquer mensagem';
        valueInput.value = '';
    } else {
        valueInput.disabled = false;
        valueInput.required = true;
        valueInput.placeholder = 'Texto do gatilho';
    }
}

function addTrigger() {
    const container = document.getElementById('triggers-container');
    const div = document.createElement('div');
    div.className = 'flex gap-2 items-center p-3 border border-gray-300 rounded-lg';
    div.innerHTML = `
        <select name="triggers[${triggerCount}][type]" id="trigger-type-${triggerCount}" class="px-3 py-2 border border-gray-300 rounded-lg" onchange="updateTriggerInput(${triggerCount}, this.value)">
            <option value="exact">Exato</option>
            <option value="contains">Contém</option>
            <option value="starts_with">Começa com</option>
            <option value="catch_all">Qualquer Mensagem</option>
        </select>
        <input type="text" name="triggers[${triggerCount}][value]" id="trigger-value-${triggerCount}" placeholder="Texto do gatilho" required class="flex-1 px-3 py-2 border border-gray-300 rounded-lg">
        <button type="button" onclick="this.parentElement.remove()" class="px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">Remover</button>
    `;
    container.appendChild(div);
    triggerCount++;
}

function addAction() {
    const container = document.getElementById('actions-container');
    const div = document.createElement('div');
    div.className = 'border border-gray-300 rounded-lg p-4 space-y-3';
    div.innerHTML = `
        <div class="flex gap-2 items-center">
            <select name="actions[${actionCount}][type]" class="px-3 py-2 border border-gray-300 rounded-lg" onchange="updateActionType(this)">
                <option value="send_message">Enviar Mensagem</option>
                <option value="wait">Aguardar</option>
                <option value="ai_response">Resposta com IA</option>
            </select>
            <button type="button" onclick="this.closest('.border').remove()" class="px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">Remover</button>
        </div>
        <div class="flex-1" id="action-content-${actionCount}">
            <input type="text" name="actions[${actionCount}][content]" placeholder="Mensagem a enviar" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
        </div>
    `;
    container.appendChild(div);
    actionCount++;
}

function updateActionType(select) {
    const actionDiv = select.closest('.border');
    const contentDiv = actionDiv.querySelector('[id^="action-content-"]');
    const actionIndex = select.name.match(/\[(\d+)\]/)[1];
    
    if (select.value === 'send_message') {
        contentDiv.innerHTML = `
            <input type="text" name="actions[${actionIndex}][content]" placeholder="Mensagem a enviar" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
        `;
    } else if (select.value === 'wait') {
        contentDiv.innerHTML = `
            <input type="number" name="actions[${actionIndex}][duration]" placeholder="Duração (ms)" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
        `;
    } else if (select.value === 'ai_response') {
        contentDiv.innerHTML = `
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prompt da IA</label>
                    <textarea name="actions[${actionIndex}][prompt]" placeholder="Ex: Você é um assistente útil. Responda de forma amigável: {message}" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></textarea>
                    <p class="text-xs text-gray-500 mt-1">Use {message} para incluir a mensagem do usuário</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Provedor</label>
                        <select name="actions[${actionIndex}][provider]" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="ollama" ${defaultProvider === 'ollama' ? 'selected' : ''}>Ollama</option>
                            <option value="gemini" ${defaultProvider === 'gemini' ? 'selected' : ''}>Google Gemini</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Modelo (opcional)</label>
                        <input type="text" name="actions[${actionIndex}][model]" placeholder="Ex: llama2, gemini-pro" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="actions[${actionIndex}][show_typing]" id="show_typing_${actionIndex}" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="show_typing_${actionIndex}" class="ml-2 block text-sm text-gray-700">Mostrar "digitando..." enquanto processa</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mensagem de erro (opcional)</label>
                    <input type="text" name="actions[${actionIndex}][error_message]" placeholder="Mensagem a enviar se a IA falhar" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
            </div>
        `;
    }
}

document.getElementById('flow-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const form = e.target;
    const data = {
        name: form.querySelector('input[name="name"]').value,
        description: form.querySelector('textarea[name="description"]').value || null,
        priority: parseInt(form.querySelector('input[name="priority"]').value) || 0,
        is_active: form.querySelector('input[name="is_active"]').checked,
        triggers: [],
        actions: []
    };
    
    // Coletar triggers - iterar pelos elementos do container
    const triggerContainer = document.getElementById('triggers-container');
    const triggerElements = triggerContainer.querySelectorAll('div');
    triggerElements.forEach(triggerDiv => {
        const typeSelect = triggerDiv.querySelector('select[name^="triggers["]');
        const valueInput = triggerDiv.querySelector('input[name^="triggers["]');
        
        if (typeSelect) {
            const trigger = { type: typeSelect.value };
            // Se não for catch_all, adicionar valor se existir
            if (trigger.type !== 'catch_all') {
                if (valueInput && valueInput.value.trim()) {
                    trigger.value = valueInput.value.trim();
                    data.triggers.push(trigger);
                }
            } else {
                // catch_all não precisa de valor
                data.triggers.push(trigger);
            }
        }
    });
    
    // Coletar actions - iterar pelos elementos do container
    const actionContainer = document.getElementById('actions-container');
    const actionElements = actionContainer.querySelectorAll('div.border');
    actionElements.forEach(actionDiv => {
        const typeSelect = actionDiv.querySelector('select[name^="actions["]');
        if (!typeSelect) return;
        
        const action = { type: typeSelect.value };
        
        if (action.type === 'send_message') {
            const contentInput = actionDiv.querySelector('input[name*="[content]"]');
            if (contentInput && contentInput.value.trim()) {
                action.content = contentInput.value.trim();
                data.actions.push(action);
            }
        } else if (action.type === 'wait') {
            const durationInput = actionDiv.querySelector('input[name*="[duration]"]');
            if (durationInput && durationInput.value) {
                action.duration = parseInt(durationInput.value) || 0;
                data.actions.push(action);
            }
        } else if (action.type === 'ai_response') {
            const promptTextarea = actionDiv.querySelector('textarea[name*="[prompt]"]');
            const providerSelect = actionDiv.querySelector('select[name*="[provider]"]');
            const modelInput = actionDiv.querySelector('input[name*="[model]"]');
            const showTypingCheckbox = actionDiv.querySelector('input[name*="[show_typing]"]');
            const useAudioCheckbox = actionDiv.querySelector('input[name*="[use_audio]"]');
            const useContextCheckbox = actionDiv.querySelector('input[name*="[use_context]"]');
            const voiceIdInput = actionDiv.querySelector('input[name*="[voice_id]"]');
            const errorMessageInput = actionDiv.querySelector('input[name*="[error_message]"]');
            const sensitiveKeywordsInput = actionDiv.querySelector('input[name*="[sensitive_keywords]"]');
            
            if (promptTextarea && promptTextarea.value.trim()) {
                action.prompt = promptTextarea.value.trim();
                action.provider = providerSelect ? providerSelect.value : defaultProvider;
                
                if (modelInput && modelInput.value.trim()) {
                    action.model = modelInput.value.trim();
                }
                
                action.show_typing = showTypingCheckbox ? showTypingCheckbox.checked : true;
                action.use_context = useContextCheckbox ? useContextCheckbox.checked : false;
                action.use_audio = useAudioCheckbox ? useAudioCheckbox.checked : false;
                
                if (useAudioCheckbox && useAudioCheckbox.checked && voiceIdInput && voiceIdInput.value.trim()) {
                    action.voice_id = voiceIdInput.value.trim();
                }
                
                if (errorMessageInput && errorMessageInput.value.trim()) {
                    action.error_message = errorMessageInput.value.trim();
                }
                
                // Processar palavras-chave sensíveis (separar por vírgula e limpar espaços)
                if (sensitiveKeywordsInput) {
                    if (sensitiveKeywordsInput.value.trim()) {
                        action.sensitive_keywords = sensitiveKeywordsInput.value.split(',')
                            .map(k => k.trim())
                            .filter(k => k.length > 0);
                    } else {
                        // Enviar array vazio se não houver valor
                        action.sensitive_keywords = [];
                    }
                }
                
                data.actions.push(action);
            }
        } else if (action.type === 'conditional') {
            // TODO: Implementar coleta de ações condicionais
            // Por enquanto, pular
        }
    });
    
    // Validações básicas
    if (!data.name || data.name.trim() === '') {
        alert('Por favor, preencha o nome do fluxo.');
        return;
    }
    
    if (data.triggers.length === 0) {
        alert('Por favor, adicione pelo menos um gatilho.');
        return;
    }
    
    if (data.actions.length === 0) {
        alert('Por favor, adicione pelo menos uma ação.');
        return;
    }
    
    try {
        const response = await axios.put(`/api/flows/{{ $flow->id }}`, data, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        
        if (response.data.success) {
            window.location.href = '/flows';
        }
    } catch (error) {
        console.error('Erro ao atualizar fluxo:', error);
        
        let errorMessage = 'Erro ao atualizar fluxo. Verifique os dados e tente novamente.';
        
        if (error.response?.data?.errors) {
            const errors = error.response.data.errors;
            const errorList = Object.values(errors).flat().join('\n');
            errorMessage = `Erros de validação:\n${errorList}`;
        } else if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        }
        
        alert(errorMessage);
    }
});
</script>
@endsection

