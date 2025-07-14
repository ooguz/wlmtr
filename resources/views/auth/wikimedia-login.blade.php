@extends('layouts.app')

@section('title', 'Wikimedia Commons ile Giriş')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="w-11/12 max-w-md p-6 bg-white rounded-lg shadow-lg flex flex-col items-center">
        <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100 mb-4">
            <span class="text-2xl">🏛️</span>
        </div>
        <h2 class="text-center text-2xl font-extrabold text-gray-900 mb-1">
            Wiki Loves Monuments Turkey
        </h2>
        <p class="text-center text-sm text-gray-600 mb-4">
            Türkiye'deki anıtları keşfedin ve fotoğraflayın
        </p>
        <h3 class="text-lg font-medium text-gray-900 mb-1 text-center">
            Wikimedia Commons Hesabınızla Giriş Yapın
        </h3>
        <p class="text-sm text-gray-600 mb-4 text-center">
            Wikimedia Commons'a fotoğraf yüklemek için Commons hesabınızla giriş yapın.
        </p>
        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 rounded-md p-3 w-full">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">
                            Giriş hatası
                        </h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <a href="{{ route('auth.wikimedia.redirect') }}"
           class="group w-full sm:w-auto flex items-center justify-center gap-2 py-2 px-6 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 mb-3">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" fill="currentColor">
                <path d="M64 0a64 64 0 1 0 0 128A64 64 0 0 0 64 0zm0 122a58 58 0 1 1 0-116 58 58 0 0 1 0 116zm0-108a50 50 0 1 0 0 100 50 50 0 0 0 0-100zm21.5 71.6L64 59.4l-21.5 26.2h43zm0-15.9L64 43.5 42.5 69.7h43z"/>
            </svg>
            <span>Wikimedia Commons ile Giriş Yap</span>
        </a>
        <div class="relative w-full mb-3">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="px-2 bg-white text-gray-500">veya</span>
            </div>
        </div>
        <a href="{{ route('monuments.map') }}" class="text-blue-600 hover:text-blue-500 text-sm font-medium mb-4 text-center w-full">Giriş yapmadan devam et</a>
        <div class="bg-gray-50 rounded-lg p-3 w-full mb-2">
            <h4 class="text-sm font-medium text-gray-900 mb-1">Wikimedia Commons Girişi Hakkında</h4>
            <ul class="text-xs text-gray-600 space-y-1">
                <li>• Wikimedia Commons'a fotoğraf yükleyebilirsiniz</li>
                <li>• Anıt fotoğraflarınızı paylaşabilirsiniz</li>
                <li>• Commons edit geçmişiniz görüntülenir</li>
                <li>• Hesabınız güvenli şekilde bağlanır</li>
                <li>• Commons özel izinleriniz kontrol edilir</li>
            </ul>
        </div>
        <div class="text-center w-full">
            <p class="text-xs text-gray-500">
                <a href="https://commons.wikimedia.org/wiki/Help:Contents" target="_blank" class="text-blue-600 hover:text-blue-500">Wikimedia Commons Hakkında</a>
                •
                <a href="https://commons.wikimedia.org/wiki/Special:OAuthConsumerRegistration" target="_blank" class="text-blue-600 hover:text-blue-500">OAuth Kaydı</a>
            </p>
        </div>
    </div>
</div>
@endsection 