@extends('layouts.app')
@section('title', '🗺️ ค้นหาร้านค้าใกล้ฉัน')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">🗺️ ค้นหาร้านค้าใกล้ฉัน</h2>
            <p class="text-muted mb-0">ดูร้านค้าในแผนที่ + นำทาง + ระยะทาง</p>
        </div>
    </div>

    {{-- ค้นหา + ตัวกรอง --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">📍 ตำแหน่งของคุณ</label>
                    <button class="btn btn-primary w-100" onclick="getMyLocation()">
                        📍 ใช้ตำแหน่งปัจจุบัน
                    </button>
                </div>
                <div class="col-md-2">
                    <label class="form-label">ระยะทาง</label>
                    <select id="filter-radius" class="form-select" onchange="filterStores()">
                        <option value="5">5 กม.</option>
                        <option value="10" selected>10 กม.</option>
                        <option value="20">20 กม.</option>
                        <option value="50">50 กม.</option>
                        <option value="100">100 กม.</option>
                        <option value="999">ทั้งหมด</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">ประเภทร้าน</label>
                    <select id="filter-type" class="form-select" onchange="filterStores()">
                        <option value="">ทั้งหมด</option>
                        <option value="ร้านสะดวกซื้อ">ร้านสะดวกซื้อ</option>
                        <option value="ร้านขายของชำ">ร้านขายของชำ</option>
                        <option value="ซูเปอร์มาร์เก็ต">ซูเปอร์มาร์เก็ต</option>
                        <option value="ร้านขายส่ง">ร้านขายส่ง</option>
                        <option value="ตลาดนัด">ตลาดนัด</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">ค้นหาชื่อ</label>
                    <input type="text" id="search-name" class="form-control" placeholder="ชื่อร้าน..."
                           oninput="filterStores()">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                        🔄 รีเซ็ต
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- แผนที่ --}}
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body p-0">
                    <x-map id="publicMap" height="550px" :centerLat="13.7563" :centerLng="100.5018" :zoom="12" />
                </div>
            </div>
        </div>

        {{-- รายการร้านค้า --}}
        <div class="col-lg-4">
            {{-- สถานะ --}}
            <div class="card mb-3 border-primary">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong id="result-count">{{ $shops->count() }}</strong> ร้านค้า
                            <span id="result-radius" class="text-muted small"></span>
                        </div>
                        <div id="user-status" class="small text-muted">
                            ⏳ รอรับพิกัด...
                        </div>
                    </div>
                </div>
            </div>

            {{-- รายการ --}}
            <div class="card">
                <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                    <div id="store-list">
                        @forelse($shops as $shop)
                        <div class="store-item border-bottom p-3"
                             data-id="{{ $shop->id }}"
                             data-lat="{{ $shop->lat }}"
                             data-lng="{{ $shop->lng }}"
                             data-name="{{ $shop->name }}"
                             data-type="{{ $shop->shop_type }}"
                             onclick="focusStore({{ $shop->lat }}, {{ $shop->lng }}, '{{ addslashes($shop->name) }}')">
                            <div class="d-flex">
                                {{-- รูป --}}
                                <div class="me-3">
                                    @php
                                        $img = $shop->map_cover_photo
                                            ?? ($shop->photos && count($shop->photos) > 0 ? $shop->photos[0] : null);
                                    @endphp
                                    @if($img)
                                        <img src="{{ asset('storage/' . $img) }}" class="rounded"
                                             width="70" height="70" style="object-fit:cover;" alt="{{ $shop->name }}">
                                    @else
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                             style="width:70px;height:70px;">
                                            <span class="fs-2">🏪</span>
                                        </div>
                                    @endif
                                </div>
                                {{-- ข้อมูล --}}
                                <div class="flex-grow-1">
                                    <strong>{{ $shop->name }}</strong>
                                    @if($shop->shop_type)
                                        <span class="badge bg-secondary ms-1">{{ $shop->shop_type }}</span>
                                    @endif
                                    @if($shop->map_description)
                                        <div class="small text-muted">{{ $shop->map_description }}</div>
                                    @elseif($shop->address)
                                        <div class="small text-muted">{{ Str::limit($shop->address, 50) }}</div>
                                    @endif
                                    <div class="small mt-1">
                                        <span class="store-distance text-muted">📏 คำนวณ...</span>
                                        @if($shop->opening_hours)
                                            · 🕐 {{ $shop->opening_hours }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            {{-- ปุ่ม --}}
                            <div class="mt-2 d-flex gap-1">
                                @if($shop->phone)
                                <a href="tel:{{ $shop->phone }}" class="btn btn-sm btn-outline-primary">
                                    📞 โทร
                                </a>
                                @endif
                                <button class="btn btn-sm btn-outline-info flex-grow-1"
                                        onclick="event.stopPropagation(); showRoute({{ $shop->lat }}, {{ $shop->lng }}, '{{ addslashes($shop->name) }}')">
                                    🛣️ ดูเส้นทาง
                                </button>
                                <button class="btn btn-sm btn-success"
                                        onclick="event.stopPropagation(); navigateTo({{ $shop->lat }}, {{ $shop->lng }}, '{{ addslashes($shop->name) }}')">
                                    🧭 นำทาง
                                </button>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-5 text-muted">
                            <div class="fs-1">🗺️</div>
                            <p>ยังไม่มีร้านค้าในแผนที่</p>
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
function getMyLocation() {
    if (!navigator.geolocation) {
        alert('อุปกรณ์นี้ไม่รองรับ GPS');
        return;
    }
    document.getElementById('user-status').innerHTML = '<span class="text-warning">⏳ กำลังรับตำแหน่ง...</span>';
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            document.getElementById('user-status').innerHTML = '<span class="text-success">✅ รับตำแหน่งแล้ว</span>';
            updateDistances();
        },
        function(err) {
            document.getElementById('user-status').innerHTML = '<span class="text-danger">❌ ' + err.message + '</span>';
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function updateDistances() {
    const userLat = window['publicMap_userLat'];
    const userLng = window['publicMap_userLng'];

    if (!userLat || !userLng) {
        setTimeout(updateDistances, 1000);
        return;
    }

    document.querySelectorAll('.store-item').forEach(function(item) {
        const lat = parseFloat(item.dataset.lat);
        const lng = parseFloat(item.dataset.lng);
        const dist = window['publicMap_distance'](userLat, userLng, lat, lng);

        const distEl = item.querySelector('.store-distance');
        if (dist < 1) {
            distEl.innerHTML = '📏 <strong>' + Math.round(dist * 1000) + ' ม.</strong>';
            distEl.className = 'store-distance text-success fw-bold';
        } else if (dist < 5) {
            distEl.innerHTML = '📏 <strong>' + dist.toFixed(1) + ' กม.</strong>';
            distEl.className = 'store-distance text-success';
        } else if (dist < 20) {
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
        return parseFloat(a.dataset.distance || 999) - parseFloat(b.dataset.distance || 999);
    });
    items.forEach(function(item) { list.appendChild(item); });

    filterStores();
}

function filterStores() {
    const radius = parseFloat(document.getElementById('filter-radius').value);
    const type = document.getElementById('filter-type').value;
    const search = document.getElementById('search-name').value.toLowerCase();
    let visible = 0;

    document.querySelectorAll('.store-item').forEach(function(item) {
        const dist = parseFloat(item.dataset.distance || 999);
        const storeType = item.dataset.type || '';
        const storeName = (item.dataset.name || '').toLowerCase();

        let show = true;
        if (dist > radius) show = false;
        if (type && storeType !== type) show = false;
        if (search && !storeName.includes(search)) show = false;

        item.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    document.getElementById('result-count').textContent = visible;
    document.getElementById('result-radius').textContent =
        radius < 999 ? '(ภายใน ' + radius + ' กม.)' : '';
}

function resetFilters() {
    document.getElementById('filter-radius').value = '10';
    document.getElementById('filter-type').value = '';
    document.getElementById('search-name').value = '';
    filterStores();
}

function focusStore(lat, lng, name) {
    const map = window['publicMap_instance'];
    if (map) {
        map.setView([lat, lng], 16);
    }
}

function showRoute(lat, lng, name) {
    const userLat = window['publicMap_userLat'];
    const userLng = window['publicMap_userLng'];

    if (!userLat || !userLng) {
        alert('กรุณากดปุ่ม "ใช้ตำแหน่งปัจจุบัน" ก่อน');
        return;
    }

    window['publicMap_showRoute'](userLat, userLng, lat, lng);

    const dist = window['publicMap_distance'](userLat, userLng, lat, lng);
    // Estimate: car ~40 km/h in city, motorcycle ~30 km/h
    const carMin = Math.round(dist / 40 * 60);
    const motoMin = Math.round(dist / 30 * 60);

    document.getElementById('route-destination').textContent = '🛣️ ' + name;
    document.getElementById('route-distance').textContent = '📏 ' + dist.toFixed(1) + ' กม.';
    document.getElementById('route-time').textContent =
        '🚗 ' + carMin + ' นาที · 🏍️ ' + motoMin + ' นาที';
    document.getElementById('route-info').style.display = 'block';

    document.getElementById('route-nav-btn').onclick = function() {
        navigateTo(lat, lng, name);
    };
}

function clearRoute() {
    window['publicMap_clearRoute']();
    document.getElementById('route-info').style.display = 'none';
}

function navigateTo(lat, lng, name) {
    window['publicMap_navigateTo'](lat, lng, name);
}

// Auto-init: add store markers
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initPublicMap, 500);
});

function initPublicMap() {
    const map = window['publicMap_instance'];
    if (!map) {
        setTimeout(initPublicMap, 500);
        return;
    }

    @foreach($shops as $shop)
    window['publicMap_addMarker']({{ $shop->lat }}, {{ $shop->lng }},
        `<div style="min-width:180px;">
            <strong>{{ $shop->name }}</strong>
            @if($shop->map_description)<div class="small text-muted">{{ $shop->map_description }}</div>@endif
            @if($shop->address)<div class="small text-muted mt-1">📍 {{ Str::limit($shop->address, 50) }}</div>@endif
            @if($shop->phone)<div class="small">📞 <a href="tel:{{ $shop->phone }}">{{ $shop->phone }}</a></div>@endif
            <div class="mt-2">
                <button class="btn btn-sm btn-success w-100" onclick="navigateTo({{ $shop->lat }}, {{ $shop->lng }}, '{{ addslashes($shop->name) }}')">
                    🧭 นำทาง
                </button>
            </div>
        </div>`,
        'store'
    );
    @endforeach

    if ({{ $shops->count() }} > 0) {
        window['publicMap_fitBounds']();
    }

    // Auto-get location
    getMyLocation();
}
</script>

<style>
.store-item {
    transition: all 0.2s;
    cursor: pointer;
}
.store-item:hover {
    background-color: #f0f9ff;
    transform: translateX(2px);
}
</style>
@endsection
