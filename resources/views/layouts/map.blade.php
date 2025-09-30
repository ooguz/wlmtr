<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Wiki Loves Monuments Turkey') }} - @yield('title', 'Harita')</title>

    <!-- App assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

      <!-- Favicon -->
      <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">

    <!-- OpenGraph and Twitter tags --> 

    <!-- OpenGraph tags -->
    <meta property="og:title" content="{{ config('app.name', 'Viki Anıtları Seviyor Türkiye') }} - @yield('title', 'Anıtlar')" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:image" content="{{ asset('wlm-logo.svg') }}" />
    <meta property="og:description" content="Viki Anıtları Seviyor Türkiye - Türkiye'deki kültürel mirası fotoğraflayın ve keşfedin" />

    <!-- Twitter Card tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ config('app.name', 'Viki Anıtları Seviyor Türkiye') }} - @yield('title', 'Anıtlar')" />
    <meta name="twitter:description" content="Türkiye'deki kültürel mirası fotoğraflayın ve keşfedin" />
    <meta name="twitter:image" content="{{ asset('wlm-logo.svg') }}" />
    <meta name="twitter:creator" content="@WikimediaTurkey" />

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- MarkerCluster CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

    @stack('styles')
</head>
<body class="font-sans antialiased">
    @yield('content')

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- MarkerCluster JS -->
    <script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    @stack('scripts')
</body>
</html>

