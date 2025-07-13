@extends('layouts.app')

@section('title', 'Wikimedia ile Giriş')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100">
                <span class="text-2xl">🏛️</span>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Wiki Loves Monuments Turkey
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Türkiye'deki anıtları keşfedin ve fotoğraflayın
            </p>
        </div>
        
        <div class="bg-white py-8 px-6 shadow rounded-lg sm:px-10">
            <div class="text-center mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    Wikimedia Hesabınızla Giriş Yapın
                </h3>
                <p class="text-sm text-gray-600">
                    Wikimedia Commons'a fotoğraf yüklemek için Wikimedia hesabınızla giriş yapın.
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
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

            <div class="space-y-6">
                <div>
                    <a href="{{ route('auth.wikimedia.redirect') }}" 
                       class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-blue-500 group-hover:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                        Wikimedia ile Giriş Yap
                    </a>
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
                <h4 class="text-sm font-medium text-gray-900 mb-2">Wikimedia Girişi Hakkında</h4>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li>• Wikimedia Commons'a fotoğraf yükleyebilirsiniz</li>
                    <li>• Anıt fotoğraflarınızı paylaşabilirsiniz</li>
                    <li>• Wikimedia edit geçmişiniz görüntülenir</li>
                    <li>• Hesabınız güvenli şekilde bağlanır</li>
                </ul>
            </div>
        </div>

        <div class="text-center">
            <p class="text-xs text-gray-500">
                <a href="https://commons.wikimedia.org/wiki/Help:Contents" 
                   target="_blank" 
                   class="text-blue-600 hover:text-blue-500">
                    Wikimedia Commons Hakkında
                </a>
                •
                <a href="https://www.wikidata.org/wiki/Wikidata:Main_Page" 
                   target="_blank" 
                   class="text-blue-600 hover:text-blue-500">
                    Wikidata
                </a>
            </p>
        </div>
    </div>
</div>
@endsection 