@extends('layouts.map')

@section('title', 'Anıt Haritası')

@section('content')
<!-- Loading Spinner -->
<div id="loadingSpinner" class="fixed inset-0 bg-white bg-opacity-90 flex items-center justify-center" style="z-index: 99999;">
    <div class="text-center">
        <div class="relative inline-block mb-4" style="height: 120px; width: 120px;">
            <img src="/wlm-logo.svg" alt="WLM Turkey" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-14 w-14" style="z-index: 2;">
            <div class="absolute inset-1 rounded-full border-8 border-blue-200"></div>
            <div class="absolute inset-0 rounded-full border-8 border-blue-600 border-t-transparent animate-spin shadow-md"></div>
        </div>
        <p class="text-lg font-medium text-gray-700">Anıtlar yükleniyor...</p>
        <p class="text-sm text-gray-500 mt-2">Lütfen bekleyin</p>
    </div>
</div>

<div class="fixed inset-0">
    <!-- Map Container -->
    <div id="map" class="w-full h-full"></div>
    
    <!-- Search Panel - hidden on mobile by default; opened via mobile search toggle -->
    <div id="searchPanel" class="absolute top-4 left-4 z-20 bg-white rounded-lg shadow-lg p-4 w-80 max-h-[calc(120vh-8rem)] overflow-y-auto hidden md:block">
        <h3 class="text-lg font-semibold mb-4">Anıt ara <i class="cc-icon cc-cc text-gray-500"></i><i class="cc-icon cc-by text-gray-500"></i><i class="cc-icon cc-sa text-gray-500"></i></h3>
        
        <!-- Search Input -->
        <div class="mb-4">
            <input type="text" 
                   id="searchInput" 
                   placeholder="Anıt adı veya açıklama ara..." 
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <!-- Filters -->
        <div class="space-y-3">
            <!-- Province Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">İl</label>
                <select id="provinceFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tüm iller</option>
                </select>
            </div>
            
            <!-- Category Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                <select id="categoryFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tüm kategoriler</option>
                </select>
            </div>
            
            <!-- Photo Status Toggle -->
            <div>
                <label class="flex items-center space-x-3">
                    <input type="checkbox" 
                           id="photoToggle" 
                           class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="text-sm font-medium text-gray-700">Sadece fotoğrafı olmayanları göster</span>
                </label>
            </div>
            
            <!-- Separator: location-based options -->
            <div class="my-3 border-t border-gray-200"></div>

            <!-- Distance Filter (location-based) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mesafe (km)</label>
                <input type="range" 
                       id="distanceFilter" 
                       min="5" 
                       max="100" 
                       value="50" 
                       class="w-full">
                <div class="flex justify-between text-xs text-gray-500">
                    <span>5km</span>
                    <span id="distanceValue">50km</span>
                    <span>100km</span>
                </div>
            </div>
        </div>
        
        <!-- Location Button (location-based) -->
        <button id="locationBtn" 
                class="w-full mt-4 bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            📍 Konumumu Kullan
        </button>

        <!-- Actions -->
        <div class="mt-4 flex gap-2">
            <button id="searchBtn" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Ara
            </button>
            <button id="clearFiltersBtn" class="flex-1 bg-gray-100 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 hidden">
                Filtreleri Temizle
            </button>
        </div>
        
        <!-- Close Button for Mobile -->
        <button id="closeSearchPanel" 
                class="md:hidden w-full mt-2 bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
            Kapat
        </button>
    </div>


    <!-- Floating Nav Cards - Positioned to avoid zoom controls -->
    <div class="floating-nav-cards absolute top-4 right-4 z-30 flex flex-col gap-3">
        <a href="https://commons.wikimedia.org/wiki/Commons:Wiki_Loves_Monuments_2025_in_Turkey/tr" target="_blank" class="bg-white/90 backdrop-blur rounded-xl shadow-md px-4 py-3 hover:bg-white">
            <div class="flex items-center gap-2">
                <span><img src="/wlm-logo.svg" alt="WLM Turkey" class="h-4"></span>
                <div>
                    <div class="text-sm font-semibold">Viki Anıtları Seviyor</div>
                    <div class="text-xs text-gray-500">Yarışma hakkında</div>
                </div>
            </div>
        </a>
        <a href="{{ route('monuments.list') }}" class="bg-white/90 backdrop-blur rounded-xl shadow-md px-4 py-3 hover:bg-white">
            <div class="flex items-center gap-2">
                <span>🏛️</span>
                <div>
                    <div class="text-sm font-semibold">Liste</div>
                    <div class="text-xs text-gray-500">Tüm anıtları görüntüle</div>
                </div>
            </div>
        </a>
        @auth
        <a href="{{ route('auth.profile') }}" class="bg-white/90 backdrop-blur rounded-xl shadow-md px-4 py-3 hover:bg-white">
            <div class="flex items-center gap-2">
                <span>👤</span>
                <div>
                    <div class="text-sm font-semibold">{{ auth()->user()->wikimedia_username }}</div>
                    <div class="text-xs text-gray-500">Profiliniz ve ayarlar</div>
                </div>
            </div>
        </a>
        @else
        <a href="{{ route('auth.login') }}" class="bg-white/90 backdrop-blur rounded-xl shadow-md px-4 py-3 hover:bg-white">
            <div class="flex items-center gap-2">
                <span>➜]</span>
                <div>
                    <div class="text-sm font-semibold">Giriş Yap</div>
                    <div class="text-xs text-gray-500">Wikimedia hesabınız ile</div>
                </div>
            </div>
        </a>
        @endauth
    </div>
    
    <!-- Monument Info Panel -->
    <div id="monumentInfo" class="absolute top-4 left-4 z-20 bg-white rounded-lg shadow-lg p-4 w-80 max-h-[calc(120vh-8rem)] overflow-y-auto hidden">
        <div class="flex justify-between items-start mb-3">
            <h3 id="monumentTitle" class="text-lg font-semibold"></h3>
            <button id="closeInfo" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Photo Carousel -->
        <div id="photoCarousel" class="mb-3 hidden">
            <div class="relative">
                <div id="carouselContainer" class="overflow-hidden rounded-lg">
                    <div id="carouselTrack" class="flex transition-transform duration-300 ease-in-out"></div>
                </div>
                
                <!-- Carousel Navigation -->
                <button id="prevPhoto" class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white rounded-full p-1 hover:bg-opacity-75">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>
                <button id="nextPhoto" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white rounded-full p-1 hover:bg-opacity-75">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
                

            </div>
            
            <!-- Carousel Indicators -->
            <div id="carouselIndicators" class="flex justify-center mt-2 space-x-1"></div>
        </div>
        
        <div id="monumentDescription" class="text-sm text-gray-600 mb-3"></div>
        {{-- <details class="mb-3">
            <summary class="text-sm font-medium cursor-pointer">Geçici JSON</summary>
            <pre id="monumentJson" class="text-[11px] bg-gray-100 rounded p-2 overflow-x-auto whitespace-pre-wrap"></pre>
        </details> --}}
        
        <div class="flex items-center justify-between text-sm text-gray-500 mb-3">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span id="monumentProvince"></span>
            </div>
            <span id="monumentPhotoCount"></span>
        </div>
        
        <div class="flex space-x-2">
            <a id="monumentDetailsLink" 
               href="#" 
               class="flex-1 bg-blue-600 text-white text-center px-3 py-2 rounded-md hover:bg-blue-700 text-sm">
                Detaylar
            </a>
            <a id="monumentWikidataLink" 
               href="#" 
               target="_blank" 
               class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 text-sm">
                Wikidata
            </a>
        </div>
        
        <!-- Quick Upload Button (Authenticated Users Only) -->
        @auth
        <div class="mt-3">
            <button id="quickUploadBtn" 
                    onclick="quickUpload()" 
                    class="w-full bg-green-600 text-white text-center px-3 py-2 rounded-md hover:bg-green-700 text-sm font-medium flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 -960 960 960"><path d="M480-480ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h320v80H200v560h560v-320h80v320q0 33-23.5 56.5T760-120H200Zm40-160h480L570-480 450-320l-90-120-120 160Zm440-320v-80h-80v-80h80v-80h80v80h80v80h-80v80h-80Z"/></svg>
                Hızlı fotoğraf yükle
            </button>
        </div>
        @endauth
        
        <!-- Upload Wizard Link -->
        <div class="mt-3">
            <a id="monumentUploadWizardLink" 
               href="#" 
               @guest
               data-auth="false"
               @else
               target="_blank"
               data-auth="true"
               @endguest
               class="w-full bg-gray-600 text-white text-center px-3 py-2 rounded-md hover:bg-gray-700 text-sm font-medium flex items-center justify-center">
               @auth
               <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 -960 960 960"><path d="m176-120-56-56 301-302-181-45 198-123-17-234 179 151 216-88-87 217 151 178-234-16-124 198-45-181-301 301Zm24-520-80-80 80-80 80 80-80 80Zm355 197 48-79 93 7-60-71 35-86-86 35-71-59 7 92-79 49 90 22 23 90Zm165 323-80-80 80-80 80 80-80 80ZM569-570Z"/></svg>
               @else
               <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 -960 960 960"><path d="M480-120v-80h280v-560H480v-80h280q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H480Zm-80-160-55-58 102-102H120v-80h327L345-622l55-58 200 200-200 200Z"/></svg>
               @endauth
                <span id="monumentUploadWizardText">Detaylı yükleme sihirbazı</span>
            </a>
        </div>
    </div>
    
    <!-- Mobile Search Toggle Button -->
    <button id="mobileSearchToggle" 
            class="md:hidden absolute top-4 left-4 z-30 bg-blue-600 text-white p-3 rounded-full shadow-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
    </button>

    <!-- Bottom-right Map Controls -->
    <div class="absolute bottom-4 right-4 z-30 flex flex-col items-end gap-3" style="z-index: 999 !important; bottom: 48px;">
        <!-- Zoom controls: vertical + / - -->
        <div class="bg-white/90 backdrop-blur rounded-xl shadow-md flex flex-col overflow-hidden">
            <button id="zoomInBtn" class="px-4 py-3 hover:bg-white text-gray-700" aria-label="Yakınlaştır">+</button>
            <div class="h-px bg-gray-200"></div>
            <button id="zoomOutBtn" class="px-4 py-3 hover:bg-white text-gray-700" aria-label="Uzaklaştır">-</button>
        </div>

        <!-- Location control: Material icon, circular -->
        <button id="mapLocateButton" class="bg-white/90 backdrop-blur rounded-full shadow-md hover:bg-white text-gray-700 w-12 h-12 flex items-center justify-center" aria-label="Konumumu Kullan">
            <svg class="w-6 h-6" viewBox="0 -960 960 960" fill="#0a0a0a" aria-hidden="true">
