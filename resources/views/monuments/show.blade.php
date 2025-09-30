@extends('layouts.app')

@section('title', $monument->primary_name)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <nav class="flex mb-8" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="{{ route('monuments.map') }}" class="text-gray-700 hover:text-gray-900 whitespace-nowrap inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                    Anıtlar
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="ml-1 text-gray-500 md:ml-2">{{ $monument->primary_name }}</span>
                </div>
            </li>
        </ol>
    </nav>
    
    <!-- Photo Carousel -->
    @if(!empty($displayPhotos))
        <div class="mb-8">
            <div class="relative">
                <div id="detailCarousel" class="overflow-hidden rounded-lg">
                    <div id="detailCarouselTrack" class="flex transition-transform duration-300 ease-in-out">
                        @foreach($displayPhotos as $photo)
                            <div class="flex-shrink-0 w-full">
                                <img src="{{ $photo['full_resolution_url'] }}" 
                                     alt="{{ $photo['title'] ?? $monument->primary_name }}"
                                     class="w-full h-96 object-cover cursor-pointer"
                                     onclick="openDetailPhotoModal('{{ $photo['full_resolution_url'] }}', '{{ $photo['commons_url'] ?? '' }}', '{{ $photo['title'] ?? '' }}', '{{ $photo['photographer'] ?? '' }}', '{{ $photo['license'] ?? ($photo['license_display_name'] ?? '') }}')">
                                
                                <!-- Photo Info Overlay -->
                                <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-75 text-white p-4">
                                    <div class="font-semibold">{{ $photo['title'] ?? 'Untitled' }}</div>
                                    @if(!empty($photo['photographer']))
                                        <div class="text-sm text-gray-300">by {{ $photo['photographer'] }}</div>
                                    @endif
                                    @php $licenseText = $photo['license'] ?? ($photo['license_display_name'] ?? null); @endphp
                                    @if(!empty($licenseText))
                                        <div class="text-xs text-gray-300">{{ $licenseText }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <!-- Carousel Navigation -->
                @if(count($displayPhotos) > 1)
                    <button id="prevDetail" class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white rounded-full p-2 hover:bg-opacity-75">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>
                    <button id="nextDetail" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white rounded-full p-2 hover:bg-opacity-75">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
                @endif
                
                <!-- Carousel Indicators -->
                @if(count($displayPhotos) > 1)
                    <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2">
                    @foreach($displayPhotos as $index => $p)
                            <button class="w-2 h-2 rounded-full {{ $index === 0 ? 'bg-white' : 'bg-white bg-opacity-50' }} detail-indicator" data-index="{{ $index }}"></button>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    @endif
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $monument->primary_name }}</h1>
                
                @if($monument->primary_description)
                    <p class="text-lg text-gray-600 mb-4">{{ $monument->primary_description }}</p>
                @endif
                
                <!-- Location -->
                <div class="flex items-center text-gray-500 mb-4">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    @if($monument->address)
                        <span class="mx-2">•</span>
                        <span>{{ $monument->address }}</span>
                    @endif
                </div>
                
                <!-- Photo count -->
                <div class="flex items-center text-gray-500">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>{{ $effectivePhotoCount }} fotoğraf</span>
                    @if(count($displayPhotos) > 0)
                        <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Fotoğraflı
                        </span>
                    @else
                        <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            Fotoğrafsız
                        </span>
                    @endif
                </div>
            </div>
            
            {{-- <!-- TEMP: Raw JSON dump for debugging -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Geçici JSON</h2>
                @php
                    $raw = \App\Services\WikidataSparqlService::getEntityData($monument->wikidata_id ?? '');
                    $payload = [
                        'monument' => $monument->load(['photos','categories'])->toArray(),
                        'raw_wikidata' => $raw,
                    ];
                @endphp
                <pre class="text-xs bg-gray-100 rounded p-3 overflow-x-auto">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div> --}}

            <!-- Map -->
         @if($monument->hasCoordinates())
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Konum</h2>
                    <div id="map" class="w-full h-64 rounded-lg"></div>
                </div>
            @endif
            
            <!-- Photos -->
            @if(!empty($displayPhotos))
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Fotoğraflar</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($displayPhotos as $photo)
                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                <div class="relative">
                                <img src="{{ $photo['display_url'] ?? $photo['full_resolution_url'] }}" 
                                     alt="{{ $photo['title'] ?? $monument->primary_name }}"
                                     class="w-full h-48 object-cover">
                                    <!-- Photo Info Overlay -->
                                    <div class="absolute bottom-2 right-2 bg-black bg-opacity-70 text-white text-xs rounded px-2 py-1 flex items-center space-x-2">
                                        @php
                                            $author = $photo['photographer'] ?? null;
                                            $license = $photo['license'] ?? ($photo['license_display_name'] ?? null);
                                            $isPublicDomain = $license && (Str::contains(strtolower($license), 'public domain') || strtolower($license) === 'cc0');
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
                                        @if(!empty($photo['commons_url']))
                                        <a href="{{ $photo['commons_url'] }}" target="_blank" title="Wikimedia Commons" class="ml-1">
                                        <svg fill="currentColor" class="h-4 inline" viewBox="0 0 24 24" role="img" xmlns="http://www.w3.org/2000/svg"><title>Wikimedia Commons icon</title><path d="M9.048 15.203a2.952 2.952 0 1 1 5.904 0 2.952 2.952 0 0 1-5.904 0zm11.749.064v-.388h-.006a8.726 8.726 0 0 0-.639-2.985 8.745 8.745 0 0 0-1.706-2.677l.004-.004-.186-.185-.044-.045-.026-.026-.204-.204-.006.007c-.848-.756-1.775-1.129-2.603-1.461-1.294-.519-2.138-.857-2.534-2.467.443.033.839.174 1.13.481C15.571 6.996 11.321 0 11.321 0s-1.063 3.985-2.362 5.461c-.654.744.22.273 1.453-.161.279 1.19.77 2.119 1.49 2.821.791.771 1.729 1.148 2.556 1.48.672.27 1.265.508 1.767.916l-.593.594-.668-.668-.668 2.463 2.463-.668-.668-.668.6-.599a6.285 6.285 0 0 1 1.614 3.906h-.844v-.944l-2.214 1.27 2.214 1.269v-.944h.844a6.283 6.283 0 0 1-1.614 3.906l-.6-.599.668-.668-2.463-.668.668 2.463.668-.668.6.6a6.263 6.263 0 0 1-3.907 1.618v-.848h.945L12 18.45l-1.27 2.214h.944v.848a6.266 6.266 0 0 1-3.906-1.618l.599-.6.668.668.668-2.463-2.463.668.668.668-.6.599a6.29 6.29 0 0 1-1.615-3.906h.844v.944l2.214-1.269-2.214-1.27v.944h-.843a6.292 6.292 0 0 1 1.615-3.906l.6.599-.668.668 2.463.668-.668-2.463-.668.668-2.359-2.358-.23.229-.044.045-.185.185.004.004a8.749 8.749 0 0 0-2.345 5.662h-.006v.649h.006a8.749 8.749 0 0 0 2.345 5.662l-.004.004.185.185.045.045.045.045.185.185.004-.004a8.73 8.73 0 0 0 2.677 1.707 8.75 8.75 0 0 0 2.985.639V24h.649v-.006a8.75 8.75 0 0 0 2.985-.639 8.717 8.717 0 0 0 2.677-1.707l.004.004.187-.187.044-.043.043-.044.187-.186-.004-.004a8.733 8.733 0 0 0 1.706-2.677 8.726 8.726 0 0 0 .639-2.985h.006v-.259z"/></svg>
                                        </a>
                                        @endif
                                    </div>
                                </div>
                                <div class="p-4">
                                    @if(!empty($photo['title']))
                                        <h3 class="font-semibold text-gray-900 mb-2">{{ $photo['title'] }}</h3>
                                    @endif
                                    @if(!empty($photo['photographer']))
                                        <p class="text-sm text-gray-600 mb-2">Fotoğrafçı: {{ $photo['photographer'] }}</p>
                                    @endif
                                    @if(!empty($photo['date_taken']))
                                        <p class="text-sm text-gray-500 mb-2">{{ $photo['date_taken'] }}</p>
                                    @endif
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-gray-500">{{ $photo['license'] ?? ($photo['license_display_name'] ?? '') }}</span>
                                        <a href="{{ $photo['full_resolution_url'] ?? ($photo['display_url'] ?? '#') }}" 
                                           target="_blank"
                                           class="text-blue-600 hover:text-blue-800 text-sm">
                                            Tam boyut
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Fotoğraflar</h2>
                    <div class="bg-gray-50 rounded-lg p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Henüz fotoğraf yok</h3>
                        <p class="mt-1 text-sm text-gray-500">Bu anıtın fotoğrafını yükleyen ilk siz olun!</p>
                    </div>
                </div>
            @endif
        </div>

        
        
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Details Card -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Detaylar</h3>
                
                <dl class="space-y-4">
                    @if($monument->heritage_status)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Koruma Durumu</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $monument->heritage_status }}</dd>
                        </div>
                    @endif

                    @if($monument->description_tr)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Açıklama</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $monument->description_tr }}</dd>
                        </div>
                    @endif

                    @if($monument->aka)
                    <div>
                            <dt class="text-sm font-medium text-gray-500">Diğer adlar</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $monument->aka }}</dd>
                        </div>
                    @endif

                    @if($monument->properties["instance_of_label_tr"])
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tür</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $monument->properties["instance_of_label_tr"] }}</dd>
                        </div>
                    @endif

                    @if($monument->location_hierarchy_tr)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Konum</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $monument->location_hierarchy_tr }}</dd>
                        </div>
                    @endif


                    @if($monument->category)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Kategori</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $monument->category }}</dd>
                        </div>
                    @endif

                    @if($monument->province)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">İl</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ \App\Services\WikidataSparqlService::getLabelForQCode($monument->province) }}
                            </dd>
                        </div>
                    @endif
                    
                </dl>
            </div>
            
            <!-- Categories Card -->
            @if($monument->categories->count() > 0)
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Kategoriler</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($monument->categories as $category)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                {{ $category->primary_name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
            
            <!-- External Links Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Dış Bağlantılar</h3>
                <div class="space-y-3">
                    @if($monument->wikidata_url)
                        <a href="{{ $monument->wikidata_url }}" 
                           target="_blank"
                           class="flex items-center text-blue-600 hover:text-blue-800">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                            Wikidata
                        </a>
                    @endif
                    
                    @if($monument->wikipedia_tr_url)
                        <a href="{{ $monument->wikipedia_tr_url }}" 
                           target="_blank"
                           class="flex items-center text-blue-600 hover:text-blue-800">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                            Vikipedi (Türkçe)
                        </a>
                    @endif
                    
                    @if($monument->wikipedia_en_url)
                        <a href="{{ $monument->wikipedia_en_url }}" 
                           target="_blank"
                           class="flex items-center text-blue-600 hover:text-blue-800">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                            Wikipedia (English)
                        </a>
                    @endif
                    
                    @if($monument->commons_category)
                        <a href="https://commons.wikimedia.org/wiki/Category:{{ $monument->commons_category }}" 
                           target="_blank"
                           class="flex items-center text-blue-600 hover:text-blue-800">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                            Wikimedia Commons
                        </a>
                    @endif

                    @if($monument->kulturenvanteri_id)
                        <a href="https://kulturenvanteri.com/yer/?p={{ $monument->kulturenvanteri_id }}" 
                           target="_blank"
                           class="flex items-center text-blue-600 hover:text-blue-800">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                            Kültür Envanteri
                        </a>
                    @endif
                    
                    <!-- Upload Wizard Link -->
                    @auth
                    <a href="{{ $monument->upload_wizard_url }}" 
                       target="_blank"
                       class="flex items-center text-green-600 hover:text-green-800 font-medium">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                        Fotoğraf Yükleme Sihirbazı
                    </a>
                    @else
                    <a href="{{ route('auth.login') }}" 
                       class="flex items-center text-green-600 hover:text-green-800 font-medium">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                        Fotoğraf yüklemek için giriş yapın
                    </a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</div>

@if($monument->hasCoordinates())
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const map = L.map('map').setView([{{ $monument->latitude }}, {{ $monument->longitude }}], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors | <a href="https://commons.wikimedia.org/wiki/Category:Wiki_Loves_Monuments_Turkey" target="_blank">Wikimedia Commons</a> | Made with ❤️ by <a href="https://github.com/ooguz" target="_blank">ooguz</a>'
        }).addTo(map);
        
        L.marker([{{ $monument->latitude }}, {{ $monument->longitude }}], {
            icon: L.divIcon({
                className: 'monument-marker',
                html: `<div style="background-color: #10B981; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            })
        }).addTo(map);
    });
    </script>
    @endpush
@endif

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const carousel = document.getElementById('detailCarouselTrack');
        const indicators = document.querySelectorAll('.detail-indicator');
        const prevBtn = document.getElementById('prevDetail');
        const nextBtn = document.getElementById('nextDetail');
        let currentIndex = 0;
        const totalSlides = {{ !empty($displayPhotos) ? count($displayPhotos) : 0 }};

        function updateCarousel() {
            carousel.style.transform = `translateX(-${currentIndex * 100}%)`;
        
        // Update indicators
            indicators.forEach((indicator, index) => {
                indicator.classList.toggle('bg-white', index === currentIndex);
                indicator.classList.toggle('bg-opacity-50', index !== currentIndex);
        });
    }
    
        function nextSlide() {
            currentIndex = (currentIndex + 1) % totalSlides;
            updateCarousel();
        }

        function prevSlide() {
            currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
            updateCarousel();
        }

        // Event listeners
        if (nextBtn) nextBtn.addEventListener('click', nextSlide);
        if (prevBtn) prevBtn.addEventListener('click', prevSlide);
        
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => {
                currentIndex = index;
                updateCarousel();
            });
        });

        // Auto-advance carousel
        if (totalSlides > 1) {
            setInterval(nextSlide, 5000);
        }
    });
    
    function openDetailPhotoModal(imageUrl, commonsUrl, title, photographer, license) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50';
        modal.style.zIndex = '9000';
        modal.innerHTML = `
            <button onclick="this.parentElement.remove()" class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300">&times;</button>
            <div class="relative max-w-6xl max-h-full p-4">
                <div class="text-center">
                    <img src="${imageUrl}" alt="${title}" class="max-w-full max-h-[80vh] object-contain mx-auto">
                    <div class="mt-4 text-white">
                        <h3 class="text-xl font-semibold">${title}</h3>
                        ${photographer ? `<p class="text-gray-300">by ${photographer}</p>` : ''}
                        ${license ? `<p class="text-sm text-gray-400">${license}</p>` : ''}
                        <div class="mt-4">
                            <a href="${commonsUrl}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                                View on Commons
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    </script>
    @endpush
@endsection 