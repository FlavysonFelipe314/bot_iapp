@extends('layouts.app')

@section('title', 'Criar Fluxo')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-900">Criar Novo Fluxo</h1>
        </div>

        <form id="flow-form" class="p-6 space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nome do Fluxo</label>
                <input type="text" name="name" id="flow-name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                <textarea name="description" id="flow-description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Prioridade</label>
                <input type="number" name="priority" id="flow-priority" value="0" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="mt-1 text-sm text-gray-500">Fluxos com maior prioridade são executados primeiro</p>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">Gatilhos</label>
                    <button type="button" onclick="addTrigger()" class="text-sm text-blue-600 hover:text-blue-800">+ Adicionar</button>
                </div>
                <div id="triggers-container" class="space-y-2">
                    <!-- Triggers serão adicionados aqui -->
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">Ações</label>
                    <button type="button" onclick="addAction()" class="text-sm text-blue-600 hover:text-blue-800">+ Adicionar</button>
                </div>
                <div id="actions-container" class="space-y-3">
                    <!-- Actions serão adicionadas aqui -->
                </div>
            </div>

            <div class="flex items-center">
                <input type="checkbox" name="is_active" id="is_active" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="is_active" class="ml-2 block text-sm text-gray-700">Ativar fluxo imediatamente</label>
            </div>

            <div class="flex gap-4">
                <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    Criar Fluxo
                </button>
                <a href="{{ route('flows.index') }}" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
let triggerCount = 0;
let actionCount = 0;
const defaultProvider = @json($defaultProvider ?? 'ollama');

function addTrigger() {
    const container = document.getElementById('triggers-container');
    const div = document.createElement('div');
    div.className = 'flex gap-2 items-center p-3 border border-gray-300 rounded-lg';
    div.innerHTML = `
        <select name="triggers[${triggerCount}][type]" class="px-3 py-2 border border-gray-300 rounded-lg" onchange="updateTriggerInput(${triggerCount}, this.value)">
            <option value="exact">Exato</option>
            <option value="contains">Contém</option>
            <option value="starts_with">Começa com</option>
            <option value="catch_all">Qualquer Mensagem</option>
        </select>
        <input type="text" name="triggers[${triggerCount}][value]" id="trigger-value-${triggerCount}" placeholder="Texto do gatilho" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg">
        <button type="button" onclick="this.parentElement.remove()" class="px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">Remover</button>
    `;
    container.appendChild(div);
    triggerCount++;
}

function addAction() {
    const container = document.getElementById('actions-container');
    const div = document.createElement('div');
    div.className = 'border border-gray-300 rounded-lg p-4';
    div.id = `action-${actionCount}`;
    
    div.innerHTML = `
        <div class="flex gap-2 items-center mb-3">
            <select name="actions[${actionCount}][type]" id="action-type-${actionCount}" class="px-3 py-2 border border-gray-300 rounded-lg" onchange="updateActionType(${actionCount})">
                <option value="send_message">Enviar Mensagem</option>
                <option value="wait">Aguardar</option>
                <option value="ai_response">Resposta com IA</option>
            </select>
            <button type="button" onclick="document.getElementById('action-${actionCount}').remove()" class="px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">Remover</button>
        </div>
        <div id="action-content-${actionCount}" class="action-content">
            <input type="text" name="actions[${actionCount}][content]" placeholder="Mensagem a enviar" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
        </div>
    `;
    
    container.appendChild(div);
    actionCount++;
}

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

