<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Wiki Loves Monuments Turkey') }} - @yield('title', 'Anıtlar')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Leaflet CSS for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Additional styles -->
    @stack('styles')
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-50">
        <!-- Navigation -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <a href="{{ route('monuments.map') }}" class="text-xl font-bold text-gray-900">
                                🏛️ WLM Turkey
                            </a>
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <a href="{{ route('monuments.map') }}" 
                               class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Harita
                            </a>
                            <a href="{{ route('monuments.list') }}" 
                               class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Liste
                            </a>
                        </div>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:items-center">
                        <div class="ml-3 relative flex items-center space-x-4">
                            <div class="text-sm text-gray-500">
                                {{ \App\Models\Monument::count() }} anıt
                            </div>
                            
                            @auth
                                <!-- User dropdown -->
                                <div class="relative">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm text-gray-700">{{ auth()->user()->display_name }}</span>
                                        <div class="relative">
                                            <button type="button" 
                                                    class="flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
                                                    id="user-menu-button" 
                                                    aria-expanded="false" 
                                                    aria-haspopup="true">
                                                <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                    <span class="text-sm font-medium text-blue-600">👤</span>
                                                </div>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Dropdown menu -->
                                    <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50" 
                                         role="menu" 
                                         aria-orientation="vertical" 
                                         aria-labelledby="user-menu-button" 
                                         tabindex="-1"
                                         id="user-menu">
                                        <a href="{{ route('auth.profile') }}" 
                                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                                           role="menuitem" 
                                           tabindex="-1">
                                            Profil
                                        </a>
                                        <form method="POST" action="{{ route('auth.logout') }}" class="block">
                                            @csrf
                                            <button type="submit" 
                                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                                                    role="menuitem" 
                                                    tabindex="-1">
                                                Çıkış Yap
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                <a href="{{ route('auth.login') }}" 
                                   class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium">
                                    Giriş Yap
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main>
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-8">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="text-center text-sm text-gray-500">
                    <p>Wiki Loves Monuments Turkey - Türkiye'deki anıtları keşfedin ve fotoğraflayın</p>
                    <p class="mt-2">
                        <a href="https://commons.wikimedia.org/wiki/Category:Wiki_Loves_Monuments_Turkey" 
                           class="text-blue-600 hover:text-blue-800" target="_blank">
                            Wikimedia Commons
                        </a> | 
                        <a href="https://www.wikidata.org/" 
                           class="text-blue-600 hover:text-blue-800" target="_blank">
                            Wikidata
                        </a>
                    </p>
                </div>
            </div>
        </footer>
    </div>

    <!-- Leaflet JS for maps -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Additional scripts -->
    @stack('scripts')
    
    <!-- User dropdown script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');
            
            if (userMenuButton && userMenu) {
                userMenuButton.addEventListener('click', function() {
                    userMenu.classList.toggle('hidden');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                        userMenu.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</body>
</html> 