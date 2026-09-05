@extends('layouts.app')
@section('title', '🗺️ แผนที่ร้านค้า')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">🗺️ แผนที่ร้านค้า</h2>
            <p class="text-muted mb-0">ดูร้านค้าทั้งหมด + นำทาง + ระยะทาง</p>
        </div>
        <div>
            <a href="{{ route('agent.shops.index') }}" class="btn btn-outline-secondary">← กลับ</a>
        </div>
    </div>

    <div class="row">
        {{-- แผนที่ --}}
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body p-0">
                    <x-map id="agentMap" height="600px" :centerLat="13.7563" :centerLng="100.5018" :zoom="11" />
                </div>
            </div>
        </div>

        {{-- รายการร้านค้า --}}
        <div class="col-lg-4">
            {{-- ข้อมูลผู้ใช้ --}}
            <div class="card mb-3 border-success">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="fs-3 me-3">📍</span>
                        <div>
                            <strong>ตำแหน่งของคุณ</strong>
                            <div id="user-location" class="text-muted small">กำลังรับพิกัด...</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ตัวกรอง --}}
            <div class="card mb-3">
                <div class="card-body">
                    <label class="form-label fw-bold">🔍 กรองตามระยะทาง</label>
                    <select id="filter-radius" class="form-select" onchange="filterStores()">
                        <option value="999">ทั้งหมด</option>
                        <option value="5">ใกล้กว่า 5 กม.</option>
                        <option value="10">ใกล้กว่า 10 กม.</option>
                        <option value="20">ใกล้กว่า 20 กม.</option>
                        <option value="50">ใกล้กว่า 50 กม.</option>
                    </select>
                </div>
            </div>

            {{-- รายการร้านค้า --}}
            <div class="card">
                <div class="card-header">
                    <strong>🏪 ร้านค้า <span id="store-count" class="badge bg-primary">{{ $shops->count() }}</span></strong>
                </div>
                <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;">
                    <div id="store-list">
                        @forelse($shops as $shop)
                        <div class="store-item border-bottom p-3" data-id="{{ $shop->id }}"
                             data-lat="{{ $shop->lat }}" data-lng="{{ $shop->lng }}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <strong class="store-name">{{ $shop->name }}</strong>
                                    @if($shop->shop_type)
                                        <span class="badge bg-secondary ms-1">{{ $shop->shop_type }}</span>
                                    @endif
                                    @if($shop->address)
                                        <div class="small text-muted mt-1">📍 {{ Str::limit($shop->address, 60) }}</div>
                                    @endif
                                    <div class="small text-muted">
                                        <span class="store-distance">📏 คำนวณ...</span>
                                        @if($shop->phone)
                                            · 📞 {{ $shop->phone }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary flex-grow-1"
                                        onclick="showRoute({{ $shop->lat }}, {{ $shop->lng }}, '{{ addslashes($shop->name) }}')">
                                    🛣️ ดูเส้นทาง
                                </button>
                                <button class="btn btn-sm btn-success"
                                        onclick="navigateTo({{ $shop->lat }}, {{ $shop->lng }}, '{{ addslashes($shop->name) }}')">
                                    🧭 นำทาง
                                </button>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-5 text-muted">
                            <div class="fs-1">🏪</div>
                            <p>ยังไม่มีร้านค้าที่มีพิกัด GPS</p>
                            <a href="{{ route('agent.shops.index') }}" class="btn btn-sm btn-primary">เพิ่มข้อมูลร้านค้า</a>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Route Info Panel --}}
<div id="route-info" class="position-fixed bottom-0 start-50 translate-middle-x mb-3"
     style="display:none; z-index:1000; max-width:500px; width:90%;">
    <div class="card shadow-lg border-primary">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong id="route-destination"></strong>
                    <div class="small text-muted">
                        <span id="route-distance"></span> · <span id="route-time"></span>
                    </div>
                </div>
                <div>
                    <button class="btn btn-sm btn-success" id="route-nav-btn">🧭 นำทาง</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearRoute()">✖</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const stores = @json($shops);

// Wait for map to initialize
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initMap, 500);
});