function updateActionType(actionIndex) {
    const select = document.getElementById(`action-type-${actionIndex}`);
    const contentDiv = document.getElementById(`action-content-${actionIndex}`);
    
    if (!select || !contentDiv) {
        console.error('Elementos não encontrados para ação', actionIndex);
        return;
    }
    
    const actionType = select.value;
    console.log(`Atualizando ação ${actionIndex} para tipo: ${actionType}`);
    
    if (actionType === 'send_message') {
        contentDiv.innerHTML = `
            <label class="block text-sm font-medium text-gray-700 mb-2">Mensagem</label>
            <input type="text" name="actions[${actionIndex}][content]" placeholder="Digite a mensagem a ser enviada" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
        `;
    } else if (actionType === 'wait') {
        contentDiv.innerHTML = `
            <label class="block text-sm font-medium text-gray-700 mb-2">Duração (milissegundos)</label>
            <input type="number" name="actions[${actionIndex}][duration]" placeholder="Ex: 1000" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
        `;
    } else if (actionType === 'ai_response') {
        const ollamaSelected = defaultProvider === 'ollama' ? 'selected' : '';
        const geminiSelected = defaultProvider === 'gemini' ? 'selected' : '';
        
        contentDiv.innerHTML = `
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Prompt da IA <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        name="actions[${actionIndex}][prompt]" 
                        id="action-prompt-${actionIndex}"
                        placeholder="Ex: Você é um assistente útil. Responda de forma amigável e concisa: {message}" 
                        required 
                        rows="4" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    ></textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        💡 Use <code class="bg-gray-200 px-1 rounded">{message}</code> para incluir a mensagem do usuário no prompt
                    </p>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Provedor de IA <span class="text-red-500">*</span>
                        </label>
                        <select 
                            name="actions[${actionIndex}][provider]" 
                            id="action-provider-${actionIndex}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                        >
                            <option value="ollama" ${ollamaSelected}>Ollama (Local)</option>
                            <option value="gemini" ${geminiSelected}>Google Gemini</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Modelo (opcional)
                        </label>
                        <input 
                            type="text" 
                            name="actions[${actionIndex}][model]" 
                            id="action-model-${actionIndex}"
                            placeholder="Ex: llama2, mistral, gemini-pro" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="text-xs text-gray-500 mt-1">Deixe vazio para usar o modelo padrão</p>
                    </div>
                </div>
                
                <div class="flex items-center p-3 bg-gray-50 rounded border border-gray-200">
                    <input 
                        type="checkbox" 
                        name="actions[${actionIndex}][show_typing]" 
                        id="show_typing_${actionIndex}" 
                        checked 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    >
                    <label for="show_typing_${actionIndex}" class="ml-2 block text-sm text-gray-700">
                        Mostrar "digitando..." enquanto a IA processa
                    </label>
                </div>
                
                <div class="flex items-center p-3 bg-green-50 rounded border border-green-200">
                    <input 
                        type="checkbox" 
                        name="actions[${actionIndex}][use_context]" 
                        id="use_context_${actionIndex}" 
                        class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded"
                    >
                    <label for="use_context_${actionIndex}" class="ml-2 block text-sm text-gray-700">
                        💬 Usar contexto da conversa (IA lembra mensagens anteriores)
                    </label>
                    <p class="text-xs text-gray-500 mt-1 ml-6">Recomendado para atendentes que precisam anotar pedidos</p>
                </div>
                
                <div class="flex items-center p-3 bg-blue-50 rounded border border-blue-200">
                    <input 
                        type="checkbox" 
                        name="actions[${actionIndex}][use_audio]" 
                        id="use_audio_${actionIndex}" 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    >
                    <label for="use_audio_${actionIndex}" class="ml-2 block text-sm text-gray-700">
                        🎵 Enviar resposta como áudio (usando ElevenLabs)
                    </label>
                </div>
                
                <div id="audio-options_${actionIndex}" class="hidden space-y-3 p-3 bg-blue-50 rounded border border-blue-200">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Voice ID (opcional)
                        </label>
                        <input 
                            type="text" 
                            name="actions[${actionIndex}][voice_id]" 
                            id="action-voice-${actionIndex}"
                            placeholder="Deixe vazio para usar o padrão" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="text-xs text-gray-500 mt-1">ID da voz do ElevenLabs (padrão será usado se vazio)</p>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Mensagem de erro (opcional)
                    </label>
                    <input 
                        type="text" 
                        name="actions[${actionIndex}][error_message]" 
                        id="action-error-${actionIndex}"
                        placeholder="Ex: Desculpe, não consegui processar sua mensagem." 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        📝 Palavras-chave sensíveis (opcional)
                    </label>
                    <input 
                        type="text" 
                        name="actions[${actionIndex}][sensitive_keywords]" 
                        id="action-sensitive-${actionIndex}"
                        placeholder="Ex: chave pix, nosso portfólio, link pix" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                    <p class="text-xs text-gray-500 mt-1">Separe por vírgula. Conteúdo contendo essas palavras será sempre enviado como texto (não áudio)</p>
                </div>
            </div>
        `;
        
        // Adicionar listener para mostrar/ocultar opções de áudio após inserir HTML
        setTimeout(() => {
            const useAudioCheckbox = document.getElementById(`use_audio_${actionIndex}`);
            const audioOptions = document.getElementById(`audio-options_${actionIndex}`);
            
            if (useAudioCheckbox && audioOptions) {
                useAudioCheckbox.addEventListener('change', function() {
                    audioOptions.classList.toggle('hidden', !this.checked);
                });
            }
        }, 100);
        
        console.log('✅ Campos de IA criados para ação', actionIndex);
    } else if (actionType === 'conditional') {
        contentDiv.innerHTML = `
            <div class="space-y-4 bg-purple-50 p-4 rounded-lg border border-purple-200">
                <p class="text-sm text-gray-700 mb-3">
                    <strong>🎯 Ação Condicional:</strong> Execute ações diferentes baseado no conteúdo da mensagem.
                </p>
                <div id="conditions-container-${actionIndex}" class="space-y-3">
                    <!-- Condições serão adicionadas aqui -->
                </div>
                <button type="button" onclick="addCondition(${actionIndex}, false)" class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 text-sm">
                    + Adicionar Condição
                </button>
                <button type="button" onclick="addCondition(${actionIndex}, true)" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm ml-2">
                    + Adicionar Padrão (Senão)
                </button>
            </div>
        `;
        
        // Adicionar condição padrão inicialmente
        setTimeout(() => addCondition(actionIndex, true), 100);
    }
}

