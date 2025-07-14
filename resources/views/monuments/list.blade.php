@extends('layouts.app')

@section('title', 'Anıt Listesi')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Anıt Listesi</h1>
        <p class="mt-2 text-gray-600">{{ $monuments->total() }} anıt bulundu</p>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <form method="GET" action="{{ route('monuments.list') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Arama</label>
                <input type="text" 
                       name="search" 
                       id="search" 
                       value="{{ request('search') }}"
                       placeholder="Anıt adı veya açıklama..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <!-- Province -->
            <div>
                <label for="province" class="block text-sm font-medium text-gray-700 mb-1">İl</label>
                <select name="province" id="province" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tüm iller</option>
                    @foreach($provinces as $province)
                        <option value="{{ $province }}" {{ request('province') == $province ? 'selected' : '' }}>
                            {{ $province }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Category -->
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                <select name="category" id="category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tüm kategoriler</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                            {{ $category->primary_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Photo Status -->
            <div>
                <label for="has_photos" class="block text-sm font-medium text-gray-700 mb-1">Fotoğraf Durumu</label>
                <select name="has_photos" id="has_photos" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tümü</option>
                    <option value="1" {{ request('has_photos') == '1' ? 'selected' : '' }}>Fotoğraflı</option>
                    <option value="0" {{ request('has_photos') == '0' ? 'selected' : '' }}>Fotoğrafsız</option>
                </select>
            </div>
            
            <!-- Filter Button -->
            <div class="md:col-span-2 lg:col-span-4">
                <div class="flex space-x-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Filtrele
                    </button>
                    <a href="{{ route('monuments.list') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Temizle
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Monuments Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @foreach($monuments as $monument)
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                <!-- Image -->
                <div class="aspect-w-16 aspect-h-9 bg-gray-200 relative">
                    @if($monument->featured_photo)
                        <img src="{{ $monument->featured_photo->display_url }}" 
                             alt="{{ $monument->primary_name }}"
                             class="w-full h-48 object-cover">
                        <!-- Photo Info Overlay -->
                        <div class="absolute bottom-2 right-2 bg-black bg-opacity-70 text-white text-xs rounded px-2 py-1 flex items-center space-x-2">
                            @php
                                $author = $monument->featured_photo->photographer;
                                $license = $monument->featured_photo->license_display_name;
                                $isPublicDomain = Str::contains(strtolower($license), 'public domain') || strtolower($license) === 'cc0';
                            @endphp
                            @if($isPublicDomain)
                                <span>Public domain</span>
                            @elseif($author && $license)
                                <span>&copy; {{ $author }} | {{ $license }}</span>
                            @elseif($author)
                                <span>&copy; {{ $author }}</span>
                            @elseif($license)
                                <span>{{ $license }}</span>
                            @endif
                            <a href="{{ $monument->featured_photo->commons_url }}" target="_blank" title="Wikimedia Commons" class="ml-1">
                                <svg class="w-4 h-4 inline" fill="currentColor" viewBox="0 0 20 20"><path d="M12.293 2.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-8.5 8.5a1 1 0 01-.325.217l-4 1.5a1 1 0 01-1.263-1.263l1.5-4a1 1 0 01.217-.325l8.5-8.5zM15 7l-2-2-8.293 8.293-1.086 2.9 2.9-1.086L15 7z"></path></svg>
                            </a>
                        </div>
                    @else
                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-400">Fotoğraf yok</span>
                        </div>
                    @endif
                </div>
                
                <!-- Content -->
                <div class="p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                        <a href="{{ route('monuments.show', $monument) }}" class="hover:text-blue-600">
                            {{ $monument->primary_name }}
                        </a>
                    </h3>
                    
                    @if($monument->primary_description)
                        <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                            {{ Str::limit($monument->primary_description, 100) }}
                        </p>
                    @endif
                    
                    <!-- Location -->
                    <div class="flex items-center text-sm text-gray-500 mb-3">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span>
                            {{ $monument->city ?? $monument->province ?? 'Bilinmeyen konum' }}
                            ({{ \App\Services\WikidataSparqlService::getLabelForQCode($monument->city ?? $monument->province ?? 'Bilinmeyen konum') }})
                        </span>
                        <span>Test: {{ \App\Services\WikidataSparqlService::getLabelForQCode('Q406') }}</span>
                    </div>
                    
                    <!-- Photo count -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center text-sm text-gray-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>{{ $monument->photo_count }} fotoğraf</span>
                        </div>
                        
                        @if($monument->has_photos)
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Fotoğraflı
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                Fotoğrafsız
                            </span>
                        @endif
                    </div>
                    
                    <!-- Categories -->
                    @if($monument->categories->count() > 0)
                        <div class="mt-3 flex flex-wrap gap-1">
                            @foreach($monument->categories->take(3) as $category)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $category->primary_name }}
                                </span>
                            @endforeach
                            @if($monument->categories->count() > 3)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    +{{ $monument->categories->count() - 3 }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    
    <!-- Pagination -->
    @if($monuments->hasPages())
        <div class="mt-8">
            {{ $monuments->appends(request()->query())->links() }}
        </div>
    @endif
    
    <!-- Empty State -->
    @if($monuments->count() === 0)
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Anıt bulunamadı</h3>
            <p class="mt-1 text-sm text-gray-500">Arama kriterlerinizi değiştirmeyi deneyin.</p>
        </div>
    @endif
</div>
@endsection 