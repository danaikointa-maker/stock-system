@extends('layouts.public')
@section('title', 'สแกนรับแต้ม · RoaMembers')

@section('body')
<div class="hero">
  <div class="brandbar">
    <img src="{{ asset('brand/logo-192.png') }}" alt="RoaMembers">
    <div>
      <span class="name">RoaMembers</span>
      <span class="sub">สะสมแต้ม · แลกของรางวัล</span>
    </div>
  </div>

  <div class="headline">
    <h1>ยิ่งสะสม<em>ยิ่งคุ้ม!</em></h1>
    <p>สแกนทุกซอง แลกของรางวัลและบริการ<br>จากร้านค้าที่ร่วมรายการ</p>
  </div>
</div>

<div class="sheet">
  <div class="grabber"></div>

  @if(session('status'))
    <div class="alert a-ok">{{ session('status') }}</div>
  @endif

  @if($errors->any())
    <div class="alert a-bad">
      @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif

  {{-- แสดงสินค้าที่กำลังจะสแกน --}}
  @if($preview)
    @if($preview['used'])
      <div class="alert a-bad">
        <b>QR นี้ถูกใช้ไปแล้ว</b><br>
        รหัสนี้เคยรับแต้มไปเรียบร้อยแล้ว ไม่สามารถใช้ซ้ำได้
      </div>
    @else
      <div class="alert a-info">
        <b>{{ $preview['product'] }}</b><br>
        สแกนแล้วรับทันที <b>{{ number_format($preview['points']) }} แต้ม</b>
      </div>
    @endif
  @endif

  {{-- ลูกค้าเดิมที่จำไว้แล้ว --}}
  @if($customer)
    <div class="alert a-ok" style="display:flex;justify-content:space-between;align-items:center;gap:10px">
      <div>
        สวัสดี <b>{{ $customer->name }}</b><br>
        <span style="font-size:12px">{{ $customer->phone }}</span>
      </div>
      <a href="{{ route('scan.wallet') }}" style="font-size:12.5px;color:#1B5E20;font-weight:700">
        ดูกระเป๋าแต้ม →
      </a>
    </div>
  @endif

  {{-- ขั้นตอน 4 ช่อง --}}
  @unless($customer)
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px">
      @foreach([['1','📱','สแกน QR'],['2','📞','ใส่เบอร์'],['3','⭐','รับแต้ม'],['4','🎁','แลกรางวัล']] as [$n,$ic,$tx])
        <div style="background:#fff;border:2px solid #1A1A1A;border-radius:14px;padding:12px 10px;text-align:center;position:relative">
          <span style="position:absolute;top:-11px;left:-9px;width:26px;height:26px;border-radius:50%;background:var(--gold);color:var(--brand-dark);font-weight:800;font-size:14px;display:grid;place-items:center;border:2px solid #1A1A1A">{{ $n }}</span>
          <span style="font-size:24px;line-height:1;margin-bottom:6px;display:block">{{ $ic }}</span>
          <b style="font-size:13px;display:block">{{ $tx }}</b>
        </div>
      @endforeach
    </div>
  @endunless

  <form method="POST" action="{{ route('scan.submit') }}" id="scanForm" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    {{-- ตำแหน่ง (เบราว์เซอร์เติมให้ถ้าผู้ใช้อนุญาต) --}}
    <input type="hidden" name="lat" id="lat">
    <input type="hidden" name="lng" id="lng">
    <input type="hidden" name="accuracy" id="accuracy">
    <input type="hidden" name="geo_status" id="geo_status" value="unavailable">

    @unless($token)
      <div class="field">
        <label for="tokenInput">รหัส QR <span class="req">*จำเป็น</span></label>
        <input class="input" type="text" id="tokenInput" name="token"
               value="{{ old('token') }}" placeholder="สแกน QR บนซองสินค้า">
        <p class="hint">ปกติระบบจะกรอกให้เองเมื่อสแกนจาก QR</p>
      </div>
    @endunless

    <div class="field">
      <label for="phone">เบอร์โทรศัพท์ <span class="req">*จำเป็น</span></label>
      <input class="input {{ $errors->has('phone') ? 'err' : '' }}"
             type="tel" id="phone" name="phone" inputmode="numeric"
             value="{{ old('phone', $customer->phone ?? '') }}"
             placeholder="08X-XXX-XXXX" maxlength="10" required>
      @error('phone')<p class="errmsg">{{ $message }}</p>@enderror
      <p class="hint">ใช้เบอร์นี้เก็บแต้มของคุณ · 1 เบอร์ต่อ 1 บัญชี</p>
    </div>

    @unless($customer)
      <div class="field">
        <label for="name">ชื่อเล่น <span class="opt">(ไม่บังคับ — กรอกทีหลังได้)</span></label>
        <input class="input" type="text" id="name" name="name"
               value="{{ old('name') }}" placeholder="เช่น สมชาย" maxlength="120">
      </div>
    @endunless

    <div class="field">
      <label for="secret">รหัสใต้ฟิล์มขูด <span class="opt">(ถ้ามี)</span></label>
      <input class="input" type="text" id="secret" name="secret"
             value="{{ old('secret') }}" placeholder="ขูดฟิล์มในซองแล้วกรอกรหัส" maxlength="60">
      <p class="hint">ช่วยยืนยันว่าคุณซื้อสินค้าจริง ไม่ใช่ถ่ายรูป QR มา</p>
    </div>

    <div class="consent">
      <input type="checkbox" id="consent" name="consent" value="1" {{ old('consent') ? 'checked' : '' }}>
      <label for="consent">
        ยอมรับเงื่อนไขและนโยบายความเป็นส่วนตัว
        อนุญาตให้ใช้ตำแหน่งที่ตั้งเพื่อยืนยันร้านค้าที่ซื้อ
        (ไม่อนุญาตก็ยังรับแต้มได้)
      </label>
    </div>

    <button type="submit" class="btn btn-main" id="submitBtn" style="margin-top:16px">
      รับแต้มเลย →
    </button>
  </form>

  <div class="divider">หรือเข้าสู่ระบบด้วย</div>
  <div style="display:flex;flex-direction:column;gap:10px">
    <a href="{{ route('social.redirect', 'line') }}" class="btn btn-line">เข้าสู่ระบบด้วย LINE</a>
    <a href="{{ route('social.redirect', 'google') }}" class="btn btn-goog">เข้าสู่ระบบด้วย Google</a>
  </div>
  <p class="hint" style="text-align:center;margin-top:12px">
    เข้าด้วย LINE หรือ Google = สมัครอัตโนมัติ ไม่ต้องกรอกอะไรเพิ่ม
  </p>
