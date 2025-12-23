<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Nexxivo')</title>
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .nav-glass {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }
        .card-modern {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2);
            transition: all 0.3s ease;
        }
        .card-modern:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.3);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        .nav-link {
            position: relative;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            color: #667eea;
        }
        .nav-link.active {
            color: #667eea;
        }
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body>
    <nav class="nav-glass shadow-xl sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="/chat" class="text-2xl font-bold bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent">
                            <i class="fas fa-robot mr-2"></i>Nexxivo
                        </a>
                    </div>
                    <div class="hidden sm:ml-8 sm:flex sm:space-x-6">
                        <a href="/chat" class="nav-link {{ request()->is('chat*') ? 'active' : '' }} inline-flex items-center px-3 pt-1 border-b-2 border-transparent text-sm font-semibold text-gray-700">
                            <i class="fas fa-comments mr-2"></i>Chat
                        </a>
                        <a href="/crm" class="nav-link {{ request()->is('crm*') ? 'active' : '' }} inline-flex items-center px-3 pt-1 border-b-2 border-transparent text-sm font-semibold text-gray-700">
                            <i class="fas fa-columns mr-2"></i>CRM Kanban
                        </a>
                        <a href="/flows" class="nav-link {{ request()->is('flows*') ? 'active' : '' }} inline-flex items-center px-3 pt-1 border-b-2 border-transparent text-sm font-semibold text-gray-700">
                            <i class="fas fa-project-diagram mr-2"></i>Fluxos
                        </a>
                        <a href="/ai-settings" class="nav-link {{ request()->is('ai-settings*') ? 'active' : '' }} inline-flex items-center px-3 pt-1 border-b-2 border-transparent text-sm font-semibold text-gray-700">
                            <i class="fas fa-cog mr-2"></i>Configurações IA
                        </a>
                    </div>
                    @auth
                    <div class="flex items-center">
                        <span class="text-sm text-gray-600 mr-4">
                            <i class="fas fa-user-shield mr-1"></i>{{ Auth::user()->name }}
                        </span>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-gray-600 hover:text-gray-800">
                                <i class="fas fa-sign-out-alt mr-1"></i>Sair
                            </button>
                        </form>
                    </div>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <main class="py-8">
        @yield('content')
    </main>
</body>
</html>

