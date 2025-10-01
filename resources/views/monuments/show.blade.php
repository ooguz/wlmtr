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
            <!-- Quick Upload Button -->
            @auth
            <button onclick="openQuickUploadModal()" 
               class="block w-full mb-3 bg-blue-600 hover:bg-blue-700 text-white text-center px-6 py-3 rounded-lg shadow-md font-medium transition-colors">
                <div class="flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                    Hızlı Yükle
                </div>
            </button>
            
            <!-- Upload Wizard Button -->
            <a href="{{ $monument->upload_wizard_url }}" 
               target="_blank"
               class="block w-full mb-6 bg-green-600 hover:bg-green-700 text-white text-center px-6 py-3 rounded-lg shadow-md font-medium transition-colors">
                <div class="flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                    Fotoğraf Yükleme Sihirbazı
                </div>
            </a>
            @else
            <a href="{{ route('auth.login') }}" 
               class="block w-full mb-6 bg-green-600 hover:bg-green-700 text-white text-center px-6 py-3 rounded-lg shadow-md font-medium transition-colors">
                <div class="flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                    Fotoğraf yüklemek için giriş yapın
                </div>
            </a>
            @endauth
            
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
                    <a href="{{ $monument->upload_wizard_url }}" 
                       target="_blank"
                       class="flex items-center text-blue-600 hover:text-blue-800">
                       <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        Commons Fotoğraf Yükleme Sihirbazı
                    </a>
                   
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

    // Quick Upload Modal Functions
    let selectedFile = null;
    let exifData = null;

    function openQuickUploadModal() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.capture = 'environment'; // For mobile camera
        
        input.onchange = function(e) {
            const file = e.target.files[0];
            if (file) {
                selectedFile = file;
                extractEXIFAndShowModal(file);
            }
        };
        
        input.click();
    }

    function extractEXIFAndShowModal(file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                // Try to extract EXIF data (we'll use a simple approach without external library)
                const photoDate = getPhotoDate(file);
                const title = getTitleSuggestion(file, photoDate);
                
                showUploadModal(e.target.result, title, photoDate);
            };
            img.src = e.target.result;
        };
        
        reader.readAsDataURL(file);
    }

    function getPhotoDate(file) {
        // Try to get from file's last modified date
        const modifiedDate = new Date(file.lastModified);
        return modifiedDate.toISOString().split('T')[0];
    }

    function getTitleSuggestion(file, date) {
        const monumentName = '{{ $monument->primary_name }}';
        return `${monumentName} ${date}`;
    }

    function showUploadModal(imageDataUrl, suggestedTitle, suggestedDate) {
        const monumentId = {{ $monument->id }};
        const commonsCategory = '{{ $monument->commons_category ?? "" }}';
        
        const modal = document.createElement('div');
        modal.id = 'quickUploadModal';
        modal.className = 'fixed inset-0 bg-gray-900/75 flex items-center justify-center z-50 p-0 md:p-4';
        modal.style.zIndex = '9000';
        
        modal.innerHTML = `
            <div class="bg-white h-full w-full md:h-auto md:rounded-lg md:max-w-4xl md:max-h-[90vh] overflow-y-auto">
                <div class="p-4 md:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold text-gray-900">Hızlı Fotoğraf Yükle</h2>
                        <button onclick="closeQuickUploadModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Image Preview -->
                    <div class="mb-6">
                        <img src="${imageDataUrl}" alt="Preview" class="max-w-full h-64 object-contain mx-auto rounded-lg border">
                    </div>
                    
                    <!-- Upload Form -->
                    <form id="quickUploadForm" class="space-y-4">
                        <input type="hidden" name="monument_id" value="${monumentId}">
                        
                        <!-- Title -->
                        <div>
                            <label for="photoTitle" class="block text-sm font-medium text-gray-700 mb-1">
                                Görselin Başlığı <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="photoTitle" name="title" value="${suggestedTitle}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <!-- Date -->
                        <div>
                            <label for="photoDate" class="block text-sm font-medium text-gray-700 mb-1">
                                Tarih <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="photoDate" name="date" value="${suggestedDate}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <!-- Categories -->
                        <div>
                            <label for="photoCategories" class="block text-sm font-medium text-gray-700 mb-1">
                                Kategoriler
                            </label>
                            <div id="categoriesContainer" class="mb-2 flex flex-wrap gap-2">
                                ${commonsCategory ? `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800" data-category="${commonsCategory}">
                                    ${commonsCategory}
                                    <button type="button" onclick="removeCategory(this)" class="ml-2 text-blue-600 hover:text-blue-800">×</button>
                                </span>` : ''}
                            </div>
                            <div class="flex gap-2">
                                <input type="text" id="categoryInput" placeholder="Kategori ekle"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <button type="button" onclick="addCategory()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                                    Ekle
                                </button>
                            </div>
                        </div>
                        
                        <!-- License Info -->
                        <div class="bg-gray-50 p-4 rounded-md">
                            <div class="flex items-center">
                            <img src="/by-sa.svg" class="w-5 h-5 text-gray-500 mr-2" />     
                                <svg class="w-5 h-5 text-gray-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-sm text-gray-600">
                                    Lisans: <strong>Creative Commons Atıf-BenzeriPaylaşım 4.0 Uluslararası</strong>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Success Message -->
                        <div id="uploadSuccess" class="hidden bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <div class="flex-1">
                                    <p id="uploadSuccessMessage" class="font-medium"></p>
                                    <p class="text-sm mt-1">Birkaç saniye içinde dosyaya yönlendirileceksiniz...</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Error Message -->
                        <div id="uploadError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-red-500 mt-0.5 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                <p id="uploadErrorMessage" class="flex-1"></p>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex gap-3">
                            <button type="submit" id="uploadButton" 
                                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-md transition-colors">
                                Yükle
                            </button>
                            <button type="button" onclick="closeQuickUploadModal()" 
                                    class="px-6 py-3 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                İptal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Setup form submission
        document.getElementById('quickUploadForm').onsubmit = function(e) {
            e.preventDefault();
            uploadPhoto();
        };
        
        // Allow adding categories with Enter key
        document.getElementById('categoryInput').onkeypress = function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addCategory();
            }
        };
    }

    function closeQuickUploadModal() {
        const modal = document.getElementById('quickUploadModal');
        if (modal) {
            modal.remove();
        }
        selectedFile = null;
        exifData = null;
    }

    function addCategory() {
        const input = document.getElementById('categoryInput');
        const category = input.value.trim();
        
        if (category) {
            const container = document.getElementById('categoriesContainer');
            const chip = document.createElement('span');
            chip.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800';
            chip.dataset.category = category;
            chip.innerHTML = `
                ${category}
                <button type="button" onclick="removeCategory(this)" class="ml-2 text-blue-600 hover:text-blue-800">×</button>
            `;
            container.appendChild(chip);
            input.value = '';
        }
    }

    function removeCategory(button) {
        button.parentElement.remove();
    }

    function getCategories() {
        const chips = document.querySelectorAll('#categoriesContainer span');
        return Array.from(chips).map(chip => chip.dataset.category);
    }

    async function uploadPhoto() {
        const form = document.getElementById('quickUploadForm');
        const button = document.getElementById('uploadButton');
        const errorDiv = document.getElementById('uploadError');
        const errorMessage = document.getElementById('uploadErrorMessage');
        const successDiv = document.getElementById('uploadSuccess');
        const successMessage = document.getElementById('uploadSuccessMessage');
        
        // Disable button and show loading
        button.disabled = true;
        button.innerHTML = '<svg class="animate-spin h-5 w-5 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
        errorDiv.classList.add('hidden');
        successDiv.classList.add('hidden');
        
        try {
            const formData = new FormData();
            formData.append('photo', selectedFile);
            formData.append('title', document.getElementById('photoTitle').value);
            formData.append('date', document.getElementById('photoDate').value);
            formData.append('monument_id', document.querySelector('input[name="monument_id"]').value);
            
            const categories = getCategories();
            categories.forEach((cat, index) => {
                formData.append(`categories[${index}]`, cat);
            });
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken) {
                throw new Error('CSRF token bulunamadı. Lütfen sayfayı yenileyin.');
            }
            
            const response = await fetch('{{ route("photos.upload") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken.content,
                    'Accept': 'application/json'
                },
                body: formData
            });
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error('Sunucu beklenmeyen bir yanıt döndürdü. Lütfen giriş yapıp tekrar deneyin.');
            }
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                // Show success message
                successMessage.textContent = result.message || 'Fotoğraf başarıyla yüklendi!';
                successDiv.classList.remove('hidden');
                
                // Open Commons page in new tab if descriptionurl is available
                if (result.data?.descriptionurl) {
                    window.open(result.data.descriptionurl, '_blank');
                }
                
                // Reload page after 3 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
                // Show error
                errorMessage.textContent = result.message || result.errors?.[0] || 'Yükleme başarısız oldu.';
                errorDiv.classList.remove('hidden');
                button.disabled = false;
                button.textContent = 'Yükle';
            }
        } catch (error) {
            console.error('Upload error:', error);
            errorMessage.textContent = error.message || 'Bir hata oluştu. Lütfen tekrar deneyin.';
            errorDiv.classList.remove('hidden');
            button.disabled = false;
            button.textContent = 'Yükle';
        }
    }
    </script>
    @endpush
@endsection 