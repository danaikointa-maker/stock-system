@extends('layouts.app')

@section('title', 'ตั้งค่าโลโก้')

@section('content')
<div class="grid g2">
  {{-- โลโก้ปัจจุบัน --}}
  <div class="card">
    <h3>🖼️ โลโก้ปัจจุบัน</h3>
    <div class="body">
      @if($currentLogo)
        <div style="text-align:center;padding:30px;background:linear-gradient(135deg,#f8fafc,#e2e8f0);border-radius:12px;margin-bottom:20px">
          <img src="{{ $currentLogo['url'] }}" alt="Logo" style="max-width:200px;max-height:200px;object-fit:contain;filter:drop-shadow(0 4px 12px rgba(0,0,0,.15))">
        </div>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;font-size:13px">
          <div>
            <div style="color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.05em">ไฟล์</div>
            <div style="font-weight:600;margin-top:4px">{{ $currentLogo['file'] }}</div>
          </div>
          <div>
            <div style="color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.05em">ประเภท</div>
            <div style="font-weight:600;margin-top:4px">{{ strtoupper($currentLogo['ext']) }}</div>
          </div>
          <div>
            <div style="color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.05em">ขนาด</div>
            <div style="font-weight:600;margin-top:4px">{{ $currentLogo['size'] }}</div>
          </div>
          <div>
            <div style="color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.05em">อัปเดตล่าสุด</div>
            <div style="font-weight:600;margin-top:4px">{{ $currentLogo['updated'] }}</div>
          </div>
        </div>
      @else
        <div class="empty">
          <div style="font-size:48px;margin-bottom:10px">🖼️</div>
          <p>ยังไม่มีโลโก้</p>
        </div>
      @endif
    </div>
  </div>

  {{-- อัปโหลดโลโก้ใหม่ --}}
  <div class="card">
    <h3>⬆️ อัปโหลดโลโก้ใหม่</h3>
    <div class="body">
      <form action="{{ route('admin.brand.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="field">
          <label>เลือกไฟล์โลโก้</label>
          <input type="file" name="logo" accept=".svg,.png,.jpg,.jpeg,.webp,.ico" required
                 style="padding:10px;background:#f8fafc">
          <p style="font-size:12px;color:var(--muted);margin-top:8px;line-height:1.6">
            รองรับ: <b>SVG, PNG, JPG, WEBP, ICO</b><br>
            ขนาดไฟล์ไม่เกิน <b>2MB</b><br>
            แนะนำ: <b>SVG</b> (ย่อขยายได้ไม่แตก) หรือ <b>PNG</b> (พื้นหลังโปร่งใส)
          </p>
        </div>

        <div id="preview" style="display:none;text-align:center;padding:20px;background:#f8fafc;border-radius:12px;margin-bottom:16px">
          <img id="previewImg" style="max-width:150px;max-height:150px;object-fit:contain">
        </div>

        <button type="submit" class="btn btn-p" style="width:100%">
          ⬆️ อัปโหลดและใช้งานทันที
        </button>
      </form>

      @if($currentLogo)
        <hr style="margin:24px 0;border:none;border-top:1px solid var(--line)">
        <form action="{{ route('admin.brand.destroy') }}" method="POST" onsubmit="return confirm('ต้องการรีเซ็ตโลโก้เป็นค่าเริ่มต้นหรือไม่?')">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-d" style="width:100%">
            🔄 รีเซ็ตเป็นค่าเริ่มต้น
          </button>
        </form>
      @endif
    </div>
  </div>
</div>

{{-- ข้อมูลเพิ่มเติม --}}
<div class="card">
  <h3>ℹ️ โลโก้ใช้ที่ไหนบ้าง?</h3>
  <div class="body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px">
      <div style="padding:16px;background:#f0fdf4;border-radius:10px;border:1px solid #86efac">
        <div style="font-size:20px;margin-bottom:6px">🌐</div>
        <div style="font-weight:700;font-size:13px;color:#15803d">Favicon (ไอคอนแท็บเบราว์เซอร์)</div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px">แสดงที่แท็บเบราว์เซอร์และ bookmarks</div>
      </div>
      <div style="padding:16px;background:#eff6ff;border-radius:10px;border:1px solid #93c5fd">
        <div style="font-size:20px;margin-bottom:6px">📱</div>
        <div style="font-weight:700;font-size:13px;color:#1e40af">Sidebar (เมนูด้านซ้าย)</div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px">แสดงที่ด้านบนของ sidebar ทุกหน้า</div>
      </div>
      <div style="padding:16px;background:#fef3c7;border-radius:10px;border:1px solid #fde047">
        <div style="font-size:20px;margin-bottom:6px">🎨</div>
        <div style="font-weight:700;font-size:13px;color:#a16207">หน้าสแกน QR</div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px">แสดงที่หน้าสแกน QR, กระเป๋าแต้ม, ใบเสร็จ</div>
      </div>
      <div style="padding:16px;background:#fce7f3;border-radius:10px;border:1px solid #f9a8d4">
        <div style="font-size:20px;margin-bottom:6px">🏪</div>
        <div style="font-weight:700;font-size:13px;color:#be185d">หน้าร้านออนไลน์</div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px">แสดงที่หน้าร้านและ footer</div>
      </div>
      <div style="padding:16px;background:#e0e7ff;border-radius:10px;border:1px solid #a5b4fc">
        <div style="font-size:20px;margin-bottom:6px">⚙️</div>
        <div style="font-weight:700;font-size:13px;color:#4338ca">หน้าติดตั้งระบบ</div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px">แสดงที่ setup wizard</div>
      </div>
      <div style="padding:16px;background:#f0fdfa;border-radius:10px;border:1px solid #5eead4">
        <div style="font-size:20px;margin-bottom:6px">📄</div>
        <div style="font-weight:700;font-size:13px;color:#0f766e">เอกสารและใบเสร็จ</div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px">แสดงที่ใบเสร็จและ statement</div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.querySelector('input[name="logo"]').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (!file) return;
  
  const reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('previewImg').src = e.target.result;
    document.getElementById('preview').style.display = 'block';
  };
  reader.readAsDataURL(file);
});
</script>
@endpush
@endsection