function initMap() {
    const mapInstance = window['agentMap_instance'];
    if (!mapInstance) {
        setTimeout(initMap, 500);
        return;
    }

    // Add store markers
    stores.forEach(function(shop) {
        const photoHtml = shop.photos && shop.photos.length > 0
            ? '<img src="/storage/' + shop.photos[0] + '" width="100%" style="max-height:120px;object-fit:cover;border-radius:8px;margin-bottom:8px;">'
            : '';

        const popup = `
            <div style="min-width:200px;">
                ${photoHtml}
                <strong>${shop.name}</strong>
                ${shop.shop_type ? '<span class="badge bg-secondary">' + shop.shop_type + '</span>' : ''}
                ${shop.address ? '<div class="small text-muted mt-1">📍 ' + shop.address + '</div>' : ''}
                ${shop.phone ? '<div class="small">📞 <a href="tel:' + shop.phone + '">' + shop.phone + '</a></div>' : ''}
                ${shop.opening_hours ? '<div class="small">🕐 ' + shop.opening_hours + '</div>' : ''}
                <div class="mt-2">
                    <button class="btn btn-sm btn-success w-100" onclick="navigateTo(${shop.lat}, ${shop.lng}, '${shop.name.replace(/'/g, "\\'")}')">
                        🧭 นำทาง
                    </button>
                </div>
            </div>
        `;

        window['agentMap_addMarker'](shop.lat, shop.lng, popup, 'store');
    });

    // Fit bounds to show all markers
    if (stores.length > 0) {
        window['agentMap_fitBounds']();
    }

    // Update distances when user location is available
    updateDistances();
}

function updateDistances() {
    const userLat = window['agentMap_userLat'];
    const userLng = window['agentMap_userLng'];

    if (!userLat || !userLng) {
        setTimeout(updateDistances, 1000);
        return;
    }

    document.getElementById('user-location').innerHTML =
        '📍 ' + userLat.toFixed(6) + ', ' + userLng.toFixed(6);

    // Calculate and display distances
    document.querySelectorAll('.store-item').forEach(function(item) {
        const lat = parseFloat(item.dataset.lat);
        const lng = parseFloat(item.dataset.lng);
        const dist = window['agentMap_distance'](userLat, userLng, lat, lng);

        const distEl = item.querySelector('.store-distance');
        if (dist < 1) {
            distEl.innerHTML = '📏 ' + Math.round(dist * 1000) + ' ม.';
            distEl.className = 'store-distance text-success fw-bold';
        } else if (dist < 10) {
            distEl.innerHTML = '📏 ' + dist.toFixed(1) + ' กม.';
            distEl.className = 'store-distance text-primary';
        } else {
            distEl.innerHTML = '📏 ' + dist.toFixed(1) + ' กม.';
            distEl.className = 'store-distance text-muted';
        }

        item.dataset.distance = dist.toFixed(2);
    });

    // Sort by distance
    const list = document.getElementById('store-list');
    const items = Array.from(list.querySelectorAll('.store-item'));
    items.sort(function(a, b) {
        return parseFloat(a.dataset.distance) - parseFloat(b.dataset.distance);
    });
    items.forEach(function(item) { list.appendChild(item); });
}

function showRoute(lat, lng, name) {
    const userLat = window['agentMap_userLat'];
    const userLng = window['agentMap_userLng'];

    if (!userLat || !userLng) {
        alert('กรุณารอ GPS รับตำแหน่งของคุณก่อน');
        return;
    }

    window['agentMap_showRoute'](userLat, userLng, lat, lng);

    // Show route info
    const dist = window['agentMap_distance'](userLat, userLng, lat, lng);
    const timeMin = Math.round(dist / 40 * 60); // assume 40 km/h average

    document.getElementById('route-destination').textContent = '🛣️ ' + name;
    document.getElementById('route-distance').textContent = '📏 ' + dist.toFixed(1) + ' กม.';
    document.getElementById('route-time').textContent = '⏱️ ประมาณ ' + timeMin + ' นาที';
    document.getElementById('route-info').style.display = 'block';

    const navBtn = document.getElementById('route-nav-btn');
    navBtn.onclick = function() { navigateTo(lat, lng, name); };
}

function clearRoute() {
    window['agentMap_clearRoute']();
    document.getElementById('route-info').style.display = 'none';
}

function navigateTo(lat, lng, name) {
    window['agentMap_navigateTo'](lat, lng, name);
}

function filterStores() {
    const radius = parseFloat(document.getElementById('filter-radius').value);
    let visible = 0;

    document.querySelectorAll('.store-item').forEach(function(item) {
        const dist = parseFloat(item.dataset.distance || 999);
        if (dist <= radius) {
            item.style.display = '';
            visible++;
        } else {
            item.style.display = 'none';
        }
    });

    document.getElementById('store-count').textContent = visible;
}
</script>

<style>
.store-item {
    transition: background-color 0.2s;
    cursor: pointer;
}
.store-item:hover {
    background-color: #f0f9ff;
}
</style>
@endsection
