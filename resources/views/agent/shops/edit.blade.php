@extends('layouts.app')
@section('title', 'แก้ไขข้อมูลร้านค้า: ' . $shop->name)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">✏️ แก้ไขข้อมูลร้านค้า</h2>
        <a href="{{ route('agent.shops.index') }}" class="btn btn-outline-secondary">← กลับ</a>
    </div>

    <form action="{{ route('agent.shops.update', $shop->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row">
            {{-- ข้อมูลพื้นฐาน --}}
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><strong>🏪 ข้อมูลพื้นฐาน</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">ชื่อร้าน <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $shop->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ประเภทร้าน</label>
                            <select name="shop_type" class="form-select">
                                <option value="">-- เลือก --</option>
                                <option value="ร้านสะดวกซื้อ" {{ old('shop_type', $shop->shop_type) === 'ร้านสะดวกซื้อ' ? 'selected' : '' }}>ร้านสะดวกซื้อ</option>
                                <option value="ร้านขายของชำ" {{ old('shop_type', $shop->shop_type) === 'ร้านขายของชำ' ? 'selected' : '' }}>ร้านขายของชำ</option>
                                <option value="ซูเปอร์มาร์เก็ต" {{ old('shop_type', $shop->shop_type) === 'ซูเปอร์มาร์เก็ต' ? 'selected' : '' }}>ซูเปอร์มาร์เก็ต</option>
                                <option value="ร้านขายส่ง" {{ old('shop_type', $shop->shop_type) === 'ร้านขายส่ง' ? 'selected' : '' }}>ร้านขายส่ง</option>
                                <option value="ตลาดนัด" {{ old('shop_type', $shop->shop_type) === 'ตลาดนัด' ? 'selected' : '' }}>ตลาดนัด</option>
                                <option value="ออนไลน์" {{ old('shop_type', $shop->shop_type) === 'ออนไลน์' ? 'selected' : '' }}>ออนไลน์</option>
                                <option value="อื่นๆ" {{ old('shop_type', $shop->shop_type) === 'อื่นๆ' ? 'selected' : '' }}>อื่นๆ</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">เวลาทำการ</label>
                            <input type="text" name="opening_hours" class="form-control"
                                   value="{{ old('opening_hours', $shop->opening_hours) }}"
                                   placeholder="เช่น 08:00-20:00 ทุกวัน">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea name="notes" class="form-control" rows="3"
                                      placeholder="ข้อมูลเพิ่มเติมเกี่ยวกับร้าน...">{{ old('notes', $shop->notes) }}</textarea>
                        </div>

                        <hr>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_on_map" value="1"
                                       id="show_on_map" {{ old('show_on_map', $shop->show_on_map) ? 'checked' : '' }}>
                                <label class="form-check-label" for="show_on_map">
                                    🗺️ แสดงบนแผนที่สาธารณะ (ลูกค้าเห็น)
                                </label>
                            </div>
                            <small class="text-muted">ถ้าเปิด ร้านค้านี้จะแสดงในหน้า "ค้นหาร้านค้าใกล้ฉัน"</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">คำอธิบายสั้น (แสดงบนแผนที่)</label>
                            <input type="text" name="map_description" class="form-control"
                                   value="{{ old('map_description', $shop->map_description) }}"
                                   placeholder="เช่น จำหน่ายสินค้าครบวงจร เปิดทุกวัน"
                                   maxlength="255">
                        </div>
                    </div>
                </div>

                {{-- ข้อมูลติดต่อ --}}
                <div class="card mb-4">
                    <div class="card-header"><strong>📞 ข้อมูลติดต่อ</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" class="form-control"
                                   value="{{ old('phone', $shop->phone) }}" placeholder="08X-XXX-XXXX">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="{{ old('email', $shop->email) }}" placeholder="shop@example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">LINE ID</label>
                            <input type="text" name="line_id" class="form-control"
                                   value="{{ old('line_id', $shop->line_id) }}" placeholder="@line_id">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ที่อยู่ + พิกัด + รูป --}}
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><strong>📍 ที่อยู่และพิกัด GPS</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">ที่อยู่</label>
                            <textarea name="address" class="form-control" rows="3"
                                      placeholder="บ้านเลขที่ ซอย ถนน แขวง/ตำบล เขต/อำเภอ จังหวัด รหัสไปรษณีย์">{{ old('address', $shop->address) }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">ละติจูด (Latitude)</label>
                                <input type="text" name="lat" id="lat" class="form-control"
                                       value="{{ old('lat', $shop->lat) }}" placeholder="13.7563">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">ลองจิจูด (Longitude)</label>
                                <input type="text" name="lng" id="lng" class="form-control"
                                       value="{{ old('lng', $shop->lng) }}" placeholder="100.5018">
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline-primary w-100" onclick="getLocation()">
                            📍 ใช้ตำแหน่งปัจจุบัน
                        </button>
                        <small class="text-muted d-block mt-2 text-center">
                            กดปุ่มเพื่อใช้ GPS ของอุปกรณ์ หรือกรอกเองจาก Google Maps
                        </small>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><strong>📸 รูปถ่ายร้าน</strong> <small class="text-muted">(สูงสุด 5 รูป)</small></div>
                    <div class="card-body">
                        {{-- รูปที่มีอยู่ --}}
                        @if($shop->photos && count($shop->photos) > 0)
                            <label class="form-label">รูปปัจจุบัน (ติ๊กเพื่อเก็บไว้)</label>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach($shop->photos as $photo)
                                <label class="position-relative" style="cursor:pointer;">
                                    <input type="checkbox" name="keep_photos[]" value="{{ $photo }}" checked
                                           class="position-absolute top-0 start-0 m-1" style="z-index:1;width:20px;height:20px;">
                                    <img src="{{ asset('storage/' . $photo) }}" class="rounded border"
                                         width="100" height="100" style="object-fit:cover;" alt="shop photo">
                                </label>
                                @endforeach
                            </div>
                            <hr>
                        @endif

                        <div class="mb-3">
                            <label class="form-label">เพิ่มรูปใหม่</label>
                            <input type="file" name="photos[]" class="form-control" multiple
                                   accept="image/jpeg,image/png,image/webp" capture="environment">
                            <small class="text-muted">รองรับ JPG, PNG, WebP (ไฟล์ละไม่เกิน 5 MB)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end">
            <a href="{{ route('agent.shops.index') }}" class="btn btn-secondary me-2">ยกเลิก</a>
            <button type="submit" class="btn btn-primary">💾 บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<script>
function getLocation() {
    if (!navigator.geolocation) {
        alert('อุปกรณ์นี้ไม่รองรับ GPS');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            document.getElementById('lat').value = pos.coords.latitude.toFixed(8);
            document.getElementById('lng').value = pos.coords.longitude.toFixed(8);
        },
        function(err) {
            alert('ไม่สามารถรับตำแหน่งได้: ' + err.message);
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}
</script>
@endsection
