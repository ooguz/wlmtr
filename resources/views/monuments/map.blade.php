@extends('layouts.app')

@section('title', 'Anıt Haritası')

@section('content')
<div class="relative h-[calc(100vh-4rem)]">
    <!-- Map Container -->
    <div id="map" class="w-full h-full"></div>
    
    <!-- Search Panel -->
    <div id="searchPanel" class="absolute top-4 left-4 z-20 bg-white rounded-lg shadow-lg p-4 w-80 max-h-[calc(100vh-8rem)] overflow-y-auto">
        <h3 class="text-lg font-semibold mb-4">Anıt Ara</h3>
        
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
            
            <!-- Photo Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fotoğraf Durumu</label>
                <select id="photoFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tümü</option>
                    <option value="1">Fotoğraflı</option>
                    <option value="0">Fotoğrafsız</option>
                </select>
            </div>
            
            <!-- Distance Filter -->
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
        
        <!-- Location Button -->
        <button id="locationBtn" 
                class="w-full mt-4 bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            📍 Konumumu Kullan
        </button>
        
        <!-- Close Button for Mobile -->
        <button id="closeSearchPanel" 
                class="md:hidden w-full mt-2 bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
            Kapat
        </button>
    </div>
    
    <!-- Monument Info Panel -->
    <div id="monumentInfo" class="absolute top-4 right-4 z-20 bg-white rounded-lg shadow-lg p-4 w-80 max-h-[calc(100vh-8rem)] overflow-y-auto hidden">
        <div class="flex justify-between items-start mb-3">
            <h3 id="monumentTitle" class="text-lg font-semibold"></h3>
            <button id="closeInfo" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div id="monumentDescription" class="text-sm text-gray-600 mb-3"></div>
        
        <div class="flex items-center justify-between text-sm text-gray-500 mb-3">
            <span id="monumentProvince"></span>
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
    </div>
    
    <!-- Mobile Search Toggle Button -->
    <button id="mobileSearchToggle" 
            class="md:hidden absolute top-4 left-4 z-30 bg-blue-600 text-white p-3 rounded-full shadow-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
    </button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    const map = L.map('map').setView([39.9334, 32.8597], 6); // Turkey center
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Store markers
    let markers = [];
    let currentInfoWindow = null;
    
    // Mobile search panel toggle
    const mobileSearchToggle = document.getElementById('mobileSearchToggle');
    const searchPanel = document.getElementById('searchPanel');
    const closeSearchPanel = document.getElementById('closeSearchPanel');
    
    // Hide search panel on mobile by default
    if (window.innerWidth < 768) {
        searchPanel.classList.add('hidden');
    }
    
    mobileSearchToggle.addEventListener('click', function() {
        searchPanel.classList.toggle('hidden');
    });
    
    closeSearchPanel.addEventListener('click', function() {
        searchPanel.classList.add('hidden');
    });
    
    // Load monuments
    loadMonuments();
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const provinceFilter = document.getElementById('provinceFilter');
    const photoFilter = document.getElementById('photoFilter');
    const distanceFilter = document.getElementById('distanceFilter');
    const distanceValue = document.getElementById('distanceValue');
    const locationBtn = document.getElementById('locationBtn');
    
    // Update distance display
    distanceFilter.addEventListener('input', function() {
        distanceValue.textContent = this.value + 'km';
    });
    
    // Search and filter
    function applyFilters() {
        const searchTerm = searchInput.value;
        const province = provinceFilter.value;
        const hasPhotos = photoFilter.value;
        
        markers.forEach(marker => {
            let show = true;
            
            if (searchTerm && !marker.monument.name.toLowerCase().includes(searchTerm.toLowerCase())) {
                show = false;
            }
            
            if (province && marker.monument.province !== province) {
                show = false;
            }
            
            if (hasPhotos !== '' && marker.monument.has_photos !== (hasPhotos === '1')) {
                show = false;
            }
            
            if (show) {
                marker.addTo(map);
            } else {
                marker.remove();
            }
        });
    }
    
    searchInput.addEventListener('input', applyFilters);
    provinceFilter.addEventListener('change', applyFilters);
    photoFilter.addEventListener('change', applyFilters);
    
    // Location functionality
    locationBtn.addEventListener('click', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                // Center map on user location
                map.setView([lat, lng], 12);
                
                // Add user location marker
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
        } else {
            alert('Tarayıcınız konum özelliğini desteklemiyor.');
        }
    });
    
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
    
    // Load monuments from API
    function loadMonuments() {
        fetch('/api/monuments/map-markers')
            .then(response => response.json())
            .then(data => {
                // Clear existing markers
                markers.forEach(marker => marker.remove());
                markers = [];
                
                // Add new markers
                data.forEach(monument => {
                    const marker = L.marker([monument.coordinates.lat, monument.coordinates.lng]);
                    
                    marker.monument = monument;
                    markers.push(marker);
                    marker.addTo(map);
                    
                    // Add click event
                    marker.on('click', function() {
                        showMonumentInfo(monument);
                    });
                });
                
                // Load provinces for filter
                loadProvinces();
            })
            .catch(error => {
                console.error('Error loading monuments:', error);
            });
    }
    
    // Load provinces for filter
    function loadProvinces() {
        fetch('/api/monuments/filters')
            .then(response => response.json())
            .then(data => {
                const provinceFilter = document.getElementById('provinceFilter');
                data.provinces.forEach(province => {
                    const option = document.createElement('option');
                    option.value = province;
                    option.textContent = province;
                    provinceFilter.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading provinces:', error);
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
        
        title.textContent = monument.name;
        description.textContent = monument.description || 'Açıklama bulunmuyor.';
        province.textContent = monument.province;
        photoCount.textContent = `${monument.photo_count} fotoğraf`;
        detailsLink.href = `/monuments/${monument.id}`;
        wikidataLink.href = `https://www.wikidata.org/wiki/${monument.wikidata_id}`;
        
        infoPanel.classList.remove('hidden');
    }
    
    // Close monument info panel
    document.getElementById('closeInfo').addEventListener('click', function() {
        document.getElementById('monumentInfo').classList.add('hidden');
    });
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

/* Ensure panels stay on top during map interactions */
.leaflet-control-zoom {
    z-index: 1000 !important;
}

.leaflet-control-attribution {
    z-index: 1000 !important;
}

/* Prevent panels from being hidden during map zoom */
#searchPanel, #monumentInfo {
    z-index: 2000 !important;
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
</style>
@endpush 