<path d="M440-42v-80q-125-14-214.5-103.5T122-440H42v-80h80q14-125 103.5-214.5T440-838v-80h80v80q125 14 214.5 103.5T838-520h80v80h-80q-14 125-103.5 214.5T520-122v80h-80Zm40-158q116 0 198-82t82-198q0-116-82-198t-198-82q-116 0-198 82t-82 198q0 116 82 198t198 82Zm0-120q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47Zm0-80q33 0 56.5-23.5T560-480q0-33-23.5-56.5T480-560q-33 0-56.5 23.5T400-480q0 33 23.5 56.5T480-400Zm0-80Z"/>            </svg>
        </button>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    const map = L.map('map', { zoomControl: false }).setView([39.0, 34.5], 6); // Wider center to include east/west margins
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors | <a href="https://commons.wikimedia.org/wiki/Category:Wiki_Loves_Monuments_Turkey" target="_blank">Wikimedia Commons</a> | Made with ❤️ by <a href="https://github.com/ooguz" target="_blank">ooguz</a>'
    }).addTo(map);
    
    // Clustering
    const markerCluster = L.markerClusterGroup({
        showCoverageOnHover: false,
        maxClusterRadius: 45
    });
    map.addLayer(markerCluster);

    // Store markers
    let markers = [];
    let allMarkers = [];
    let categoryIdToQid = {};
    let currentInfoWindow = null;
    
    // Mobile search panel toggle
    const searchPanel = document.getElementById('searchPanel');
    const closeSearchPanel = document.getElementById('closeSearchPanel');
    const mobileSearchToggle = document.getElementById('mobileSearchToggle');
    // Track search panel visibility before opening info panel (for proper restore on close)
    let searchPanelWasVisibleBeforeInfo = false;
    let searchPanelWasMobileOpenBeforeInfo = false;
    
    if (mobileSearchToggle) {
        mobileSearchToggle.addEventListener('click', function() {
            searchPanel.classList.remove('hidden');
            searchPanel.classList.add('mobile-open');
        });
    }
    
    if (closeSearchPanel) {
        closeSearchPanel.addEventListener('click', function() {
            searchPanel.classList.add('hidden');
            searchPanel.classList.remove('mobile-open');
        });
    }
    
    // Load monuments after first tile loads, using single Turkey-wide request
    let firstTileLoaded = false;
    let hasTurkeyDatasetLoaded = false;
    
    map.on('tileload', function() {
        if (!firstTileLoaded) {
            firstTileLoaded = true;
            setTimeout(fetchTurkeyWideMarkers, 500);
        }
    });
    
    // Fallback: load monuments after 5 seconds even if no tiles load
    setTimeout(function() {
        if (!firstTileLoaded) {
            fetchTurkeyWideMarkers();
        }
    }, 5000);
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const provinceFilter = document.getElementById('provinceFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const photoToggle = document.getElementById('photoToggle');
    const distanceFilter = document.getElementById('distanceFilter');
    const distanceValue = document.getElementById('distanceValue');
    const locationBtn = document.getElementById('locationBtn');
    const mapLocateButton = document.getElementById('mapLocateButton');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const searchBtn = document.getElementById('searchBtn');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    
    // Update distance display
    distanceFilter.addEventListener('input', function() {
        distanceValue.textContent = this.value + 'km';
    });
    
    // Determine if filters are active
    function isFiltered() {
        return (
            (searchInput.value && searchInput.value.trim() !== '') ||
            (provinceFilter.value && provinceFilter.value !== '') ||
            (categoryFilter.value && categoryFilter.value !== '') ||
            photoToggle.checked
        );
    }

    function updateClearButtonVisibility() {
        if (clearFiltersBtn) {
            clearFiltersBtn.classList.toggle('hidden', !isFiltered());
        }
    }

    // Compute filtered markers from the full dataset
    function getFilteredMarkers() {
        let result = allMarkers.slice();

        const q = (searchInput.value || '').trim().toLowerCase();
        const provinceVal = (provinceFilter.value || '').trim();
        const categoryRaw = (categoryFilter.value || '').trim();
        const onlyWithoutPhotos = !!photoToggle.checked;

        // Photos filter
        if (onlyWithoutPhotos) {
            result = result.filter(m => !m.monument.has_photos);
        }

        // Province filter (monument.province is a code/QID)
        if (provinceVal) {
            result = result.filter(m => (m.monument.province || '') === provinceVal);
        }

        // Category filter (supports id, QID, or label)
        if (categoryRaw) {
            let catQid = null;
            let catLabel = null;
            if (/^Q\d+$/i.test(categoryRaw)) {
                catQid = categoryRaw.toUpperCase();
            } else if (/^\d+$/.test(categoryRaw)) {
                catQid = (categoryIdToQid[categoryRaw] || '').toUpperCase() || null;
            } else {
                catLabel = categoryRaw.toLowerCase();
            }

            if (catQid) {
                result = result.filter(m => ((m.monument.category_qid || m.monument.type_qid || '').toUpperCase()) === catQid);
            } else if (catLabel) {
                result = result.filter(m => ((m.monument.category_label_tr || m.monument.type_label_tr || '').toLowerCase().includes(catLabel)));
            }
        }

        // Search filter
        if (q) {
            result = result.filter(m => {
                const name = (m.monument.name || '').toLowerCase();
                const desc = (m.monument.description || '').toLowerCase();
                const typeLabel = (m.monument.type_label_tr || '').toLowerCase();
                return name.includes(q) || desc.includes(q) || typeLabel.includes(q);
            });
        }

        return result;
    }

    // Apply current filters and render markers
    function applyFilters() {
        markers = getFilteredMarkers();
        markerCluster.clearLayers();
        markerCluster.addLayers(markers);

        updateClearButtonVisibility();
        if (window.innerWidth < 768) {
            searchPanel.classList.add('hidden');
            searchPanel.classList.remove('mobile-open');
        }
    }
    
    // Apply only when the user clicks 'Ara'
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            applyFilters();
        });
    }

    // Clear filters
    function clearFilters() {
        searchInput.value = '';
        provinceFilter.value = '';
        categoryFilter.value = '';
        photoToggle.checked = false;
        distanceFilter.value = 50;
        distanceValue.textContent = '50km';
        // Reapply (shows all)
        applyFilters();
    }

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            clearFilters();
        });
    }

    // Initialize clear button visibility on load
    updateClearButtonVisibility();
    
    // Location functionality (shared)
    function locateUserAndFilter() {
        if (!navigator.geolocation) {
            alert('Tarayıcınız konum özelliğini desteklemiyor.');
            return;
        }
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            // Center map on user location
            map.setView([lat, lng], 12);

            // Add or update user location marker
            L.marker([lat, lng])
                .addTo(map)
                .bindPopup('Konumunuz')
                .openPopup();

            // Filter monuments by distance
            const distance = parseInt(distanceFilter.value);
            filterByDistance(lat, lng, distance);
        }, function(error) {
            alert('Konum alınamadı: ' + error.message);
        });
    }

    if (locationBtn) {
        locationBtn.addEventListener('click', locateUserAndFilter);
    }
    if (mapLocateButton) {
        mapLocateButton.addEventListener('click', locateUserAndFilter);
    }

    // Zoom controls
    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', function() { map.zoomIn(); });
    }
    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', function() { map.zoomOut(); });
    }
    
    // Filter monuments by distance from user location
    function filterByDistance(userLat, userLng, maxDistance) {
        markers.forEach(marker => {
            const monumentLat = marker.monument.coordinates.lat;
            const monumentLng = marker.monument.coordinates.lng;
            
            const distance = calculateDistance(userLat, userLng, monumentLat, monumentLng);
            
            if (distance <= maxDistance) {
                marker.addTo(map);
            } else {
                marker.remove();
            }
        });
    }
    
    // Calculate distance between two points (Haversine formula)
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Earth's radius in kilometers
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }
    
    // Build API URL for Turkey-wide markers (optionally with filters)
    function buildTurkeyMarkersUrl() {
        const zoom = map.getZoom();
        const params = new URLSearchParams();
        params.set('coverage', 'turkey');
        params.set('zoom', zoom);
        // Send filters to backend (server-side filtering)
        const q = (searchInput.value || '').trim();
        if (q) params.set('q', q);
        if (provinceFilter.value) params.set('province', provinceFilter.value);
        if (categoryFilter.value) params.set('category', categoryFilter.value);
        if (photoToggle.checked) params.set('has_photos', '0');
        return '/api/monuments/map-markers?' + params.toString();
    }

    // Debounced viewport-based fetch
    let debounceTimer = null;
    function scheduleFetchMarkers() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(fetchMarkersForCurrentView, 350);
    }

    // Do NOT refetch on panning. Only consider refetching on zoom when filtered.
    map.on('zoomend', function() {
        if (isFiltered()) {
            scheduleFetchMarkers();
        }
    });

    function fetchTurkeyWideMarkers() {
        const loadingSpinner = document.getElementById('loadingSpinner');
        const url = buildTurkeyMarkersUrl();
        
        // Show spinner only if nothing is on the map yet
        const shouldShowSpinner = markers.length === 0;
        if (shouldShowSpinner) {
            loadingSpinner.style.display = 'flex';
        }
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('API response not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // Update markers collection (full dataset)
                allMarkers = [];
                markers = [];
                markerCluster.clearLayers();

                (data || []).forEach(item => {
                    const marker = L.circleMarker([item.coordinates.lat, item.coordinates.lng], {
                        radius: 6,
                        fillColor: '#3B82F6',
                        color: '#1E40AF',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    });
                    marker.type = 'marker';
                    marker.monument = item;
                    allMarkers.push(marker);
                    
                    // Click event with lazy detail fetch when needed
                    marker.on('click', function() {
                        // Always fetch full details to ensure freshest data in panel
                        if (item && item.id) {
                            fetch(`/api/monuments/${item.id}`)
                                .then(r => r.json())
                                .then(full => {
                                    // Merge lightweight item with full details (keep featured_photo if already present)
                                    const merged = Object.assign({}, item, full);
                                    if (!merged.featured_photo && item.featured_photo) {
                                        merged.featured_photo = item.featured_photo;
                                    }
                                    showMonumentInfo(merged);
                                })
                                .catch(() => showMonumentInfo(item));
                        } else {
                            showMonumentInfo(item);
                        }
                    });
                });

                // Respect current filters when adding to cluster
                applyFilters();

                hasTurkeyDatasetLoaded = true;

                // Load provinces for filter (only once)
                if (!window._filtersLoadedOnce) {
                    window._filtersLoadedOnce = true;
                    loadProvinces();
                }

                if (shouldShowSpinner) {
                    loadingSpinner.style.display = 'none';
                }
            })
            .catch(() => {
                if (shouldShowSpinner) {
                    loadingSpinner.style.display = 'none';
                }
            });
    }

    // Load monuments (reused by filter submits); if the Turkey dataset is already loaded and no filters are active, skip network
    function fetchMarkersForCurrentView() {
        if (hasTurkeyDatasetLoaded && !isFiltered() && markers.length > 0) {
            return; // Already have data; re-clustering will adapt to zoom
        }
        return fetchTurkeyWideMarkers();
    }
    
    // Get province name from Wikidata
    function getProvinceName(provinceCode) {
        if (!provinceCode) {
            return Promise.resolve('Bilinmeyen konum');
        }
        
        return fetch(`/api/wikidata/label/${encodeURIComponent(provinceCode)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch province name');
                }
                return response.json();
            })
            .then(data => {
                return data.label || provinceCode;
            })
            .catch(error => {
                console.error('Error fetching province name:', error);
                return provinceCode; // Fallback to original code
            });
    }
    
    // Load provinces and categories for filter
    function loadProvinces() {
        fetch('/api/monuments/filters')
            .then(response => response.json())
            .then(data => {
                // Load provinces
                const provinceFilter = document.getElementById('provinceFilter');
                data.provinces.forEach(province => {
                    const option = document.createElement('option');
                    option.value = province;
                    option.textContent = province;
                    provinceFilter.appendChild(option);
                });
                
                // Load categories
                const categoryFilter = document.getElementById('categoryFilter');
                data.categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = String(category.id);
                    if (category.wikidata_id) {
                        option.setAttribute('data-qid', category.wikidata_id);
                        categoryIdToQid[String(category.id)] = category.wikidata_id;
                    }
                    option.textContent = `${category.name} (${category.monument_count})`;
                    categoryFilter.appendChild(option);
                });
            })
            .catch(error => {
                // Fail soft if filters API fails to avoid hard error loops
                console.warn('Filters unavailable');
            });
    }
    
    // Show monument info panel
    function showMonumentInfo(monument) {
        const infoPanel = document.getElementById('monumentInfo');
        const title = document.getElementById('monumentTitle');
        const description = document.getElementById('monumentDescription');
        const province = document.getElementById('monumentProvince');
        const photoCount = document.getElementById('monumentPhotoCount');
        const detailsLink = document.getElementById('monumentDetailsLink');
        const wikidataLink = document.getElementById('monumentWikidataLink');
        const photoCarousel = document.getElementById('photoCarousel');
        const carouselTrack = document.getElementById('carouselTrack');
        const carouselIndicators = document.getElementById('carouselIndicators');
        const uploadWizardLink = document.getElementById('monumentUploadWizardLink');
        
        title.textContent = monument.name;
        description.textContent = monument.description || 'Açıklama bulunmuyor.';
        
        // Prefer full location hierarchy if available
        if (monument.location_hierarchy_tr) {
            province.textContent = monument.location_hierarchy_tr;
        } else if (monument.admin_area) {
            province.textContent = monument.admin_area;
        } else if (monument.city && monument.province) {
            province.textContent = `${monument.city}, ${monument.province}`;
        } else if (monument.province) {
            getProvinceName(monument.province).then(provinceName => {
                province.textContent = provinceName;
            }).catch(() => {
                province.textContent = monument.province;
            });
        } else if (monument.city) {
            province.textContent = monument.city;
        } else {
            province.textContent = 'Bilinmeyen konum';
        }
        
        photoCount.textContent = `${monument.photo_count} fotoğraf`;
        detailsLink.href = `/monuments/${monument.id}`;
        wikidataLink.href = `https://www.wikidata.org/wiki/${monument.wikidata_id}`;
        
        // Build upload wizard URL or login link based on auth status
        const isAuthenticated = uploadWizardLink.getAttribute('data-auth') === 'true';
        const uploadWizardText = document.getElementById('monumentUploadWizardText');
        
        if (isAuthenticated) {
            const uploadWizardUrl = buildUploadWizardUrl(monument);
            uploadWizardLink.href = uploadWizardUrl;
            uploadWizardText.textContent = 'Fotoğraf Yükleme Sihirbazı';
            uploadWizardLink.setAttribute('target', '_blank');
        } else {
            uploadWizardLink.href = '{{ route("auth.login") }}';
            uploadWizardText.textContent = 'Fotoğraf yüklemek için giriş yapın';
            uploadWizardLink.removeAttribute('target');
        }
        
        // Handle photo preview/carousel
        if (monument.photos && monument.photos.length > 0) {
            setupPhotoCarousel(monument.photos);
            photoCarousel.classList.remove('hidden');
        } else if (monument.featured_photo) {
            let fp = monument.featured_photo;
            let photoObj;
            if (typeof fp === 'string') {
                photoObj = {
                    full_resolution_url: fp,
                    display_url: fp,
                    title: monument.name,
                    photographer: null,
                    license: null,
                    commons_url: null,
                };
            } else {
                photoObj = {
                    full_resolution_url: fp.full_resolution_url || fp.display_url || null,
                    display_url: fp.display_url || fp.full_resolution_url || null,
                    title: fp.title || monument.name,
                    photographer: fp.photographer || null,
                    license: fp.license || null,
                    commons_url: fp.commons_url || null,
                };
            }
            setupPhotoCarousel([photoObj]);
            photoCarousel.classList.remove('hidden');
        } else {
            photoCarousel.classList.add('hidden');
        }
        
        infoPanel.classList.remove('hidden');
        
        // Remember current search panel state, then hide it to avoid overlap
        searchPanelWasVisibleBeforeInfo = !searchPanel.classList.contains('hidden');
        searchPanelWasMobileOpenBeforeInfo = searchPanel.classList.contains('mobile-open');
        searchPanel.classList.add('hidden');
        searchPanel.classList.remove('mobile-open');
        
        // On mobile, make info panel full screen
        if (window.innerWidth < 768) {
            infoPanel.classList.add('mobile-open');
        }

        // Fetch full JSON for this monument and display
        // fetch(`/api/monuments/${monument.id}?raw=1`)
        //     .then(r => r.json())
        //     .then(full => {
        //         jsonBox.textContent = JSON.stringify(full, null, 2);
        //     })
        //     .catch(() => {
        //         jsonBox.textContent = JSON.stringify(monument, null, 2);
        //     });
    }
    
    // Setup photo carousel
    function setupPhotoCarousel(photos) {
        const carouselTrack = document.getElementById('carouselTrack');
        const carouselIndicators = document.getElementById('carouselIndicators');
        
        // Clear previous content
        carouselTrack.innerHTML = '';
        carouselIndicators.innerHTML = '';
        
        // Add photos to carousel
        photos.forEach((photo, index) => {
            // Overlay logic
            let author = photo.photographer;
            let license = photo.license;
            let isPublicDomain = (license && (license.toLowerCase().includes('public domain') || license.toLowerCase() === 'cc0'));
            let overlayText = '';
            if (isPublicDomain) {
                overlayText = 'Public domain';
            } else if (author && license) {
                overlayText = `&copy; ${author} | ${license}`;
            } else if (author) {
                overlayText = `&copy; ${author}`;
            } else if (license) {
                overlayText = license;
            }
            let overlayHtml = '';
            if (overlayText) {
                if (photo.commons_url) {
                    overlayHtml = `<a href="${photo.commons_url}" target="_blank" class="photo-overlay" style="position:absolute;bottom:0.4em;right:0.4em;pointer-events:auto;">${overlayText}</a>`;
                } else {
                    overlayHtml = `<div class="photo-overlay" style="position:absolute;bottom:0.4em;right:0.4em;pointer-events:none;">${overlayText}</div>`;
                }
            }
            const slide = document.createElement('div');
            slide.className = 'flex-shrink-0 w-full relative';
            slide.innerHTML = `
                <img src="${photo.full_resolution_url}" 
                     alt="${photo.title || 'Monument photo'}" 
                     class="w-full h-48 object-cover cursor-pointer">
                ${overlayHtml}
            `;
            carouselTrack.appendChild(slide);
            
            // Add indicator
            const indicator = document.createElement('button');
            indicator.className = `w-2 h-2 rounded-full ${index === 0 ? 'bg-blue-600' : 'bg-gray-300'}`;
            indicator.onclick = () => goToSlide(index);
            carouselIndicators.appendChild(indicator);
        });
        

        
        // Store photos for navigation
        carouselTrack.photos = photos;
        carouselTrack.currentIndex = 0;
    }
    

    
    // Navigate to specific slide
    function goToSlide(index) {
        const carouselTrack = document.getElementById('carouselTrack');
        const indicators = document.querySelectorAll('#carouselIndicators button');
        
        if (index < 0 || index >= carouselTrack.photos.length) return;
        
        carouselTrack.currentIndex = index;
        carouselTrack.style.transform = `translateX(-${index * 100}%)`;
        
        // Update indicators
        indicators.forEach((indicator, i) => {
            indicator.className = `w-2 h-2 rounded-full ${i === index ? 'bg-blue-600' : 'bg-gray-300'}`;
        });
        

    }
    
    // Next photo
    function nextPhoto() {
        const carouselTrack = document.getElementById('carouselTrack');
        if (carouselTrack.photos && carouselTrack.currentIndex < carouselTrack.photos.length - 1) {
            goToSlide(carouselTrack.currentIndex + 1);
        }
    }
    
    // Previous photo
    function prevPhoto() {
        const carouselTrack = document.getElementById('carouselTrack');
        if (carouselTrack.photos && carouselTrack.currentIndex > 0) {
            goToSlide(carouselTrack.currentIndex - 1);
        }
    }
    
    // Open photo modal
    function openPhotoModal(imageUrl, commonsUrl, title, photographer, license) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="relative max-w-4xl max-h-full p-4">
                <button onclick="this.parentElement.parentElement.remove()" class="absolute top-2 right-2 text-white text-2xl">&times;</button>
                <img src="${imageUrl}" alt="${title || 'Full size photo'}" class="max-w-full max-h-full object-contain">
                <div class="mt-4 text-center text-white">
                    ${title ? `<h3 class="text-xl font-semibold mb-2">${title}</h3>` : ''}
                    ${photographer ? `<p class="text-gray-300 mb-2">by ${photographer}</p>` : ''}
                    ${license ? `<p class="text-sm text-gray-400 mb-4">${license}</p>` : ''}
                    <a href="${commonsUrl}" target="_blank" class="text-blue-400 hover:text-blue-300">View on Commons</a>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Add carousel navigation event listeners
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('nextPhoto').addEventListener('click', nextPhoto);
        document.getElementById('prevPhoto').addEventListener('click', prevPhoto);
    });
    
    // Close monument info panel
    document.getElementById('closeInfo').addEventListener('click', function() {
        const infoPanel = document.getElementById('monumentInfo');
        infoPanel.classList.add('hidden');
        infoPanel.classList.remove('mobile-open');
        // Restore search panel to its previous state (do not force open if it was closed)
        if (searchPanelWasVisibleBeforeInfo) {
            searchPanel.classList.remove('hidden');
        } else {
            searchPanel.classList.add('hidden');
        }
        if (searchPanelWasMobileOpenBeforeInfo) {
            searchPanel.classList.add('mobile-open');
        } else {
            searchPanel.classList.remove('mobile-open');
        }
        // Reset flags
        searchPanelWasVisibleBeforeInfo = false;
        searchPanelWasMobileOpenBeforeInfo = false;
    });
    
    // Build upload wizard URL for monument
    function buildUploadWizardUrl(monument) {
        const baseUrl = 'https://commons.wikimedia.org/wiki/Special:UploadWizard';
        
        // Build description with monument name and WLM template
        let description = monument.name;
        if (monument.wikidata_id) {
            description += '\n\{\{on Wikidata|' + monument.wikidata_id + '\}\}';
            description += '\n\{\{Load via app WLM.tr|year=' + new Date().getFullYear() + '|source=wizard\}\}';

        }
        
        const params = {
            description: description,
            descriptionlang: 'tr',
            campaign: 'wlm-tr',
        };
        params.categories = "";
        if (monument.province == null) {
            params.categories += monument.location_hierarchy_tr.split(',')[0];
        } else {
            params.categories += monument.province;
        }
        if (monument.commons_category) {
            params.categories += '|' + monument.commons_category;
        }
        // Add categories based on location hierarchy
            console.log(monument.latitude, monument.longitude);
            console.log(monument);
            params.lat = monument.coordinates['lat'];
            params.lon = monument.coordinates['lng'];
        
        
        // Add monument ID if available
        if (monument.wikidata_id) {
            params.id = monument.wikidata_id;
        }
        
        return baseUrl + '?' + new URLSearchParams(params).toString();
    }
    
    // Quick Upload functionality
    let selectedFile = null;
    let currentMonument = null;
    
    // Store current monument when showing info panel
    const originalShowMonumentInfo = showMonumentInfo;
    showMonumentInfo = function(monument) {
        currentMonument = monument;
        originalShowMonumentInfo(monument);
    };
    
    window.quickUpload = function() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.capture = 'environment';
        
        input.onchange = function(e) {
            const file = e.target.files[0];
            if (file) {
                selectedFile = file;
                extractEXIFAndShowModal(file);
            }
        };
        
        input.click();
    };
    
    function extractEXIFAndShowModal(file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const photoDate = getPhotoDate(file);
                const title = getTitleSuggestion(file, photoDate);
                showUploadModal(e.target.result, title, photoDate);
            };
            img.src = e.target.result;
        };
        
        reader.readAsDataURL(file);
    }
    
    function getPhotoDate(file) {
        const modifiedDate = new Date(file.lastModified);
        return modifiedDate.toISOString().split('T')[0];
    }
    
    function getTitleSuggestion(file, date) {
        const monumentName = currentMonument ? currentMonument.name : 'Monument';
        return `${monumentName} ${date}`;
    }
    
    function showUploadModal(imageDataUrl, suggestedTitle, suggestedDate) {
        const monumentId = currentMonument ? currentMonument.id : null;
        const commonsCategory = currentMonument ? (currentMonument.commons_category || '') : '';
        
        const modal = document.createElement('div');
        modal.id = 'quickUploadModal';
        modal.className = 'fixed inset-0 bg-gray-900/75 flex items-center justify-center p-0 md:p-4';
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
                    
                    <div class="mb-6">
                        <img src="${imageDataUrl}" alt="Preview" class="max-w-full h-64 object-contain mx-auto rounded-lg border">
                    </div>
                    
                    <form id="quickUploadForm" class="space-y-4">
                        <input type="hidden" name="monument_id" value="${monumentId}">
                        
                        <div>
                            <label for="photoTitle" class="block text-sm font-medium text-gray-700 mb-1">
                                Görselin Başlığı <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="photoTitle" name="title" value="${suggestedTitle}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="photoDate" class="block text-sm font-medium text-gray-700 mb-1">
                                Tarih <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="photoDate" name="date" value="${suggestedDate}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
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
                        
                        <div class="bg-gray-50 p-4 rounded-md">
                            <div class="flex items-center">
                            <img src="/by-sa.svg" class="h-6 text-gray-500 mr-2" />     
                            <svg class="w-5 h-5 text-gray-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-sm text-gray-600">
                                    Lisans: <strong>Creative Commons Atıf-BenzeriPaylaşım 4.0 Uluslararası</strong>
                                </span>
                            </div>
                        </div>
                        
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
                        
                        <div id="uploadError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-red-500 mt-0.5 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                <p id="uploadErrorMessage" class="flex-1"></p>
                            </div>
                        </div>
                        
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
        
        document.getElementById('quickUploadForm').onsubmit = function(e) {
            e.preventDefault();
            uploadPhoto();
        };
        
        document.getElementById('categoryInput').onkeypress = function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addCategory();
            }
        };
    }
    
    window.closeQuickUploadModal = function() {
        const modal = document.getElementById('quickUploadModal');
        if (modal) {
            modal.remove();
        }
        selectedFile = null;
    };
    
    window.addCategory = function() {
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
    };
    
    window.removeCategory = function(button) {
        button.parentElement.remove();
    };
    
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
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error('Sunucu beklenmeyen bir yanıt döndürdü. Lütfen giriş yapıp tekrar deneyin.');
            }
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                successMessage.textContent = result.message || 'Fotoğraf başarıyla yüklendi!';
                successDiv.classList.remove('hidden');
                
                if (result.data?.descriptionurl) {
                    window.open(result.data.descriptionurl, '_blank');
                }
                
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
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
});
</script>
@endpush

