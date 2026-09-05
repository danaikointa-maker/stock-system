@extends('layouts.app')
@section('title', 'Check-in: ' . $shop->name)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">📍 Check-in ที่ร้านค้า</h2>
        <a href="{{ route('agent.shops.index') }}" class="btn btn-outline-secondary">← กลับ</a>
    </div>

    <div class="row">
        <div class="col-lg-6">
            {{-- ข้อมูลร้าน --}}
            <div class="card mb-4">
                <div class="card-header"><strong>🏪 {{ $shop->name }}</strong></div>
                <div class="card-body">
                    @if($shop->address)
                        <p><strong>📍 ที่อยู่:</strong> {{ $shop->address }}</p>
                    @endif
                    @if($shop->phone)
                        <p><strong>📞 โทร:</strong>
                            <a href="tel:{{ $shop->phone }}">{{ $shop->phone }}</a>
                        </p>
                    @endif
                    @if($shop->line_id)
                        <p><strong>💬 LINE:</strong> {{ $shop->line_id }}</p>
                    @endif
                    @if($shop->opening_hours)
                        <p><strong>🕐 เวลาทำการ:</strong> {{ $shop->opening_hours }}</p>
                    @endif
                    @if($shop->lat && $shop->lng)
                        <p><strong>🗺️ พิกัด:</strong> {{ $shop->lat }}, {{ $shop->lng }}
                            <a href="https://maps.google.com/?q={{ $shop->lat }},{{ $shop->lng }}"
                               target="_blank" class="btn btn-sm btn-outline-primary ms-2">เปิด Google Maps</a>
                        </p>
                    @endif

                    @if($shop->photos && count($shop->photos) > 0)
                        <hr>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($shop->photos as $photo)
                                <img src="{{ asset('storage/' . $photo) }}" class="rounded border"
                                     width="100" height="100" style="object-fit:cover;" alt="shop photo">
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            {{-- ฟอร์ม Check-in --}}
            <div class="card mb-4">
                <div class="card-header"><strong>📍 บันทึก Check-in</strong></div>
                <div class="card-body">
                    <form action="{{ route('agent.shops.store-checkin', $shop->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        {{-- GPS auto-capture --}}
                        <div class="mb-3">
                            <label class="form-label">ตำแหน่ง GPS <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="text" name="latitude" id="latitude" class="form-control"
                                           placeholder="ละติจูด" readonly required>
                                </div>
                                <div class="col-6">
                                    <input type="text" name="longitude" id="longitude" class="form-control"
                                           placeholder="ลองจิจูด" readonly required>
                                </div>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="getLocation()">
                                    📍 รับตำแหน่ง GPS
                                </button>
                                <span id="gps-status" class="ms-2 text-muted">กำลังรอ...</span>
                            </div>
                            @if($shop->lat && $shop->lng)
                                <div id="distance-info" class="mt-2"></div>
                            @endif
                        </div>

                        {{-- ประเภท --}}
                        <div class="mb-3">
                            <label class="form-label">ประเภท <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-3">
                                    <input type="radio" name="type" value="visit" id="type-visit" class="btn-check" checked>
                                    <label for="type-visit" class="btn btn-outline-primary w-100">🏪 เยี่ยม</label>
                                </div>
                                <div class="col-3">
                                    <input type="radio" name="type" value="delivery" id="type-delivery" class="btn-check">
                                    <label for="type-delivery" class="btn btn-outline-success w-100">🚚 ส่งของ</label>
                                </div>
                                <div class="col-3">
                                    <input type="radio" name="type" value="pickup" id="type-pickup" class="btn-check">
                                    <label for="type-pickup" class="btn btn-outline-warning w-100">📦 รับของ</label>
                                </div>
                                <div class="col-3">
                                    <input type="radio" name="type" value="other" id="type-other" class="btn-check">
                                    <label for="type-other" class="btn btn-outline-secondary w-100">📌 อื่นๆ</label>
                                </div>
                            </div>
                        </div>

                        {{-- หมายเหตุ --}}
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea name="notes" class="form-control" rows="3"
                                      placeholder="บันทึกสิ่งที่พบ/สิ่งที่ส่ง/ปัญหาที่เจอ...">{{ old('notes') }}</textarea>
                        </div>

                        {{-- รูปถ่าย --}}
                        <div class="mb-3">
                            <label class="form-label">📸 ถ่ายรูปยืนยัน <small class="text-muted">(สูงสุด 5 รูป)</small></label>
                            <input type="file" name="photos[]" id="photo-input" class="form-control" multiple
                                   accept="image/jpeg,image/png,image/webp" capture="environment">
                            <small class="text-muted">ถ่ายรูปหน้าร้าน, สินค้าที่ส่ง, หรือหลักฐานอื่นๆ</small>

                            <div id="photo-preview" class="d-flex flex-wrap gap-2 mt-2"></div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            ✅ ยืนยัน Check-in
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const shopLat = {{ $shop->lat ?: 'null' }};
const shopLng = {{ $shop->lng ?: 'null' }};

// Auto-capture GPS when page loads
document.addEventListener('DOMContentLoaded', function() {
    getLocation();
});

function getLocation() {
    const status = document.getElementById('gps-status');
    if (!navigator.geolocation) {
        status.innerHTML = '<span class="text-danger">❌ อุปกรณ์ไม่รองรับ GPS</span>';
        return;
    }
    status.innerHTML = '<span class="text-warning">⏳ กำลังรับตำแหน่ง...</span>';
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const lat = pos.coords.latitude.toFixed(8);
            const lng = pos.coords.longitude.toFixed(8);
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            status.innerHTML = '<span class="text-success">✅ รับตำแหน่งเรียบร้อย</span>';

            // คำนวณระยะห่าง
            if (shopLat && shopLng) {
                const distance = haversine(parseFloat(lat), parseFloat(lng), shopLat, shopLng);
                const info = document.getElementById('distance-info');
                const color = distance < 100 ? 'success' : (distance < 500 ? 'warning' : 'danger');
                info.innerHTML = '<span class="badge bg-' + color + '">📏 ระยะห่าง: ' + distance + ' ม.</span>';
            }
        },
        function(err) {
            status.innerHTML = '<span class="text-danger">❌ ' + err.message + '</span>';
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function haversine(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
}

// Photo preview
document.getElementById('photo-input').addEventListener('change', function(e) {
    const preview = document.getElementById('photo-preview');
    preview.innerHTML = '';
    Array.from(e.target.files).forEach(function(file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            const img = document.createElement('img');
            img.src = ev.target.result;
            img.className = 'rounded border';
            img.style.cssText = 'width:80px;height:80px;object-fit:cover;';
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
});
</script>
@endsection
