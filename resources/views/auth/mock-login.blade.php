@extends('layouts.app')

@section('title', 'Test Girişi - Commons')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="w-11/12 max-w-md p-6 bg-white rounded-lg shadow-lg flex flex-col items-center">
        <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-yellow-100 mb-4">
            <span class="text-2xl">🧪</span>
        </div>
        <h2 class="text-center text-2xl font-extrabold text-gray-900 mb-1">
            Test Girişi
        </h2>
        <p class="text-center text-sm text-gray-600 mb-4">
            Geliştirme ortamı için test Commons hesabı
        </p>
        <h3 class="text-lg font-medium text-gray-900 mb-1 text-center">
            Test Wikimedia Commons Hesabı
        </h3>
        <p class="text-sm text-gray-600 mb-4 text-center">
            Bu, gerçek Wikimedia Commons OAuth olmadan test etmek için kullanılır.
        </p>
        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-4 w-full">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">
                        Geliştirme Modu
                    </h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Bu test hesabı ile giriş yaparak uygulamanın özelliklerini test edebilirsiniz.</p>
                    </div>
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('auth.mock-login.post') }}" class="w-full">
            @csrf
            <button type="submit"
                class="group w-full sm:w-auto flex items-center justify-center gap-2 py-2 px-6 border border-transparent text-sm font-medium rounded-md text-white btn-danger hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200 mb-3">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" fill="currentColor">
                    <path d="M64 0a64 64 0 1 0 0 128A64 64 0 0 0 64 0zm0 122a58 58 0 1 1 0-116 58 58 0 0 1 0 116zm0-108a50 50 0 1 0 0 100 50 50 0 0 0 0-100zm21.5 71.6L64 59.4l-21.5 26.2h43zm0-15.9L64 43.5 42.5 69.7h43z"/>
                </svg>
                <span>Test Commons Hesabıyla Giriş Yap</span>
            </button>
        </form>
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
            <h4 class="text-sm font-medium text-gray-900 mb-1">Test Commons Hesabı Bilgileri</h4>
            <ul class="text-xs text-gray-600 space-y-1">
                <li>• Kullanıcı Adı: TestCommonsUser</li>
                <li>• Edit Sayısı: 250</li>
                <li>• Commons Düzenleme İzni: Var</li>
                <li>• Gruplar: user, autoconfirmed, filemover</li>
                <li>• Haklar: edit, upload, createpage, movefile</li>
                <li>• Toplam Yükleme: 45 dosya</li>
                <li>• Öne Çıkan Resimler: 2</li>
            </ul>
        </div>
        <div class="text-center w-full">
            <p class="text-xs text-gray-500">
                Bu sadece geliştirme ortamı içindir. Gerçek Wikimedia Commons OAuth için
                <a href="https://commons.wikimedia.org/wiki/Special:OAuthConsumerRegistration" target="_blank" class="text-blue-600 hover:text-blue-500">Commons OAuth Consumer kaydı</a> gerekir.
            </p>
        </div>
    </div>
</div>
@endsection 