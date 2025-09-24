@extends('layouts.map')

@section('title', 'Anıt Haritası')

@section('content')
<!-- Loading Spinner -->
<div id="loadingSpinner" class="fixed inset-0 bg-white bg-opacity-90 flex items-center justify-center" style="z-index: 99999;">
    <div class="text-center">
        <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600 mb-4"></div>
        <p class="text-lg font-medium text-gray-700">Anıtlar yükleniyor...</p>
        <p class="text-sm text-gray-500 mt-2">Lütfen bekleyin</p>
    </div>
</div>

<div class="fixed inset-0">
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
            
            <!-- Category Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                <select id="categoryFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tüm kategoriler</option>
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

    <!-- Floating Nav Cards -->
    <div class="floating-nav-cards absolute top-4 right-4 z-30 flex flex-col gap-3">
        <a href="{{ route('monuments.list') }}" class="bg-white/90 backdrop-blur rounded-xl shadow-md px-4 py-3 hover:bg-white">
            <div class="flex items-center gap-2">
                <span>📃</span>
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
                    <div class="text-sm font-semibold">Profil</div>
                    <div class="text-xs text-gray-500">Hesap ayarları</div>
                </div>
            </div>
        </a>
        @else
        <a href="{{ route('auth.login') }}" class="bg-white/90 backdrop-blur rounded-xl shadow-md px-4 py-3 hover:bg-white">
            <div class="flex items-center gap-2">
                <span>↪️</span>
                <div>
                    <div class="text-sm font-semibold">Giriş Yap</div>
                    <div class="text-xs text-gray-500">Wikimedia ile</div>
                </div>
            </div>
        </a>
        @endauth
    </div>
    
    <!-- Monument Info Panel -->
    <div id="monumentInfo" class="absolute top-4 left-4 z-20 bg-white rounded-lg shadow-lg p-4 w-80 max-h-[calc(100vh-8rem)] overflow-y-auto hidden">
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
        
        <!-- Upload Wizard Link -->
        <div class="mt-3">
            <a id="monumentUploadWizardLink" 
               href="#" 
               target="_blank" 
               class="w-full bg-green-600 text-white text-center px-3 py-2 rounded-md hover:bg-green-700 text-sm font-medium flex items-center justify-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
                Fotoğraf Yükleme Sihirbazı
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    const map = L.map('map').setView([39.9334, 32.8597], 6); // Turkey center
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors | © <a href="https://leafletjs.com/" target="_blank">Leaflet</a> | <a href="https://commons.wikimedia.org/wiki/Category:Wiki_Loves_Monuments_Turkey" target="_blank">Wikimedia Commons</a> | Made with ❤️ by <a href="https://github.com/m3rcury" target="_blank">m3rcury</a>'
    }).addTo(map);
    
    // Clustering
    const markerCluster = L.markerClusterGroup({
        showCoverageOnHover: false,
        maxClusterRadius: 45
    });
    map.addLayer(markerCluster);

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
    
    if (mobileSearchToggle) {
        mobileSearchToggle.addEventListener('click', function() {
            searchPanel.classList.toggle('hidden');
        });
    }
    
    if (closeSearchPanel) {
        closeSearchPanel.addEventListener('click', function() {
            searchPanel.classList.add('hidden');
        });
    }
    
    // Load monuments after first tile loads
    let firstTileLoaded = false;
    
    map.on('tileload', function() {
        if (!firstTileLoaded) {
            firstTileLoaded = true;
            console.log('First tile loaded, will call loadMonuments in 1 second');
            // Wait a bit more for all initial tiles to load
            setTimeout(loadMonuments, 1000);
        }
    });
    
    // Fallback: load monuments after 5 seconds even if no tiles load
    setTimeout(function() {
        if (!firstTileLoaded) {
            loadMonuments();
        }
    }, 5000);
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const provinceFilter = document.getElementById('provinceFilter');
    const categoryFilter = document.getElementById('categoryFilter');
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
        const category = categoryFilter.value;
        const hasPhotos = photoFilter.value;
        
        markers.forEach(marker => {
            let show = true;
            
            if (searchTerm && !marker.monument.name.toLowerCase().includes(searchTerm.toLowerCase())) {
                show = false;
            }
            
            if (province && marker.monument.province !== province) {
                show = false;
            }
            
            if (category && !marker.monument.categories?.some(cat => cat.id == category)) {
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
    categoryFilter.addEventListener('change', applyFilters);
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
        // Get loading spinner reference
        const loadingSpinner = document.getElementById('loadingSpinner');
        console.log('loadMonuments started, spinner should be visible');
        
        fetch('/api/monuments/map-markers')
            .then(response => {
                if (!response.ok) {
                    throw new Error('API response not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                
                // Clear existing markers
                markerCluster.clearLayers();
                markers = [];
                
                // Add new markers
                (data || []).forEach(monument => {
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
                    markerCluster.addLayer(marker);
                    
                    // Add click event
                    marker.on('click', function() {
                        showMonumentInfo(monument);
                    });
                });
                
                // Load provinces for filter
                loadProvinces();
                
                // Hide loading spinner after markers are added
                console.log('Hiding spinner - markers have been added');
                loadingSpinner.style.display = 'none';
            })
            .catch(error => {
                console.warn('Monuments unavailable');
                // Hide loading spinner even on error
                loadingSpinner.style.display = 'none';
            });
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
                    option.value = category.id;
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
        
        // Build upload wizard URL
        const uploadWizardUrl = buildUploadWizardUrl(monument);
        uploadWizardLink.href = uploadWizardUrl;
        
        // Handle photo preview/carousel
        if (monument.photos && monument.photos.length > 0) {
            setupPhotoCarousel(monument.photos);
            photoCarousel.classList.remove('hidden');
        } else if (monument.featured_photo) {
            setupPhotoCarousel([
                {
                    full_resolution_url: monument.featured_photo,
                    display_url: monument.featured_photo,
                    title: monument.name,
                    photographer: null,
                    license: null,
                    commons_url: null,
                },
            ]);
            photoCarousel.classList.remove('hidden');
        } else {
            photoCarousel.classList.add('hidden');
        }
        
        infoPanel.classList.remove('hidden');
        
        // Hide search panel when monument info is shown to avoid overlap
        searchPanel.classList.add('hidden');

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
            let commonsLink = photo.commons_url ? `<a href="${photo.commons_url}" target="_blank" class="photo-overlay" style="position:absolute;bottom:0.4em;right:0.4em;pointer-events:auto;">${overlayText}</a>` : '';
            const slide = document.createElement('div');
            slide.className = 'flex-shrink-0 w-full relative';
            slide.innerHTML = `
                <img src="${photo.full_resolution_url}" 
                     alt="${photo.title || 'Monument photo'}" 
                     class="w-full h-48 object-cover cursor-pointer">
                ${commonsLink}
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
        document.getElementById('monumentInfo').classList.add('hidden');
        // Show search panel again when monument info is closed
        if (window.innerWidth >= 768) {
            searchPanel.classList.remove('hidden');
        }
    });
    
    // Build upload wizard URL for monument
    function buildUploadWizardUrl(monument) {
        const baseUrl = 'https://commons.wikimedia.org/wiki/Special:UploadWizard';
        
        // Build description with monument name and WLM template
        let description = monument.name;
        if (monument.wikidata_id) {
            description += '\{\{Load via app WLM.tr|year=' + new Date().getFullYear() + '|source=wizard\}\}';
        }
        
        const params = {
            description: description,
            descriptionlang: 'tr',
            campaign: 'wlm-tr',
        };
        
        // Add categories based on location hierarchy
        if (monument.location_hierarchy_tr) {
            params.categories = monument.location_hierarchy_tr;
        } else if (monument.province) {
            params.categories = monument.province;
        }
        
        // Add monument ID if available
        if (monument.wikidata_id) {
            params.id = monument.wikidata_id;
        }
        
        return baseUrl + '?' + new URLSearchParams(params).toString();
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

/* Ensure panels stay on top during map interactions */
.leaflet-control-zoom {
    z-index: 1000 !important;
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

/* Prevent panels from being hidden during map zoom */
#searchPanel {
    z-index: 2000 !important;
    position: fixed !important;
}

#monumentInfo {
    z-index: 1500 !important;
    position: fixed !important;
}

/* Ensure floating nav cards stay on top */
.floating-nav-cards {
    z-index: 3000 !important;
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