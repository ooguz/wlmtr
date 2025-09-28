@extends('layouts.app')

@section('title', 'Wikimedia Commons ile Giriş')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="w-11/12 max-w-md p-6 bg-white rounded-lg shadow-lg flex flex-col items-center">
    <!-- <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100 mb-4">
            <span class="text-2xl">🏛️</span> 
        </div> -->
        <h2 class="text-center text-2xl font-extrabold text-gray-900 mb-1">
        <img src="/wlm-logo-h.svg" alt="WLM Turkey" class="h-16">
        </h2>
        <p class="text-center text-sm text-gray-600 mb-4">
            Türkiye'deki anıtları keşfedin ve fotoğraflayın
        </p>
        <h3 class="text-lg font-medium text-gray-900 mb-1 text-center">
            Wikimedia Commons Hesabınızla Giriş Yapın
        </h3>
        <p class="text-sm text-gray-600 mb-4 text-center">
            Wikimedia Commons'a fotoğraf yüklemek için Commons hesabınızla giriş yapın. Vikipedi'de bir hesabınız varsa onunla giriş yapabilirsiniz.
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
           <svg fill="#ffffff" class="h-5" viewBox="0 0 24 24" role="img" xmlns="http://www.w3.org/2000/svg"><title>Wikimedia Commons icon</title><path d="M9.048 15.203a2.952 2.952 0 1 1 5.904 0 2.952 2.952 0 0 1-5.904 0zm11.749.064v-.388h-.006a8.726 8.726 0 0 0-.639-2.985 8.745 8.745 0 0 0-1.706-2.677l.004-.004-.186-.185-.044-.045-.026-.026-.204-.204-.006.007c-.848-.756-1.775-1.129-2.603-1.461-1.294-.519-2.138-.857-2.534-2.467.443.033.839.174 1.13.481C15.571 6.996 11.321 0 11.321 0s-1.063 3.985-2.362 5.461c-.654.744.22.273 1.453-.161.279 1.19.77 2.119 1.49 2.821.791.771 1.729 1.148 2.556 1.48.672.27 1.265.508 1.767.916l-.593.594-.668-.668-.668 2.463 2.463-.668-.668-.668.6-.599a6.285 6.285 0 0 1 1.614 3.906h-.844v-.944l-2.214 1.27 2.214 1.269v-.944h.844a6.283 6.283 0 0 1-1.614 3.906l-.6-.599.668-.668-2.463-.668.668 2.463.668-.668.6.6a6.263 6.263 0 0 1-3.907 1.618v-.848h.945L12 18.45l-1.27 2.214h.944v.848a6.266 6.266 0 0 1-3.906-1.618l.599-.6.668.668.668-2.463-2.463.668.668.668-.6.599a6.29 6.29 0 0 1-1.615-3.906h.844v.944l2.214-1.269-2.214-1.27v.944h-.843a6.292 6.292 0 0 1 1.615-3.906l.6.599-.668.668 2.463.668-.668-2.463-.668.668-2.359-2.358-.23.229-.044.045-.185.185.004.004a8.749 8.749 0 0 0-2.345 5.662h-.006v.649h.006a8.749 8.749 0 0 0 2.345 5.662l-.004.004.185.185.045.045.045.045.185.185.004-.004a8.73 8.73 0 0 0 2.677 1.707 8.75 8.75 0 0 0 2.985.639V24h.649v-.006a8.75 8.75 0 0 0 2.985-.639 8.717 8.717 0 0 0 2.677-1.707l.004.004.187-.187.044-.043.043-.044.187-.186-.004-.004a8.733 8.733 0 0 0 1.706-2.677 8.726 8.726 0 0 0 .639-2.985h.006v-.259z"/></svg>
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