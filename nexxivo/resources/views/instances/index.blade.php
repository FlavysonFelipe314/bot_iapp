@extends('layouts.app')

@section('title', 'Minhas instâncias')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="card-modern p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent">
                <i class="fas fa-mobile-alt mr-3"></i>Minhas instâncias
            </h1>
            <button type="button" onclick="document.getElementById('form-new').classList.toggle('hidden')" class="btn-primary text-white px-6 py-2 rounded-lg font-semibold">
                <i class="fas fa-plus mr-2"></i>Nova instância
            </button>
        </div>

        @if(session('success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
            {{ session('success') }}
        </div>
        @endif
        @if($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
            <p class="font-semibold mb-1"><i class="fas fa-exclamation-circle mr-2"></i>Não foi possível criar a instância:</p>
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div id="form-new" class="{{ $errors->any() ? '' : 'hidden' }} mb-6 p-5 bg-gray-50 rounded-xl border border-gray-200">
            <form id="form-create-instance" action="{{ route('instances.store') }}" method="POST" class="flex flex-wrap items-end gap-4" novalidate>
                @csrf
                <div class="flex-1 min-w-[200px]">
                    <label for="instance-name" class="block text-sm font-medium text-gray-700 mb-1">Nome (apenas letras, números, _ e -)</label>
                    <input id="instance-name" type="text" name="name" value="{{ old('name') }}" required maxlength="80" autocomplete="off" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="ex: atendimento">
                    <p class="mt-1 text-xs text-gray-500">Ex.: atendimento, vendas, suporte</p>
                </div>
                <button type="submit" id="btn-create-instance" class="btn-primary text-white px-6 py-2 rounded-lg font-semibold">
                    Criar
                </button>
            </form>
        </div>

        @php
            $instancesAguardandoQr = $instances->filter(fn ($i) => $i->status !== 'connected' && empty($i->qrcode));
            $refreshQrUrls = $instancesAguardandoQr->map(fn ($i) => route('instances.refresh-qr', $i))->values()->all();
            $pollingList = $instancesAguardandoQr->map(fn ($i) => [
                'id' => $i->id,
                'rawUrl' => route('instances.evolution-connect-raw', $i),
                'saveUrl' => route('instances.save-qr', $i),
            ])->values()->all();
        @endphp
        @if($instancesAguardandoQr->isNotEmpty())
        <script>
        (function() {
            var refreshUrls = @json($refreshQrUrls);
            var pollingList = @json($pollingList);
            var csrf = @json(csrf_token());

            function findInObj(obj, out) {
                if (!obj || typeof obj !== 'object') return;
                var keys = Object.keys(obj);
                for (var i = 0; i < keys.length; i++) {
                    var k = keys[i], v = obj[k];
                    if (typeof v === 'string') {
                        if ((k === 'pairingCode' || k === 'pairing_code') && v.length >= 4 && v.length <= 32) out.pairingCode = v;
                        if (v.length > 100 && v.indexOf('@') === -1 && /^[A-Za-z0-9+\/=]+$/.test(v)) out.base64 = v;
                        if (v.indexOf('data:image') === 0 && v.indexOf('base64,') !== -1) out.base64 = v.replace(/^data:image\/[^;]+;base64,/, '');
                    } else if (typeof v === 'object' && v !== null) findInObj(v, out);
                }
            }

            function poll() {
                if (document.visibilityState !== 'visible') return;
                refreshUrls.forEach(function(url) {
                    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                        .then(function(r) { return r.json(); })
                        .then(function(data) { if (data.qrcode || data.pairing_code) location.reload(); })
                        .catch(function() {});
                });
                pollingList.forEach(function(item) {
                    fetch(item.rawUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var out = {};
                            findInObj(data, out);
                            if (out.base64 || out.pairingCode) {
                                var body = new FormData();
                                if (csrf) body.append('_token', csrf);
                                if (out.base64) body.append('qrcode', out.base64);
                                if (out.pairingCode) body.append('pairing_code', out.pairingCode);
                                fetch(item.saveUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
                                    .then(function() { location.reload(); });
                            }
                        })
                        .catch(function() {});
                });
            }
            setInterval(poll, 2000);
            poll();
        })();
        </script>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($instances as $instance)
            <div class="card-modern p-5" data-instance-id="{{ $instance->id }}">
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
                @elseif($instance->pairing_code)
                <div class="mt-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg">
                    <p class="text-sm font-semibold text-blue-800 mb-2">Conectar com código de pareamento</p>
                    <p class="text-lg font-mono font-bold text-blue-900 tracking-widest mb-2">{{ $instance->pairing_code }}</p>
                    <p class="text-xs text-blue-700">No WhatsApp: Configurações → Aparelhos conectados → Conectar com número de telefone → digite o código acima.</p>
                </div>
                @elseif($instance->status !== 'connected')
                <div class="mt-4 p-4 bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-lg">
                    <p class="text-sm text-yellow-800"><i class="fas fa-clock mr-2"></i>Aguardando QR Code...</p>
                </div>
                @endif
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <form action="{{ route('instances.destroy', $instance) }}" method="POST" onsubmit="return confirm('Remover esta instância?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:text-red-800">
                            <i class="fas fa-trash mr-1"></i>Remover
                        </button>
                    </form>
                </div>
            </div>
            @empty
            <div class="col-span-full card-modern p-12 text-center">
                <i class="fas fa-mobile-alt text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg mb-2">Nenhuma instância ainda.</p>
                <p class="text-gray-400 text-sm">Clique em "Nova instância" para conectar um número ao WhatsApp.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
