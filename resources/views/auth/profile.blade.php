@extends('layouts.app')

@section('title', 'Profil - ' . $user->display_name)

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Profil</h1>
        <p class="mt-2 text-gray-600">Wikimedia hesap bilgileriniz ve istatistikleriniz</p>
    </div>

    @if (session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">
                        {{ session('success') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

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
                        Hata oluştu
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Profile Card -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center space-x-4 mb-6">
                    <div class="flex-shrink-0">
                        <div class="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center">
                            <span class="text-2xl">👤</span>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">{{ $user->display_name }}</h2>
                        <p class="text-gray-600">{{ $user->email }}</p>
                        @if($user->isWikimediaConnected())
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Wikimedia Bağlı
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                Wikimedia Bağlı Değil
                            </span>
                        @endif
                    </div>
                </div>

                @if($user->isWikimediaConnected())
                    <!-- Wikimedia Information -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Wikimedia Hesap Bilgileri</h3>
                        
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Wikimedia Kullanıcı Adı</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <a href="https://meta.wikimedia.org/wiki/User:{{ $user->wikimedia_username }}" 
                                       target="_blank" 
                                       class="text-blue-600 hover:text-blue-800">
                                        {{ $user->wikimedia_username }}
                                    </a>
                                </dd>
                            </div>

                            @if($user->wikimedia_real_name)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Gerçek Ad</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $user->wikimedia_real_name }}</dd>
                                </div>
                            @endif

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Wikimedia Edit Sayısı</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ number_format($user->wikimedia_edit_count) }}</dd>
                            </div>

                            @if($user->wikimedia_registration_date)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Kayıt Tarihi</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $user->wikimedia_registration_date->format('d.m.Y') }}</dd>
                                </div>
                            @endif

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Commons Düzenleme İzni</dt>
                                <dd class="mt-1">
                                    @if($user->canEditCommons())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Var
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Yok
                                        </span>
                                    @endif
                                </dd>
                            </div>

                            @if($user->last_wikimedia_sync)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Son Senkronizasyon</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $user->last_wikimedia_sync->diffForHumans() }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if($user->wikimedia_groups)
                            <div class="mt-6">
                                <dt class="text-sm font-medium text-gray-500 mb-2">Wikimedia Grupları</dt>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($user->wikimedia_groups as $group)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $group }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($user->wikimedia_rights)
                            <div class="mt-6">
                                <dt class="text-sm font-medium text-gray-500 mb-2">Wikimedia Hakları</dt>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($user->wikimedia_rights as $right)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ $right }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <!-- Not Connected -->
                    <div class="border-t border-gray-200 pt-6">
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Wikimedia Hesabı Bağlı Değil</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Wikimedia Commons'a fotoğraf yüklemek için hesabınızı bağlayın.
                            </p>
                            <div class="mt-6">
                                <a href="{{ route('auth.wikimedia.redirect') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Wikimedia ile Bağlan
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Statistics Card -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">İstatistikler</h3>
                
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Toplam Edit Sayısı</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($user->total_edit_count) }}</dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Platform Yükleme Sayısı</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($user->upload_count) }}</dd>
                    </div>
                    
                    @if($user->isWikimediaConnected())
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Wikimedia Edit Sayısı</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($user->wikimedia_edit_count) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Actions Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">İşlemler</h3>
                
                <div class="space-y-3">
                    @if($user->isWikimediaConnected())
                        <form method="POST" action="{{ route('auth.profile.sync-wikimedia') }}">
                            @csrf
                            <button type="submit" 
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Wikimedia Verilerini Senkronize Et
                            </button>
                        </form>
                    @endif
                    
                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <button type="submit" 
                                class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Çıkış Yap
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 