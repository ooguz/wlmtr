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
            @if(request()->filled('lat'))
                <input type="hidden" name="lat" id="lat" value="{{ request('lat') }}">
            @endif
            @if(request()->filled('lng'))
                <input type="hidden" name="lng" id="lng" value="{{ request('lng') }}">
            @endif
            @if(request()->filled('distance'))
                <input type="hidden" name="distance" id="distance" value="{{ request('distance') }}">
            @endif
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

            <!-- Location-based Search -->
          
            
            <!-- Filter Button -->
            <div class="md:col-span-2 lg:col-span-4">
                <div class="flex space-x-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10 18a8 8 0 110-16 8 8 0 010 16z"></path>
                        </svg>
                        Filtrele
                    </button>
                    <button type="button" id="btnUseMyLocation" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center">
                     
                        <svg class="w-4 h-4 mr-2" viewBox="0 -960 960 960" fill="currentColor"  aria-hidden="true">
<path d="M440-42v-80q-125-14-214.5-103.5T122-440H42v-80h80q14-125 103.5-214.5T440-838v-80h80v80q125 14 214.5 103.5T838-520h80v80h-80q-14 125-103.5 214.5T520-122v80h-80Zm40-158q116 0 198-82t82-198q0-116-82-198t-198-82q-116 0-198 82t-82 198q0 116 82 198t198 82Zm0-120q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47Zm0-80q33 0 56.5-23.5T560-480q0-33-23.5-56.5T480-560q-33 0-56.5 23.5T400-480q0 33 23.5 56.5T480-400Zm0-80Z"/>            </svg>
                        Konumumu Kullan
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
                    @php $cardPhoto = $monument->featured_photo ?? ($monument->list_featured_photo ?? null); @endphp
                    @if($cardPhoto)
                        <img src="{{ $cardPhoto->display_url }}" 
                             alt="{{ $monument->primary_name }}"
                             class="w-full h-48 object-cover">
                        <!-- Photo Info Overlay -->
                        <div class="absolute bottom-2 right-2 bg-black bg-opacity-70 text-white text-xs rounded px-2 py-1 flex items-center space-x-2">
                            @php
                                $author = $cardPhoto->photographer;
                                $license = $cardPhoto->license_display_name;
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
                            @if(!empty($cardPhoto->commons_url))
                            <a href="{{ $cardPhoto->commons_url }}" target="_blank" title="Wikimedia Commons" class="ml-1">
                              
                                <svg fill="currentColor" class="h-4 inline" viewBox="0 0 24 24" role="img" xmlns="http://www.w3.org/2000/svg"><title>Wikimedia Commons icon</title><path d="M9.048 15.203a2.952 2.952 0 1 1 5.904 0 2.952 2.952 0 0 1-5.904 0zm11.749.064v-.388h-.006a8.726 8.726 0 0 0-.639-2.985 8.745 8.745 0 0 0-1.706-2.677l.004-.004-.186-.185-.044-.045-.026-.026-.204-.204-.006.007c-.848-.756-1.775-1.129-2.603-1.461-1.294-.519-2.138-.857-2.534-2.467.443.033.839.174 1.13.481C15.571 6.996 11.321 0 11.321 0s-1.063 3.985-2.362 5.461c-.654.744.22.273 1.453-.161.279 1.19.77 2.119 1.49 2.821.791.771 1.729 1.148 2.556 1.48.672.27 1.265.508 1.767.916l-.593.594-.668-.668-.668 2.463 2.463-.668-.668-.668.6-.599a6.285 6.285 0 0 1 1.614 3.906h-.844v-.944l-2.214 1.27 2.214 1.269v-.944h.844a6.283 6.283 0 0 1-1.614 3.906l-.6-.599.668-.668-2.463-.668.668 2.463.668-.668.6.6a6.263 6.263 0 0 1-3.907 1.618v-.848h.945L12 18.45l-1.27 2.214h.944v.848a6.266 6.266 0 0 1-3.906-1.618l.599-.6.668.668.668-2.463-2.463.668.668.668-.6.599a6.29 6.29 0 0 1-1.615-3.906h.844v.944l2.214-1.269-2.214-1.27v.944h-.843a6.292 6.292 0 0 1 1.615-3.906l.6.599-.668.668 2.463.668-.668-2.463-.668.668-2.359-2.358-.23.229-.044.045-.185.185.004.004a8.749 8.749 0 0 0-2.345 5.662h-.006v.649h.006a8.749 8.749 0 0 0 2.345 5.662l-.004.004.185.185.045.045.045.045.185.185.004-.004a8.73 8.73 0 0 0 2.677 1.707 8.75 8.75 0 0 0 2.985.639V24h.649v-.006a8.75 8.75 0 0 0 2.985-.639 8.717 8.717 0 0 0 2.677-1.707l.004.004.187-.187.044-.043.043-.044.187-.186-.004-.004a8.733 8.733 0 0 0 1.706-2.677 8.726 8.726 0 0 0 .639-2.985h.006v-.259z"/></svg>

                            </a>
                            @endif
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
                            {{ $monument->location_hierarchy_tr
                                ?? ( $monument->admin_area_tr 
                                    ?? ( $monument->city ? \App\Services\WikidataSparqlService::getLabelForQCode($monument->city)
                                        : ( $monument->province ? \App\Services\WikidataSparqlService::getLabelForQCode($monument->province)
                                            : ( $monument->district ? \App\Services\WikidataSparqlService::getLabelForQCode($monument->district)
                                                : 'Bilinmeyen konum')))) }}
                        </span>
                    </div>
                    
                    <!-- Distance & Photo count -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center text-sm text-gray-500">
                            @php
                                $latQ = request('lat');
                                $lngQ = request('lng');
                                $showDistance = $latQ !== null && $latQ !== '' && $lngQ !== null && $lngQ !== '' && $monument->hasCoordinates();
                                $distanceKm = null;
                                if ($showDistance) {
                                    $distanceKm = round($monument->getDistanceFrom((float) $latQ, (float) $lngQ), 1);
                                }
                            @endphp
                            @if($showDistance && $distanceKm !== null)
                                <span class="mr-3">{{ $distanceKm }} km</span>
                                <span class="ml-2 mr-3"></span>
                            @endif
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>{{ $monument->effective_photo_count ?? $monument->photo_count }} fotoğraf</span>
                        </div>
                        
                        @if(($monument->effective_has_photos ?? null) === true || $monument->photos->count() > 0)
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
            {{ $monuments->appends(request()->query())->links('vendor.pagination.tailwind-tr') }}
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnUseMyLocation');
    if (!btn) return;
    btn.addEventListener('click', function () {
        if (!navigator.geolocation) {
            alert('Tarayıcı konum bilgisi desteklemiyor.');
            return;
        }
        btn.disabled = true;
        btn.textContent = 'Konum alınıyor...';
        navigator.geolocation.getCurrentPosition(function (pos) {
            const { latitude, longitude } = pos.coords;
            let latInput = document.getElementById('lat');
            let lngInput = document.getElementById('lng');
            let distInput = document.getElementById('distance');
            if (!latInput) {
                latInput = document.createElement('input');
                latInput.type = 'hidden';
                latInput.name = 'lat';
                latInput.id = 'lat';
                btn.closest('form').appendChild(latInput);
            }
            if (!lngInput) {
                lngInput = document.createElement('input');
                lngInput.type = 'hidden';
                lngInput.name = 'lng';
                lngInput.id = 'lng';
                btn.closest('form').appendChild(lngInput);
            }
            if (!distInput) {
                distInput = document.createElement('input');
                distInput.type = 'hidden';
                distInput.name = 'distance';
                distInput.id = 'distance';
                btn.closest('form').appendChild(distInput);
            }
            latInput.value = latitude.toFixed(6);
            lngInput.value = longitude.toFixed(6);
            // Default radius if none provided already
            if (!distInput.value) {
                distInput.value = '50';
            }
            // Submit form automatically once location is set
            btn.closest('form').submit();
        }, function () {
            alert('Konum alınamadı. Lütfen izin verdiğinizden emin olun.');
            btn.disabled = false;
            btn.textContent = 'Konumumu Kullan';
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
    });
});
</script>
@endpush