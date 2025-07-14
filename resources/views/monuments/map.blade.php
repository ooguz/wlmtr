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
                
                <!-- Photo Info -->
                <div id="photoInfo" class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-75 text-white text-xs p-2">
                    <div id="photoTitle" class="font-semibold"></div>
                    <div id="photoPhotographer" class="text-gray-300"></div>
                    <div id="photoLicense" class="text-gray-300"></div>
                </div>
            </div>
            
            <!-- Carousel Indicators -->
            <div id="carouselIndicators" class="flex justify-center mt-2 space-x-1"></div>
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
                    const marker = L.circleMarker([monument.coordinates.lat, monument.coordinates.lng], {
                        radius: 6,
                        fillColor: '#3B82F6',
                        color: '#1E40AF',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    });
                    
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
        const photoCarousel = document.getElementById('photoCarousel');
        const carouselTrack = document.getElementById('carouselTrack');
        const carouselIndicators = document.getElementById('carouselIndicators');
        
        title.textContent = monument.name;
        description.textContent = monument.description || 'Açıklama bulunmuyor.';
        province.textContent = monument.province;
        photoCount.textContent = `${monument.photo_count} fotoğraf`;
        detailsLink.href = `/monuments/${monument.id}`;
        wikidataLink.href = `https://www.wikidata.org/wiki/${monument.wikidata_id}`;
        
        // Handle photo carousel
        if (monument.photos && monument.photos.length > 0) {
            setupPhotoCarousel(monument.photos);
            photoCarousel.classList.remove('hidden');
        } else {
            photoCarousel.classList.add('hidden');
        }
        
        infoPanel.classList.remove('hidden');
    }
    
    // Setup photo carousel
    function setupPhotoCarousel(photos) {
        const carouselTrack = document.getElementById('carouselTrack');
        const carouselIndicators = document.getElementById('carouselIndicators');
        const photoInfo = document.getElementById('photoInfo');
        const photoTitle = document.getElementById('photoTitle');
        const photoPhotographer = document.getElementById('photoPhotographer');
        const photoLicense = document.getElementById('photoLicense');
        
        // Clear previous content
        carouselTrack.innerHTML = '';
        carouselIndicators.innerHTML = '';
        
        // Add photos to carousel
        photos.forEach((photo, index) => {
            const slide = document.createElement('div');
            slide.className = 'flex-shrink-0 w-full';
            slide.innerHTML = `
                <img src="${photo.display_url}" 
                     alt="${photo.title || 'Monument photo'}" 
                     class="w-full h-48 object-cover cursor-pointer"
                     onclick="openPhotoModal('${photo.full_resolution_url}', '${photo.commons_url}')">
            `;
            carouselTrack.appendChild(slide);
            
            // Add indicator
            const indicator = document.createElement('button');
            indicator.className = `w-2 h-2 rounded-full ${index === 0 ? 'bg-blue-600' : 'bg-gray-300'}`;
            indicator.onclick = () => goToSlide(index);
            carouselIndicators.appendChild(indicator);
        });
        
        // Set initial photo info
        if (photos.length > 0) {
            updatePhotoInfo(photos[0]);
        }
        
        // Store photos for navigation
        carouselTrack.photos = photos;
        carouselTrack.currentIndex = 0;
    }
    
    // Update photo info
    function updatePhotoInfo(photo) {
        const photoTitle = document.getElementById('photoTitle');
        const photoPhotographer = document.getElementById('photoPhotographer');
        const photoLicense = document.getElementById('photoLicense');
        
        photoTitle.textContent = photo.title || 'Untitled';
        photoPhotographer.textContent = photo.photographer ? `by ${photo.photographer}` : '';
        photoLicense.textContent = photo.license || '';
    }
    
    // Navigate to specific slide
    function goToSlide(index) {
        const carouselTrack = document.getElementById('carouselTrack');
        const indicators = document.querySelectorAll('#carouselIndicators button');
        
        carouselTrack.currentIndex = index;
        carouselTrack.style.transform = `translateX(-${index * 100}%)`;
        
        // Update indicators
        indicators.forEach((indicator, i) => {
            indicator.className = `w-2 h-2 rounded-full ${i === index ? 'bg-blue-600' : 'bg-gray-300'}`;
        });
        
        // Update photo info
        if (carouselTrack.photos && carouselTrack.photos[index]) {
            updatePhotoInfo(carouselTrack.photos[index]);
        }
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
    function openPhotoModal(imageUrl, commonsUrl) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="relative max-w-4xl max-h-full p-4">
                <button onclick="this.parentElement.parentElement.remove()" class="absolute top-2 right-2 text-white text-2xl">&times;</button>
                <img src="${imageUrl}" alt="Full size photo" class="max-w-full max-h-full object-contain">
                <div class="mt-4 text-center">
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