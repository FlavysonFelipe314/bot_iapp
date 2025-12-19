@extends('layouts.app')

@section('title', 'Conversas')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<script>
// Auto-refresh a cada 10 segundos para atualizar status do bot
let lastStatus = '{{ $instances->first()->status ?? 'unknown' }}';
setInterval(function() {
    if (document.visibilityState === 'visible') {
        // Recarregar página para atualizar status
        location.reload();
    }
}, 10000); // 10 segundos
</script>
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-900">Conversas</h1>
        </div>

        <!-- QR Code Section -->
        @if($instances->count() > 0)
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold mb-4">Status do Bot</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($instances as $instance)
                <div class="border rounded-lg p-4">
                    <h3 class="font-semibold">{{ $instance->instance_name }}</h3>
                    <p class="text-sm text-gray-600">Status: 
                        <span class="font-medium 
                            @if($instance->status === 'connected') text-green-600
                            @elseif($instance->status === 'started') text-yellow-600
                            @else text-red-600
                            @endif">
                            {{ ucfirst($instance->status) }}
                        </span>
                    </p>
                    @if($instance->qrcode)
                    <div class="mt-4">
                        @if(str_starts_with($instance->qrcode, 'data:image'))
                            <img src="{{ $instance->qrcode }}" alt="QR Code" class="w-48 h-48 mx-auto border">
                        @else
                            <img src="data:image/png;base64,{{ $instance->qrcode }}" alt="QR Code" class="w-48 h-48 mx-auto border">
                        @endif
                        <p class="text-xs text-gray-500 mt-2 text-center">Escaneie com o WhatsApp</p>
                    </div>
                    @elseif($instance->status !== 'connected')
                    <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded">
                        <p class="text-sm text-yellow-800">Aguardando QR Code... O bot precisa gerar um novo QR Code para conectar.</p>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Conversations List -->
        <div class="divide-y divide-gray-200">
            @forelse($conversations as $conversation)
            <a href="{{ route('chat.show', $conversation->id) }}" class="block p-6 hover:bg-gray-50 transition">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center">
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ $conversation->contact_name ?? $conversation->contact }}
                            </h3>
                            <span class="ml-2 text-sm text-gray-500">{{ $conversation->contact }}</span>
                        </div>
                        @if($conversation->latestMessage)
                        <p class="mt-1 text-sm text-gray-600 truncate">
                            {{ $conversation->latestMessage->message }}
                        </p>
                        @endif
                    </div>
                    <div class="ml-4 text-right">
                        @if($conversation->last_message_at)
                        <p class="text-sm text-gray-500">
                            {{ $conversation->last_message_at->diffForHumans() }}
                        </p>
                        @endif
                    </div>
                </div>
            </a>
            @empty
            <div class="p-12 text-center">
                <p class="text-gray-500">Nenhuma conversa ainda.</p>
            </div>
            @endforelse
        </div>

        @if($conversations->hasPages())
        <div class="p-6 border-t border-gray-200">
            {{ $conversations->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