document.getElementById('flow-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const form = e.target;
    const data = {
        name: document.getElementById('flow-name').value.trim(),
        description: document.getElementById('flow-description').value.trim() || null,
        priority: parseInt(document.getElementById('flow-priority').value) || 0,
        is_active: document.getElementById('is_active').checked,
        triggers: [],
        actions: []
    };
    
    // Validar nome
    if (!data.name) {
        alert('Por favor, preencha o nome do fluxo.');
        document.getElementById('flow-name').focus();
        return;
    }
    
    // Coletar triggers
    const triggerContainer = document.getElementById('triggers-container');
    const triggerElements = triggerContainer.querySelectorAll('div');
    triggerElements.forEach(triggerDiv => {
        const typeSelect = triggerDiv.querySelector('select[name^="triggers["]');
        const valueInput = triggerDiv.querySelector('input[name^="triggers["]');
        
        if (typeSelect) {
            const trigger = { type: typeSelect.value };
            // Se não for catch_all, adicionar valor
            if (trigger.type !== 'catch_all' && valueInput && valueInput.value.trim()) {
                trigger.value = valueInput.value.trim();
                data.triggers.push(trigger);
            } else if (trigger.type === 'catch_all') {
                // catch_all não precisa de valor
                data.triggers.push(trigger);
            }
        }
    });
    
    if (data.triggers.length === 0) {
        alert('Por favor, adicione pelo menos um gatilho.');
        return;
    }
    
    // Coletar actions
    const actionContainer = document.getElementById('actions-container');
    const actionElements = actionContainer.querySelectorAll('[id^="action-"]');
    
    actionElements.forEach(actionDiv => {
        const actionIndex = actionDiv.id.replace('action-', '');
        const typeSelect = document.getElementById(`action-type-${actionIndex}`);
        
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
            const promptTextarea = document.getElementById(`action-prompt-${actionIndex}`);
            const providerSelect = document.getElementById(`action-provider-${actionIndex}`);
            const modelInput = document.getElementById(`action-model-${actionIndex}`);
            const showTypingCheckbox = document.getElementById(`show_typing_${actionIndex}`);
            const useAudioCheckbox = document.getElementById(`use_audio_${actionIndex}`);
            const voiceIdInput = document.getElementById(`action-voice-${actionIndex}`);
            const errorMessageInput = document.getElementById(`action-error-${actionIndex}`);
            
            if (!promptTextarea || !promptTextarea.value.trim()) {
                alert(`Por favor, preencha o prompt da IA para a ação ${parseInt(actionIndex) + 1}.`);
                if (promptTextarea) promptTextarea.focus();
                return;
            }
            
            action.prompt = promptTextarea.value.trim();
            action.provider = providerSelect ? providerSelect.value : defaultProvider;
            
            if (modelInput && modelInput.value.trim()) {
                action.model = modelInput.value.trim();
            }
            
            action.show_typing = showTypingCheckbox ? showTypingCheckbox.checked : true;
            
            const useContextCheckbox = document.getElementById(`use_context_${actionIndex}`);
            action.use_context = useContextCheckbox ? useContextCheckbox.checked : false;
            
            action.use_audio = useAudioCheckbox ? useAudioCheckbox.checked : false;
            
            if (useAudioCheckbox && useAudioCheckbox.checked && voiceIdInput && voiceIdInput.value.trim()) {
                action.voice_id = voiceIdInput.value.trim();
            }
            
            if (errorMessageInput && errorMessageInput.value.trim()) {
                action.error_message = errorMessageInput.value.trim();
            }
            
            // Processar palavras-chave sensíveis (separar por vírgula e limpar espaços)
            const sensitiveKeywordsInput = document.getElementById(`action-sensitive-${actionIndex}`);
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
        } else if (action.type === 'conditional') {
            // Coletar condições
            action.conditions = [];
            const conditionsContainer = document.getElementById(`conditions-container-${actionIndex}`);
            if (conditionsContainer) {
                const conditionElements = conditionsContainer.querySelectorAll('[id^="condition-"]');
                conditionElements.forEach(conditionDiv => {
                    const conditionId = conditionDiv.id;
                    const conditionIndexMatch = conditionId.match(/condition-(\d+)-(\d+)/);
                    if (!conditionIndexMatch) return;
                    
                    const condition = {};
                    
                    // Verificar se é condição padrão
                    const defaultInput = conditionDiv.querySelector('input[type="hidden"][value="true"]');
                    if (defaultInput) {
                        condition.default = true;
                    } else {
                        // Coletar tipo e valor
                        const typeSelect = conditionDiv.querySelector('select[name*="[type]"]');
                        const valueInput = conditionDiv.querySelector('input[name*="[value]"]');
                        
                        if (typeSelect) condition.type = typeSelect.value;
                        if (valueInput && valueInput.value.trim()) {
                            condition.value = valueInput.value.trim();
                        }
                    }
                    
                    // Coletar ações da condição
                    condition.actions = [];
                    const actionsContainer = document.getElementById(`condition-actions-${actionIndex}-${conditionIndexMatch[2]}`);
                    if (actionsContainer) {
                        const actionElements = actionsContainer.querySelectorAll('.flex.gap-2');
                        actionElements.forEach(actionEl => {
                            const actionTypeSelect = actionEl.querySelector('select[name*="[type]"]');
                            if (!actionTypeSelect) return;
                            
                            const subAction = { type: actionTypeSelect.value };
                            
                            if (subAction.type === 'send_message') {
                                const contentInput = actionEl.querySelector('input[name*="[content]"]');
                                if (contentInput && contentInput.value.trim()) {
                                    subAction.content = contentInput.value.trim();
                                }
                            } else if (subAction.type === 'wait') {
                                const durationInput = actionEl.querySelector('input[name*="[duration]"]');
                                if (durationInput && durationInput.value) {
                                    subAction.duration = parseInt(durationInput.value) || 0;
                                }
                            } else if (subAction.type === 'ai_response') {
                                const promptTextarea = actionEl.querySelector('textarea[name*="[prompt]"]');
                                if (promptTextarea && promptTextarea.value.trim()) {
                                    subAction.prompt = promptTextarea.value.trim();
                                    subAction.provider = defaultProvider;
                                }
                            }
                            
                            if (Object.keys(subAction).length > 1) { // Tem pelo menos type e outro campo
                                condition.actions.push(subAction);
                            }
                        });
                    }
                    
                    if (condition.default || (condition.type && condition.actions.length > 0)) {
                        action.conditions.push(condition);
                    }
                });
            }
            
            if (action.conditions && action.conditions.length > 0) {
                data.actions.push(action);
            }
        }
    });
    
    if (data.actions.length === 0) {
        alert('Por favor, adicione pelo menos uma ação.');
        return;
    }
    
    console.log('Dados a serem enviados:', JSON.stringify(data, null, 2));
    
    try {
        const response = await axios.post('/api/flows', data, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        
        if (response.data.success) {
            window.location.href = '/flows';
        }
    } catch (error) {
        console.error('Erro ao criar fluxo:', error);
        
        let errorMessage = 'Erro ao criar fluxo. Verifique os dados e tente novamente.';
        
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

let conditionCount = {};

function addCondition(actionIndex, isDefault = false) {
    if (!conditionCount[actionIndex]) {
        conditionCount[actionIndex] = 0;
    }
    
    const container = document.getElementById(`conditions-container-${actionIndex}`);
    if (!container) return;
    
    const conditionIndex = conditionCount[actionIndex]++;
    const conditionDiv = document.createElement('div');
    conditionDiv.className = 'border border-purple-300 rounded-lg p-4 bg-white';
    conditionDiv.id = `condition-${actionIndex}-${conditionIndex}`;
    
    if (isDefault) {
        conditionDiv.innerHTML = `
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-semibold text-purple-700">Condição Padrão (Senão)</h4>
                <input type="hidden" name="actions[${actionIndex}][conditions][${conditionIndex}][default]" value="true">
            </div>
            <div class="mb-3">
                <p class="text-xs text-gray-600">Executada se nenhuma outra condição for verdadeira</p>
            </div>
            <div id="condition-actions-${actionIndex}-${conditionIndex}" class="space-y-2">
                <!-- Ações serão adicionadas aqui -->
            </div>
            <button type="button" onclick="addConditionAction(${actionIndex}, ${conditionIndex})" class="mt-2 px-3 py-1 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">
                + Adicionar Ação
            </button>
        `;
    } else {
        conditionDiv.innerHTML = `
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-semibold text-purple-700">Condição ${conditionIndex + 1}</h4>
                <button type="button" onclick="document.getElementById('condition-${actionIndex}-${conditionIndex}').remove()" class="px-2 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200">
                    Remover
                </button>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tipo</label>
                    <select name="actions[${actionIndex}][conditions][${conditionIndex}][type]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                        <option value="contains">Contém</option>
                        <option value="exact">Exato</option>
                        <option value="starts_with">Começa com</option>
                        <option value="regex">Expressão Regular</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Valor</label>
                    <input type="text" name="actions[${actionIndex}][conditions][${conditionIndex}][value]" placeholder="Ex: pix, portfólio" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                </div>
            </div>
            <div id="condition-actions-${actionIndex}-${conditionIndex}" class="space-y-2">
                <!-- Ações serão adicionadas aqui -->
            </div>
            <button type="button" onclick="addConditionAction(${actionIndex}, ${conditionIndex})" class="mt-2 px-3 py-1 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">
                + Adicionar Ação
            </button>
        `;
    }
    
    container.appendChild(conditionDiv);
    // Adicionar uma ação inicial
    setTimeout(() => addConditionAction(actionIndex, conditionIndex), 100);
}

function addConditionAction(actionIndex, conditionIndex) {
    const container = document.getElementById(`condition-actions-${actionIndex}-${conditionIndex}`);
    if (!container) return;
    
    const actionDiv = document.createElement('div');
    actionDiv.className = 'flex gap-2 items-center p-2 bg-gray-50 rounded border border-gray-200';
    const actionId = `${actionIndex}-${conditionIndex}-${Date.now()}`;
    
    actionDiv.innerHTML = `
        <select name="actions[${actionIndex}][conditions][${conditionIndex}][actions][][type]" class="flex-1 px-2 py-1 border border-gray-300 rounded text-sm" onchange="updateConditionActionType(this, '${actionId}')">
            <option value="send_message">Enviar Mensagem</option>
            <option value="ai_response">Resposta com IA</option>
            <option value="wait">Aguardar</option>
        </select>
        <div id="condition-action-content-${actionId}" class="flex-1">
            <input type="text" name="actions[${actionIndex}][conditions][${conditionIndex}][actions][][content]" placeholder="Mensagem" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="px-2 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200">
            ✕
        </button>
    `;
    
    container.appendChild(actionDiv);
}

function updateConditionActionType(select, actionId) {
    const contentDiv = document.getElementById(`condition-action-content-${actionId}`);
    if (!contentDiv) return;
    
    const type = select.value;
    const actionIndex = actionId.split('-')[0];
    const conditionIndex = actionId.split('-')[1];
    
    if (type === 'send_message') {
        contentDiv.innerHTML = `
            <input type="text" name="actions[${actionIndex}][conditions][${conditionIndex}][actions][][content]" placeholder="Mensagem" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
        `;
    } else if (type === 'wait') {
        contentDiv.innerHTML = `
            <input type="number" name="actions[${actionIndex}][conditions][${conditionIndex}][actions][][duration]" placeholder="ms" min="0" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
        `;
    } else if (type === 'ai_response') {
        contentDiv.innerHTML = `
            <textarea name="actions[${actionIndex}][conditions][${conditionIndex}][actions][][prompt]" placeholder="Prompt da IA" rows="2" class="w-full px-2 py-1 border border-gray-300 rounded text-sm"></textarea>
        `;
    }
}

// Adicionar um trigger e uma ação por padrão
addTrigger();
addAction();
</script>
@endsection
