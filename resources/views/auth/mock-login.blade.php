@extends('layouts.app')

@section('title', 'Test Girişi')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-yellow-100">
                <span class="text-2xl">🧪</span>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Test Girişi
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Geliştirme ortamı için test hesabı
            </p>
        </div>
        
        <div class="bg-white py-8 px-6 shadow rounded-lg sm:px-10">
            <div class="text-center mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    Test Wikimedia Hesabı
                </h3>
                <p class="text-sm text-gray-600">
                    Bu, gerçek Wikimedia OAuth olmadan test etmek için kullanılır.
                </p>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
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

            <div class="space-y-6">
                <div>
                    <form method="POST" action="{{ route('auth.mock-login.post') }}">
                        @csrf
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-yellow-500 group-hover:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                            </span>
                            Test Hesabıyla Giriş Yap
                        </button>
                    </form>
                </div>

                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">veya</span>
                    </div>
                </div>

                <div class="text-center">
                    <a href="{{ route('monuments.map') }}" 
                       class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                        Giriş yapmadan devam et
                    </a>
                </div>
            </div>

            <div class="mt-8 bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-gray-900 mb-2">Test Hesabı Bilgileri</h4>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li>• Kullanıcı Adı: TestWikimediaUser</li>
                    <li>• Edit Sayısı: 150</li>
                    <li>• Commons Düzenleme İzni: Var</li>
                    <li>• Gruplar: user, autoconfirmed</li>
                    <li>• Haklar: edit, upload, createpage</li>
                </ul>
            </div>
        </div>

        <div class="text-center">
            <p class="text-xs text-gray-500">
                Bu sadece geliştirme ortamı içindir. Gerçek Wikimedia OAuth için 
                <a href="https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration" 
                   target="_blank" 
                   class="text-blue-600 hover:text-blue-500">
                    OAuth Consumer kaydı
                </a> gerekir.
            </p>
        </div>
    </div>
</div>
@endsection 