@push('styles')
<style>
.monument-marker {
    cursor: pointer;
}

.user-location {
    font-size: 24px;
    text-align: center;
    line-height: 30px;
}

/* Map control positioning - Move zoom to left bottom to avoid floating cards */
.leaflet-control-zoom {
    z-index: 1000 !important;
    left: 10px !important;
    right: auto !important;
    bottom: 20px !important;
    top: auto !important;
    position: absolute !important;
}

/* Force zoom control positioning with higher specificity */
.leaflet-container .leaflet-control-zoom {
    left: 10px !important;
    right: auto !important;
    bottom: 20px !important;
    top: auto !important;
    position: absolute !important;
}

.leaflet-control-attribution {
    z-index: 1000 !important;
    font-size: 11px !important;
    line-height: 1.4 !important;
    background: rgba(255, 255, 255, 0.8) !important;
    padding: 4px 8px !important;
    border-radius: 4px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2) !important;
}

.leaflet-control-attribution a {
    color: #0066cc !important;
    text-decoration: none !important;
}

.leaflet-control-attribution a:hover {
    text-decoration: underline !important;
}

/* Panel positioning */
#searchPanel {
    z-index: 2000 !important;
}

.leaflet-attribution-flag { display: none !important; }

/* Mobile search panel and info panel full screen overlay */
@media (max-width: 768px) {
    #searchPanel.mobile-open,
    #monumentInfo.mobile-open {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        max-height: none !important;
        border-radius: 0 !important;
        z-index: 3000 !important;
    }
    
    .floating-nav-cards {
        right: 10px !important;
        top: 10px !important;
    }
    
    /* Make search panel smaller on mobile by default */
    #searchPanel {
        width: calc(100vw - 2rem) !important;
        max-width: 320px !important;
    }
}

#monumentInfo {
    z-index: 2500 !important;
    position: fixed !important;
}

#mobileSearchToggle {
    z-index: 2999 !important;
    position: fixed !important;
}

/* Ensure floating nav cards stay on top */
.floating-nav-cards {
    z-index: 2400 !important;
    position: fixed !important;
}

@media (max-width: 768px) {
    #searchPanel {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100% !important;
        max-height: 100vh !important;
        z-index: 3000 !important;
        border-radius: 0 !important;
    }
}

.photo-overlay {
    background: rgba(0,0,0,0.55) !important;
    color: #fff !important;
    font-size: 0.72em !important;
    font-weight: 500 !important;
    border-radius: 0.35em !important;
    padding: 0.18em 0.6em !important;
    margin-bottom: 0 !important;
    margin-right: 0 !important;
    text-decoration: underline dotted #fff2;
    transition: background 0.2s;
    box-shadow: none !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.25);
    z-index: 10 !important;
    display: inline-block !important;
}
.photo-overlay:hover {
    background: rgba(0,0,0,0.75) !important;
    color: #ffe !important;
}
</style>
@endpush 