</div>
@endsection

@push('scripts')
<script>
/*
 * ขอตำแหน่งจากเบราว์เซอร์
 *
 * ข้อจำกัด: เบราว์เซอร์ทำแบบเบื้องหลังไม่ได้ ต้องขึ้น popup ขออนุญาตเสมอ
 * ถ้าผู้ใช้ปฏิเสธ ระบบยังให้แต้มตามปกติ แค่บันทึกว่า denied ไว้
 */
(function () {
  var latEl = document.getElementById('lat');
  var lngEl = document.getElementById('lng');
  var accEl = document.getElementById('accuracy');
  var statusEl = document.getElementById('geo_status');

  if (!navigator.geolocation) {
    statusEl.value = 'unavailable';
    return;
  }

  navigator.geolocation.getCurrentPosition(
    function (pos) {
      latEl.value = pos.coords.latitude.toFixed(7);
      lngEl.value = pos.coords.longitude.toFixed(7);
      accEl.value = Math.round(pos.coords.accuracy);
      statusEl.value = 'granted';
    },
    function () {
      statusEl.value = 'denied';
    },
    { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
  );
})();

/* กันกดปุ่มซ้ำจนเกิดรายการซ้ำ */
(function () {
  var form = document.getElementById('scanForm');
  var btn = document.getElementById('submitBtn');

  form.addEventListener('submit', function () {
    setTimeout(function () {
      btn.disabled = true;
      btn.textContent = 'กำลังบันทึก...';
    }, 0);
  });
})();

/* กรอกเบอร์ให้เหลือแต่ตัวเลข */
(function () {
  var phone = document.getElementById('phone');
  if (!phone) return;
  phone.addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
  });
})();
</script>
@endpush
