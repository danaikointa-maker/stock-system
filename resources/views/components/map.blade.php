@props([
    'id' => 'map',
    'height' => '500px',
    'centerLat' => 13.7563,
    'centerLng' => 100.5018,
    'zoom' => 12,
    'editable' => false,
    'showRoute' => false,
    'showUserLocation' => true,
    'showNearby' => false,
])

{{-- Leaflet CSS --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
{{-- Leaflet Routing Machine CSS --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css"/>

<div id="{{ $id }}" style="height: {{ $height }}; width: 100%; border-radius: 12px; border: 1px solid #ddd; z-index: 1;"></div>

{{-- Leaflet JS --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
{{-- Leaflet Routing Machine JS --}}
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

<script>
(function() {
    const mapId = '{{ $id }}';
    const editable = {{ $editable ? 'true' : 'false' }};
    const showRoute = {{ $showRoute ? 'true' : 'false' }};
    const showUserLocation = {{ $showUserLocation ? 'true' : 'false' }};

    // Initialize map
    const map = L.map(mapId).setView([{{ $centerLat }}, {{ $centerLng }}], {{ $zoom }});

    // Tile layer (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // Store map instance globally
    window[mapId + '_instance'] = map;
    window[mapId + '_markers'] = [];
    window[mapId + '_routeControl'] = null;

    // Custom icons
    const icons = {
        store: L.divIcon({
            html: '<div style="background:#2563eb;width:32px;height:32px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;"><span style="transform:rotate(45deg);font-size:14px;">🏪</span></div>',
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            className: ''
        }),
        user: L.divIcon({
            html: '<div style="background:#10b981;width:20px;height:20px;border-radius:50%;border:3px solid #fff;box-shadow:0 0 0 3px rgba(16,185,129,0.3),0 2px 6px rgba(0,0,0,0.3);"></div>',
            iconSize: [20, 20],
            iconAnchor: [10, 10],
            className: ''
        }),
        destination: L.divIcon({
            html: '<div style="background:#ef4444;width:32px;height:32px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;"><span style="transform:rotate(45deg);font-size:14px;">📍</span></div>',
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            className: ''
        }),
        warehouse: L.divIcon({
            html: '<div style="background:#f59e0b;width:32px;height:32px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;"><span style="transform:rotate(45deg);font-size:14px;">🏭</span></div>',
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            className: ''
        })
    };

    // Add user location
    let userMarker = null;
    let userLat = null, userLng = null;

    if (showUserLocation && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            userMarker = L.marker([userLat, userLng], { icon: icons.user })
                .addTo(map)
                .bindPopup('<strong>📍 ตำแหน่งของคุณ</strong>');
            map.setView([userLat, userLng], 13);
            window[mapId + '_userLat'] = userLat;
            window[mapId + '_userLng'] = userLng;
        }, function(err) {
            console.log('GPS error:', err.message);
        }, { enableHighAccuracy: true, timeout: 10000 });
    }

    // Editable mode — click to place marker
    if (editable) {
        let editMarker = null;
        map.on('click', function(e) {
            if (editMarker) map.removeLayer(editMarker);
            editMarker = L.marker(e.latlng, { icon: icons.destination, draggable: true }).addTo(map);
            editMarker.on('dragend', function(ev) {
                const pos = ev.target.getLatLng();
                updateEditFields(pos.lat, pos.lng);
            });
            updateEditFields(e.latlng.lat, e.latlng.lng);
        });
    }

    // Helper functions
    window[mapId + '_addMarker'] = function(lat, lng, popup, iconType) {
        const icon = icons[iconType] || icons.store;
        const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
        if (popup) marker.bindPopup(popup);
        window[mapId + '_markers'].push(marker);
        return marker;
    };

    window[mapId + '_fitBounds'] = function() {
        const markers = window[mapId + '_markers'];
        if (markers.length > 0) {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    };

    window[mapId + '_showRoute'] = function(fromLat, fromLng, toLat, toLng) {
        // Remove existing route
        if (window[mapId + '_routeControl']) {
            map.removeControl(window[mapId + '_routeControl']);
        }

        window[mapId + '_routeControl'] = L.Routing.control({
            waypoints: [
                L.latLng(fromLat, fromLng),
                L.latLng(toLat, toLng)
            ],
            router: L.Routing.osrmv1({
                serviceUrl: 'https://router.project-osrm.org/route/v1'
            }),
            lineOptions: {
                styles: [{ color: '#2563eb', weight: 5, opacity: 0.8 }]
            },
            addWaypoints: false,
            draggableWaypoints: false,
            fitSelectedRoutes: true,
            showAlternatives: true,
            createMarker: function() { return null; }
        }).addTo(map);
    };

    window[mapId + '_clearRoute'] = function() {
        if (window[mapId + '_routeControl']) {
            map.removeControl(window[mapId + '_routeControl']);
            window[mapId + '_routeControl'] = null;
        }
    };

    window[mapId + '_navigateTo'] = function(lat, lng, name) {
        // Open Google Maps navigation
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isAndroid = /Android/.test(navigator.userAgent);

        if (isIOS) {
            window.open('maps://maps.apple.com/?daddr=' + lat + ',' + lng + '&dirflg=d', '_blank');
        } else if (isAndroid) {
            window.open('google.navigation:q=' + lat + ',' + lng, '_blank');
        } else {
            window.open('https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng + '&travelmode=driving', '_blank');
        }
    };

    function updateEditFields(lat, lng) {
        const latInput = document.querySelector('input[name="lat"]');
        const lngInput = document.querySelector('input[name="lng"]');
        if (latInput) latInput.value = lat.toFixed(8);
        if (lngInput) lngInput.value = lng.toFixed(8);
    }

    // Distance calculation
    window[mapId + '_distance'] = function(lat1, lng1, lat2, lng2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLng/2) * Math.sin(dLng/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    };
})();
</script>
