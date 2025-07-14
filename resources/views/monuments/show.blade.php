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
    @if($monument->photos->count() > 0)
        <div class="mb-8">
            <div class="relative">
                <div id="detailCarousel" class="overflow-hidden rounded-lg">
                    <div id="detailCarouselTrack" class="flex transition-transform duration-300 ease-in-out">
                        @foreach($monument->photos as $photo)
                            <div class="flex-shrink-0 w-full">
                                <img src="{{ $photo->full_resolution_url }}" 
                                     alt="{{ $photo->title ?? $monument->primary_name }}"
                                     class="w-full h-96 object-cover cursor-pointer"
                                     onclick="openDetailPhotoModal('{{ $photo->full_resolution_url }}', '{{ $photo->commons_url }}', '{{ $photo->title ?? '' }}', '{{ $photo->photographer ?? '' }}', '{{ $photo->license_display_name ?? '' }}')">
                                
                                <!-- Photo Info Overlay -->
                                <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-75 text-white p-4">
                                    <div class="font-semibold">{{ $photo->title ?? 'Untitled' }}</div>
                                    @if($photo->photographer)
                                        <div class="text-sm text-gray-300">by {{ $photo->photographer }}</div>
                                    @endif
                                    @if($photo->license_display_name)
                                        <div class="text-xs text-gray-300">{{ $photo->license_display_name }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <!-- Carousel Navigation -->
                @if($monument->photos->count() > 1)
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
                @if($monument->photos->count() > 1)
                    <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2">
                    @foreach($monument->photos as $index => $photo)
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
                    <span>{{ \App\Services\WikidataSparqlService::getLabelForQCode($monument->city ?? $monument->province ?? 'Bilinmeyen konum') }}</span>
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
                    <span>{{ $monument->photo_count }} fotoğraf</span>
                    @if($monument->has_photos)
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
            
            <!-- Map -->
            @if($monument->hasCoordinates())
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Konum</h2>
                    <div id="map" class="w-full h-64 rounded-lg"></div>
                </div>
            @endif
            
            <!-- Photos -->
            @if($monument->photos->count() > 0)
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Fotoğraflar</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($monument->photos as $photo)
                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                <div class="relative">
                                <img src="{{ $photo->display_url }}" 
                                     alt="{{ $photo->title ?? $monument->primary_name }}"
                                     class="w-full h-48 object-cover">
                                    <!-- Photo Info Overlay -->
                                    <div class="absolute bottom-2 right-2 bg-black bg-opacity-70 text-white text-xs rounded px-2 py-1 flex items-center space-x-2">
                                        @php
                                            $author = $photo->photographer;
                                            $license = $photo->license_display_name;
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
                                        <a href="{{ $photo->commons_url }}" target="_blank" title="Wikimedia Commons" class="ml-1">
                                            <svg class="w-4 h-4 inline" fill="currentColor" viewBox="0 0 20 20"><path d="M12.293 2.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-8.5 8.5a1 1 0 01-.325.217l-4 1.5a1 1 0 01-1.263-1.263l1.5-4a1 1 0 01.217-.325l8.5-8.5zM15 7l-2-2-8.293 8.293-1.086 2.9 2.9-1.086L15 7z"></path></svg>
                                        </a>
                                    </div>
                                </div>
                                <div class="p-4">
                                    @if($photo->title)
                                        <h3 class="font-semibold text-gray-900 mb-2">{{ $photo->title }}</h3>
                                    @endif
                                    @if($photo->photographer)
                                        <p class="text-sm text-gray-600 mb-2">Fotoğrafçı: {{ $photo->photographer }}</p>
                                    @endif
                                    @if($photo->formatted_date_taken)
                                        <p class="text-sm text-gray-500 mb-2">{{ $photo->formatted_date_taken }}</p>
                                    @endif
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-gray-500">{{ $photo->license_display_name }}</span>
                                        <a href="{{ $photo->full_resolution_url }}" 
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
                        <p class="mt-1 text-sm text-gray-500">Bu anıtın fotoğrafını çekmek için ilk siz olun!</p>
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
                    
                    @if($monument->construction_date)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">İnşa Tarihi</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $monument->construction_date }}</dd>
                        </div>
                    @endif
                    
                    @if($monument->architect)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Mimar</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $monument->architect }}</dd>
                        </div>
                    @endif
                    
                    @if($monument->style)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Mimari Stil</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $monument->style }}</dd>
                        </div>
                    @endif
                    
                    @if($monument->material)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Malzeme</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $monument->material }}</dd>
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
                    
                    @if($monument->city)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Şehir</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ \App\Services\WikidataSparqlService::getLabelForQCode($monument->city) }}
                            </dd>
                        </div>
                    @endif
                    
                    @if($monument->district)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">İlçe</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ \App\Services\WikidataSparqlService::getLabelForQCode($monument->district) }}
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
                            Wikipedia (TR)
                        </a>
                    @endif
                    
                    @if($monument->wikipedia_en_url)
                        <a href="{{ $monument->wikipedia_en_url }}" 
                           target="_blank"
                           class="flex items-center text-blue-600 hover:text-blue-800">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                            Wikipedia (EN)
                        </a>
                    @endif
                    
                    @if($monument->commons_url)
                        <a href="{{ $monument->commons_url }}" 
                           target="_blank"
                           class="flex items-center text-blue-600 hover:text-blue-800">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                            Wikimedia Commons
                        </a>
                    @endif
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
            attribution: '© OpenStreetMap contributors | © <a href="https://leafletjs.com/" target="_blank">Leaflet</a> | <a href="https://commons.wikimedia.org/wiki/Category:Wiki_Loves_Monuments_Turkey" target="_blank">Wikimedia Commons</a> | Made with ❤️ by <a href="https://github.com/m3rcury" target="_blank">m3rcury</a>'
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
        const totalSlides = {{ $monument->photos->count() }};

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
        modal.innerHTML = `
            <div class="relative max-w-6xl max-h-full p-4">
                <button onclick="this.parentElement.parentElement.remove()" class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300">&times;</button>
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