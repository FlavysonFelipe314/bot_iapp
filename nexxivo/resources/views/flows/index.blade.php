@extends('layouts.app')

@section('title', 'Fluxos')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Fluxos do Bot</h1>
            <a href="{{ route('flows.create') }}" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                + Novo Fluxo
            </a>
        </div>

        <div class="divide-y divide-gray-200">
            @forelse($flows as $flow)
            <div class="p-6 hover:bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $flow->name }}</h3>
                            <span class="px-2 py-1 text-xs rounded-full {{ $flow->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $flow->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                            @if($flow->priority > 0)
                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                Prioridade: {{ $flow->priority }}
                            </span>
                            @endif
                        </div>
                        @if($flow->description)
                        <p class="mt-1 text-sm text-gray-600">{{ $flow->description }}</p>
                        @endif
                        <div class="mt-3">
                            <p class="text-sm font-medium text-gray-700">Gatilhos:</p>
                            <ul class="mt-1 text-sm text-gray-600 list-disc list-inside">
                                @foreach($flow->triggers as $trigger)
                                <li>
                                    @if($trigger['type'] === 'catch_all')
                                        🌐 Qualquer Mensagem
                                    @elseif(isset($trigger['value']))
                                        {{ ucfirst(str_replace('_', ' ', $trigger['type'])) }}: "{{ $trigger['value'] }}"
                                    @else
                                        {{ ucfirst(str_replace('_', ' ', $trigger['type'])) }}
                                    @endif
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="mt-3">
                            <p class="text-sm font-medium text-gray-700">Ações:</p>
                            <ul class="mt-1 text-sm text-gray-600 list-disc list-inside">
                                @foreach($flow->actions as $action)
                                <li>
                                    @if($action['type'] === 'send_message')
                                        📤 Enviar mensagem: "{{ substr($action['content'] ?? '', 0, 50) }}..."
                                    @elseif($action['type'] === 'wait')
                                        ⏱️ Aguardar {{ $action['duration'] ?? 0 }}ms
                                    @elseif($action['type'] === 'ai_response')
                                        🤖 Resposta com IA ({{ $action['provider'] ?? 'ollama' }})
                                        @if(!empty($action['use_context']))
                                            <span class="text-green-600">💬 [Contexto]</span>
                                        @endif
                                        @if(!empty($action['use_audio']))
                                            <span class="text-blue-600">🎵 [Áudio]</span>
                                        @endif
                                        @if(isset($action['prompt']))
                                            <br><span class="text-xs text-gray-500">Prompt: {{ substr($action['prompt'], 0, 50) }}...</span>
                                        @endif
                                    @elseif($action['type'] === 'conditional')
                                        🎯 Ação Condicional:
                                        <ul class="ml-4 mt-1 space-y-1 text-xs">
                                            @if(!empty($action['conditions']))
                                                @foreach($action['conditions'] as $condition)
                                                    <li>
                                                        @if(!empty($condition['default']))
                                                            ➜ <span class="text-gray-500">Padrão (Senão)</span>
                                                        @else
                                                            ➜ Se {{ ucfirst(str_replace('_', ' ', $condition['type'] ?? 'contains')) }}
                                                            @if(!empty($condition['value']))
                                                                "{{ $condition['value'] }}"
                                                            @endif
                                                        @endif
                                                        @if(!empty($condition['actions']))
                                                            <span class="text-gray-500">({{ count($condition['actions']) }} ação(ões))</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            @endif
                                        </ul>
                                    @endif
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="ml-4 flex gap-2">
                        <a href="{{ route('flows.edit', $flow->id) }}" class="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                            Editar
                        </a>
                        <button onclick="toggleFlow({{ $flow->id }}, {{ $flow->is_active ? 'false' : 'true' }})" class="px-4 py-2 text-sm {{ $flow->is_active ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' }} rounded-lg">
                            {{ $flow->is_active ? 'Desativar' : 'Ativar' }}
                        </button>
                        <button onclick="deleteFlow({{ $flow->id }})" class="px-4 py-2 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200">
                            Deletar
                        </button>
                    </div>
                </div>
            </div>
            @empty
            <div class="p-12 text-center">
                <p class="text-gray-500">Nenhum fluxo configurado ainda.</p>
                <a href="{{ route('flows.create') }}" class="mt-4 inline-block px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    Criar primeiro fluxo
                </a>
            </div>
            @endforelse
        </div>
    </div>
</div>

<script>
async function toggleFlow(id, isActive) {
    try {
        const response = await axios.put(`/api/flows/${id}`, {
            is_active: isActive
        });
        
        if (response.data.success) {
            location.reload();
        }
    } catch (error) {
        console.error('Erro ao atualizar fluxo:', error);
        alert('Erro ao atualizar fluxo. Tente novamente.');
    }
}

async function deleteFlow(id) {
    if (!confirm('Tem certeza que deseja deletar este fluxo?')) {
        return;
    }
    
    try {
        const response = await axios.delete(`/api/flows/${id}`);
        
        if (response.data.success) {
            location.reload();
        }
    } catch (error) {
        console.error('Erro ao deletar fluxo:', error);
        alert('Erro ao deletar fluxo. Tente novamente.');
    }
}
</script>
@endsection

