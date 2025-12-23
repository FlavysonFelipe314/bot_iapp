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
    <div class="card-modern p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent">
                <i class="fas fa-comments mr-3"></i>Conversas
            </h1>
            <a href="/crm" class="btn-primary text-white px-6 py-2 rounded-lg font-semibold">
                <i class="fas fa-columns mr-2"></i>Ver CRM Kanban
            </a>
        </div>

        <!-- QR Code Section -->
        @if($instances->count() > 0)
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4"><i class="fas fa-robot mr-2"></i>Status do Bot</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($instances as $instance)
                <div class="card-modern p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-gray-800">{{ $instance->instance_name }}</h3>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold
                            @if($instance->status === 'connected') bg-green-100 text-green-700
                            @elseif($instance->status === 'started') bg-yellow-100 text-yellow-700
                            @else bg-red-100 text-red-700
                            @endif">
                            <i class="fas fa-circle text-xs mr-1"></i>{{ ucfirst($instance->status) }}
                        </span>
                    </div>
                    @if($instance->qrcode)
                    <div class="mt-4">
                        @if(str_starts_with($instance->qrcode, 'data:image'))
                            <img src="{{ $instance->qrcode }}" alt="QR Code" class="w-48 h-48 mx-auto border-2 border-purple-200 rounded-lg shadow-lg">
                        @else
                            <img src="data:image/png;base64,{{ $instance->qrcode }}" alt="QR Code" class="w-48 h-48 mx-auto border-2 border-purple-200 rounded-lg shadow-lg">
                        @endif
                        <p class="text-xs text-gray-600 mt-3 text-center font-medium">Escaneie com o WhatsApp</p>
                    </div>
                    @elseif($instance->status !== 'connected')
                    <div class="mt-4 p-4 bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm text-yellow-800"><i class="fas fa-clock mr-2"></i>Aguardando QR Code...</p>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Conversations List -->
        <div class="space-y-3">
            @forelse($conversations as $conversation)
            <a href="{{ route('chat.show', $conversation->id) }}" class="card-modern block p-5 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-purple-500 to-indigo-500 flex items-center justify-center text-white font-bold text-lg mr-3">
                                {{ strtoupper(substr($conversation->contact_name ?? $conversation->contact, 0, 1)) }}
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">
                                    {{ $conversation->contact_name ?? $conversation->contact }}
                                </h3>
                                <span class="text-sm text-gray-500"><i class="fas fa-phone mr-1"></i>{{ $conversation->contact }}</span>
                            </div>
                        </div>
                        @if($conversation->latestMessage)
                        <p class="mt-2 text-sm text-gray-600 truncate pl-15">
                            <i class="fas fa-comment-dots mr-2 text-purple-500"></i>{{ \Illuminate\Support\Str::limit($conversation->latestMessage->message, 80) }}
                        </p>
                        @endif
                    </div>
                    <div class="ml-4 text-right">
                        @if($conversation->last_message_at)
                        <p class="text-sm text-gray-500 font-medium">
                            <i class="fas fa-clock mr-1"></i>{{ $conversation->last_message_at->diffForHumans() }}
                        </p>
                        @endif
                    </div>
                </div>
            </a>
            @empty
            <div class="card-modern p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">Nenhuma conversa ainda.</p>
            </div>
            @endforelse
        </div>

        @if($conversations->hasPages())
        <div class="mt-6 flex justify-center">
            {{ $conversations->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

