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

  {{-- countdown timer --}}
  <div id="timerBar" style="display:none;background:#FFF3E0;border:1px solid #FFE0B2;border-radius:12px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#E65100;text-align:center">
    ⏱️ <span id="timerText">เซสชันจะหมดอายุใน <b id="timerCount">5:00</b></span>
    <span id="timerExpired" style="display:none"><b>เซสชันหมดอายุแล้ว</b> — กรุณาสแกน QR ใหม่</span>
  </div>

  <form method="POST" action="{{ route('scan.submit') }}" id="scanForm" novalidate>
    @csrf
    {{-- scan session token — กัน refresh/reuse --}}
    <input type="hidden" name="_scan_token" value="{{ $scanToken }}">
    <input type="hidden" name="token" id="tokenHidden" value="{{ $token }}">

    {{-- ตำแหน่ง (เบราว์เซอร์เติมให้ถ้าผู้ใช้อนุญาต) --}}
    <input type="hidden" name="lat" id="lat">
    <input type="hidden" name="lng" id="lng">
    <input type="hidden" name="accuracy" id="accuracy">
    <input type="hidden" name="geo_status" id="geo_status" value="unavailable">

    {{-- ช่องกรอก QR + ปุ่มกล้อง --}}
    <div class="field">
      <label for="tokenInput">รหัส QR <span class="req">*จำเป็น</span></label>
      <div style="display:flex;gap:8px;align-items:stretch">
        <input class="input" type="text" id="tokenInput" name="token"
               value="{{ old('token', $token) }}"
               placeholder="{{ $token ? 'QR จากซองสินค้า' : 'สแกน QR หรือกรอกรหัส' }}"
               {{ $token ? 'readonly' : '' }}
               style="{{ $token ? 'background:#F5F5F0;color:#333' : '' }};flex:1">
        <button type="button" id="cameraBtn" class="btn btn-main"
                style="width:auto;padding:14px 18px;white-space:nowrap;border-radius:14px;font-size:15px;flex-shrink:0"
                title="เปิดกล้องสแกน QR / Barcode">
          📷 สแกน
        </button>
      </div>
      <p class="hint" id="tokenHint">
        @if($token)
          🔒 QR ถูกล็อกแล้ว — ต้องการเปลี่ยนให้สแกนใหม่
        @else
          กดปุ่ม 📷 เพื่อเปิดกล้องสแกน หรือกรอกรหัสเอง
        @endif
      </p>
    </div>

    {{-- กล้องสแกน (ซ่อนไว้ก่อน) --}}
    <div id="cameraBox" style="display:none;margin-bottom:16px">
      <div style="background:#000;border-radius:16px;overflow:hidden;position:relative">
        <div id="qr-reader" style="width:100%"></div>
        <button type="button" id="cameraClose"
                style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:50%;width:36px;height:36px;font-size:18px;cursor:pointer;z-index:10">
          ✕
        </button>
      </div>
      <p class="hint" style="text-align:center;margin-top:8px">
        เล็งกล้องไปที่ QR Code หรือ Barcode บนสินค้า
      </p>
    </div>

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
{{-- html5-qrcode: library สแกน QR/Barcode จากกล้อง --}}
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
(function () {
  'use strict';

  var tokenInput = document.getElementById('tokenInput');
  var tokenHidden = document.getElementById('tokenHidden');
  var phoneInput = document.getElementById('phone');
  var cameraBtn = document.getElementById('cameraBtn');
  var cameraBox = document.getElementById('cameraBox');
  var cameraClose = document.getElementById('cameraClose');
  var tokenHint = document.getElementById('tokenHint');
  var timerBar = document.getElementById('timerBar');
  var timerCount = document.getElementById('timerCount');
  var timerText = document.getElementById('timerText');
  var timerExpired = document.getElementById('timerExpired');
  var qrReader = null;
  var isLocked = {{ $token ? 'true' : 'false' }};

  // ─── 1. จำเบอร์จากเครื่องเดิม (localStorage) ────────────────
  (function rememberPhone() {
    if (!phoneInput || phoneInput.value) return;
    try {
      var saved = localStorage.getItem('roamembers_phone');
      if (saved && /^0[0-9]{8,9}$/.test(saved)) {
        phoneInput.value = saved;
      }
    } catch(e) {}
  })();

  // บันทึกเบอร์ลง localStorage เมื่อพิมพ์
  if (phoneInput) {
    phoneInput.addEventListener('change', function () {
      try { localStorage.setItem('roamembers_phone', this.value); } catch(e) {}
    });
  }

  // ─── 2. Countdown timer ──────────────────────────────────────
  var expirySeconds = {{ $scanExpiry }};
  var remaining = expirySeconds;

  function updateTimer() {
    if (remaining <= 0) {
      timerText.style.display = 'none';
      timerExpired.style.display = 'inline';
      timerBar.style.background = '#FFEBEE';
      timerBar.style.borderColor = '#EF9A9A';
      timerBar.style.color = '#B71C1C';
      // ล็อกฟอร์ม
      if (tokenInput) tokenInput.readOnly = true;
      isLocked = true;
      return;
    }
    var m = Math.floor(remaining / 60);
    var s = remaining % 60;
    timerCount.textContent = m + ':' + (s < 10 ? '0' : '') + s;
    remaining--;
    setTimeout(updateTimer, 1000);
  }

  if (timerBar && expirySeconds > 0) {
    timerBar.style.display = 'block';
    updateTimer();
  }

  // ─── 3. Camera QR/Barcode Scanner ───────────────────────────
  function onScanSuccess(decodedText) {
    // หยุดกล้อง
    stopCamera();

    // ใส่ค่า QR token ลงช่อง
    if (tokenInput) {
      tokenInput.value = decodedText;
      tokenInput.readOnly = true;
      tokenInput.style.background = '#F5F5F0';
      tokenInput.style.color = '#333';
    }
    if (tokenHidden) {
      tokenHidden.value = decodedText;
    }
    isLocked = true;

    // อัปเดต hint
    if (tokenHint) {
      tokenHint.innerHTML = '🔒 <b>สแกนสำเร็จ!</b> — ล็อกแล้ว ต้องการเปลี่ยนให้โหลดหน้าใหม่';
      tokenHint.style.color = '#1B5E20';
    }

    // ซ่อน token ที่มาจาก URL (ถ้ามี) เพราะตอนนี้ใช้ค่าจากกล้อง
    // โฟกัสไปที่ช่องเบอร์อัตโนมัติ
    if (phoneInput) {
      setTimeout(function () { phoneInput.focus(); }, 300);
    }
  }

  function startCamera() {
    cameraBox.style.display = 'block';
    cameraBtn.style.display = 'none';

    try {
      qrReader = new Html5Qrcode('qr-reader');
      qrReader.start(
        { facingMode: 'environment' },
        {
          fps: 10,
          qrbox: { width: 250, height: 250 },
          aspectRatio: 1.0,
          // สแกนทั้ง QR และ Barcode
          formatsToSupport: [
            Html5QrcodeSupportedFormats.QR_CODE,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
          ]
        },
        function (decodedText) {
          // สำเร็จ
          onScanSuccess(decodedText);
        },
        function () {
          // กำลังสแกน... (ไม่ทำอะไร)
        }
      ).catch(function (err) {
        cameraBox.style.display = 'none';
        cameraBtn.style.display = '';
        alert('ไม่สามารถเปิดกล้องได้: ' + err + '\n\nกรุณาอนุญาตการเข้าถึงกล้อง หรือใช้เบราว์เซอร์ที่รองรับ');
      });
    } catch (e) {
      cameraBox.style.display = 'none';
      cameraBtn.style.display = '';
      alert('เบราว์เซอร์นี้ไม่รองรับการสแกน QR จากกล้อง');
    }
  }

  function stopCamera() {
    if (qrReader) {
      qrReader.stop().then(function () {
        qrReader.clear();
      }).catch(function () {});
      qrReader = null;
    }
    cameraBox.style.display = 'none';
    cameraBtn.style.display = '';
  }

  // ปุ่มเปิดกล้อง
  if (cameraBtn) {
    cameraBtn.addEventListener('click', function () {
      if (isLocked) {
        if (confirm('QR ถูกล็อกแล้ว ต้องการเงินรหัสใหม่หรือไม่?\n\nจะรีเซ็ตเซสชันและต้องสแกนใหม่')) {
          window.location.href = '{{ route("scan.form") }}';
        }
        return;
      }
      startCamera();
    });
  }

  // ปุ่มปิดกล้อง
  if (cameraClose) {
    cameraClose.addEventListener('click', stopCamera);
  }

  // ─── 4. ล็อกช่อง QR ไม่ให้แก้เอง ──────────────────────────
  if (tokenInput) {
    tokenInput.addEventListener('input', function () {
      // ถ้าไม่ได้มาจากการสแกน แล้วพิมพ์เอง ก็ยังได้ แต่ล็อกหลังจากพิมพ์
      if (!isLocked && this.value.length >= 8) {
        // ปล่อยให้เลือกได้ — จะล็อกตอน submit
      }
    });

    // ถ้ามีค่าจาก URL อยู่แล้ว → ล็อก
    if (tokenInput.value && {{ $token ? 'true' : 'false' }}) {
      tokenInput.readOnly = true;
      isLocked = true;
    }
  }

  // ─── 5. ขอตำแหน่งจากเบราว์เซอร์ ─────────────────────────────
  var latEl = document.getElementById('lat');
  var lngEl = document.getElementById('lng');
  var accEl = document.getElementById('accuracy');
  var statusEl = document.getElementById('geo_status');

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        latEl.value = pos.coords.latitude.toFixed(7);
        lngEl.value = pos.coords.longitude.toFixed(7);
        accEl.value = Math.round(pos.coords.accuracy);
        statusEl.value = 'granted';
      },
      function () { statusEl.value = 'denied'; },
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
    );
  }

  // ─── 6. กันกดปุ่มซ้ำ + โฟกัส ─────────────────────────────
  var form = document.getElementById('scanForm');
  var btn = document.getElementById('submitBtn');

  form.addEventListener('submit', function () {
    // ตรวจว่า session ยังไม่หมดอายุ
    if (remaining <= 0) {
      alert('เซสชันหมดอายุแล้ว กรุณาสแกน QR ใหม่');
      event.preventDefault();
      return;
    }

    setTimeout(function () {
      btn.disabled = true;
      btn.textContent = 'กำลังบันทึก...';
    }, 0);
  });

  // ─── 7. กรอกเบอร์ให้เหลือแต่ตัวเลข ────────────────────────
  if (phoneInput) {
    phoneInput.addEventListener('input', function () {
      this.value = this.value.replace(/[^0-9]/g, '');
    });
  }

  // ─── 8. ถ้ามี QR จาก URL → โฟกัสเบอร์ทันที ───────────────
  @if($token)
    if (phoneInput && !phoneInput.value) {
      phoneInput.focus();
    }
  @endif

})();
</script>
@endpush
