<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Wiki Loves Monuments Turkey') }} - @yield('title', 'Anıtlar')</title>

    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- OpenGraph and Twitter tags --> 

    <!-- OpenGraph tags -->
    <meta property="og:title" content="{{ config('app.name', 'Viki Anıtları Seviyor Türkiye') }} - @yield('title', 'Anıtlar')" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:image" content="{{ asset('wlm-logo.svg') }}" />
    <meta property="og:description" content="Viki Anıtları Seviyor Türkiye - Türkiye'deki kültürel mirası fotoğraflayın ve keşfedin" />

    <!-- Twitter Card tags -->
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:title" content="{{ config('app.name', 'Viki Anıtları Seviyor Türkiye') }} - @yield('title', 'Anıtlar')" />
    <meta name="twitter:description" content="Türkiye'deki kültürel mirası fotoğraflayın ve keşfedin" />
    <meta name="twitter:image" content="{{ asset('wlm-logo.svg') }}" />
    <meta name="twitter:creator" content="@WikimediaTurkey" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Leaflet CSS for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Leaflet Attribution Styles -->
    <style>
    .leaflet-control-attribution {
        font-size: 11px !important;
        line-height: 1.4 !important;
        background: rgba(255, 255, 255, 0.8) !important;
        padding: 4px 8px !important;
        border-radius: 4px !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2) !important;
    }

    .leaflet-control-attribution a {
        color: #0066cc !important;
        text-decoration: none !important;
    }

    .leaflet-control-attribution a:hover {
        text-decoration: underline !important;
    }
    </style>
    
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
                            <a href="{{ route('home') }}" class="text-xl font-bold text-gray-900">
                             <!--   🏛️ WLM Turkey --> <img src="/wlm-logo-h.svg" alt="WLM Turkey" class="h-8">
                            </a>
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="https://commons.wikimedia.org/wiki/Commons:Wiki_Loves_Monuments_2025_in_Turkey/tr" target="_blank" 
                               class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Hakkında
                            </a>
                            <a href="{{ route('home') }}" 
                               class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Harita
                            </a>
                            <a href="{{ route('monuments.list') }}" 
                               class="{{ route('monuments.list') ? 'border-blue-300' : 'border-transparent' }} text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" >
                                Liste
                            </a>
                        </div>
                    </div>
                    
                    <!-- Desktop Navigation -->
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

                    <!-- Mobile menu button -->
                    <div class="sm:hidden flex items-center">
                        <button type="button" 
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500" 
                                id="mobile-menu-button" 
                                aria-expanded="false">
                            <span class="sr-only">Open main menu</span>
                            <svg class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu -->
            <div class="hidden sm:hidden" id="mobile-menu">
                <div class="pt-2 pb-3 space-y-1">
                <a href="https://commons.wikimedia.org/wiki/Commons:Wiki_Loves_Monuments_2025_in_Turkey/tr" target="_blank"
                       class="border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                        Hakkında
                    </a>
                    <a href="{{ route('home') }}" 
                       class="border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                        Harita
                    </a>
                    <a href="{{ route('monuments.list') }}" 
                       class="border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                        Liste
                    </a>
                </div>
                <div class="pt-4 pb-3 border-t border-gray-200">
                    <div class="flex items-center px-4">
                        <div class="text-sm text-gray-500">
                            {{ \App\Models\Monument::count() }} anıt
                        </div>
                    </div>
                    <div class="mt-3 space-y-1">
                        @auth
                            <a href="{{ route('auth.profile') }}" 
                               class="block px-4 py-2 text-base font-medium text-gray-500 hover:text-gray-800 hover:bg-gray-100">
                                Profil
                            </a>
                            <form method="POST" action="{{ route('auth.logout') }}" class="block">
                                @csrf
                                <button type="submit" 
                                        class="block w-full text-left px-4 py-2 text-base font-medium text-gray-500 hover:text-gray-800 hover:bg-gray-100">
                                    Çıkış Yap
                                </button>
                            </form>
                        @else
                            <a href="{{ route('auth.login') }}" 
                               class="block px-4 py-2 text-base font-medium text-gray-500 hover:text-gray-800 hover:bg-gray-100">
                                Giriş Yap
                            </a>
                        @endauth
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
                    <p>Viki Anıtları Seviyor Türkiye - Türkiye'deki anıtları keşfedin ve fotoğraflayın</p>
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
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <p class="text-xs text-gray-400">
                            Made with ❤️ by 
                            <a href="https://github.com/ooguz" 
                               class="text-blue-600 hover:text-blue-800 font-medium" 
                               target="_blank">
                                Magurale (ooguz)
                            </a>
                            <span class="mx-2">•</span>
                            <a href="https://github.com/ooguz/wlmtr" 
                               class="text-blue-600 hover:text-blue-800" 
                               target="_blank">
                                GitHub
                            </a>
                            <span class="mx-2">•</span>
                            <a href="https://buymeacoffee.com/ooguz" 
                               class="text-blue-600 hover:text-blue-800" 
                               target="_blank">
                                ☕ Buy Me a Coffee
                            </a>
                        </p>
                    </div>

                </div>
            </div>
        </footer>
    </div>

    <!-- Leaflet JS for maps -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Additional scripts -->
    @stack('scripts')
    
    <!-- Navigation scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Desktop user dropdown
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

            // Mobile menu
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
                
                // Close mobile menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!mobileMenuButton.contains(event.target) && !mobileMenu.contains(event.target)) {
                        mobileMenu.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</body>
</html> 