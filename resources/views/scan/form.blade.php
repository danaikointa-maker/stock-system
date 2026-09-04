@extends('layouts.public')
@section('title', 'สแกนรับแต้ม · RoaMembers')

@section('body')
<div class="hero">
  <div class="brandbar">
    <img src="{{ asset('brand/logo.svg') }}" alt="RoaMembers">
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
    <span id="timerExpired" style="display:none"><b>เซสชันหมดอายุแล้ว</b> — กรุณาสแกน QR ใหม่อีกครั้ง</span>
  </div>

  <form method="POST" action="{{ route('scan.submit') }}" id="scanForm" novalidate>
    @csrf
    <input type="hidden" name="_scan_token" value="{{ $scanToken }}">
    <input type="hidden" name="token" id="tokenHidden" value="{{ $token }}">
    <input type="hidden" name="lat" id="lat">
    <input type="hidden" name="lng" id="lng">
    <input type="hidden" name="accuracy" id="accuracy">
    <input type="hidden" name="geo_status" id="geo_status" value="unavailable">

    {{-- ─── ปุ่มสแกน 3 แบบ ─────────────────────────────────── --}}
    <div class="field" id="scanButtons" style="{{ $token ? 'display:none' : '' }}">
      <label>📱 สแกน QR / Barcode</label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
        {{-- ปุ่มหลัก: ถ่ายรูปสแกน (native camera) --}}
        <label for="cameraInput" class="btn btn-main" style="margin:0;text-align:center;cursor:pointer;padding:16px 10px;font-size:14px;border-radius:14px">
          📷 ถ่ายรูปสแกน
        </label>
        {{-- ปุ่มรอง: เลือกรูปจาก gallery --}}
        <label for="galleryInput" class="btn" style="margin:0;text-align:center;cursor:pointer;padding:16px 10px;font-size:14px;border-radius:14px;background:#fff;color:var(--brand-dark);border:2px solid #1A1A1A">
          📁 เลือกรูป
        </label>
      </div>
      {{-- ปุ่มเสริม: สแกนสด (live stream) — แสดงเฉพาะ browser ที่รองรับ --}}
      <button type="button" id="liveScanBtn" class="btn"
              style="display:none;width:100%;margin:0;text-align:center;padding:12px;font-size:13px;border-radius:14px;background:#F5F5F0;color:#555;border:1px dashed #ccc">
        ⚡ สแกนสด (เปิดกล้องต่อเนื่อง)
      </button>

      {{-- Native file inputs (ซ่อน) --}}
      <input type="file" id="cameraInput" accept="image/*" capture="environment" style="display:none">
      <input type="file" id="galleryInput" accept="image/*" style="display:none">

      <p class="hint">ถ่ายรูป QR/Barcode บนซองสินค้า หรือเลือกรูปที่บันทึกไว้</p>
    </div>

    {{-- ─── กำลังสแกนรูป... ────────────────────────────────── --}}
    <div id="scanLoading" style="display:none" class="alert a-info">
      ⏳ <b>กำลังอ่าน QR/Barcode...</b> กรุณารอสักครู่
    </div>

    {{-- ─── ข้อผิดพลาดสแกน ─────────────────────────────────── --}}
    <div id="scanError" style="display:none" class="alert a-bad">
      <b>❌ อ่านไม่ได้</b>
      <div id="scanErrorMsg" style="margin-top:4px;font-size:13px"></div>
      <button type="button" id="retryBtn" class="btn btn-main" style="margin-top:10px;padding:10px 16px;font-size:13px">
        📷 ลองใหม่
      </button>
    </div>

    {{-- ─── Live camera (ซ่อนไว้ก่อน) ──────────────────────── --}}
    <div id="liveCameraBox" style="display:none;margin-bottom:16px">
      <div style="background:#000;border-radius:16px;overflow:hidden;position:relative">
        <div id="qr-reader" style="width:100%"></div>
        <button type="button" id="liveClose"
                style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:50%;width:36px;height:36px;font-size:18px;cursor:pointer;z-index:10">
          ✕
        </button>
      </div>
      <p class="hint" style="text-align:center;margin-top:8px">
        เล็งกล้องไปที่ QR Code หรือ Barcode
      </p>
    </div>

    {{-- ─── ช่องแสดงรหัส QR (หลังสแกนสำเร็จ) ─────────────── --}}
    <div class="field">
      <label for="tokenInput">รหัส QR <span class="req">*จำเป็น</span></label>
      <input class="input" type="text" id="tokenInput" name="token"
             value="{{ old('token', $token) }}"
             placeholder="{{ $token ? '✅ สแกนเรียบร้อย' : 'สแกน QR หรือกรอกรหัสเอง' }}"
             {{ $token ? 'readonly' : '' }}
             style="{{ $token ? 'background:#E8F5E9;color:#1B5E20;font-weight:600' : '' }}">
      <p class="hint" id="tokenHint">
        @if($token)
          ✅ QR ถูกล็อกแล้ว — ต้องการเปลี่ยนให้กดปุ่มสแกนใหม่
        @else
          ถ่ายรูปสแกน หรือกรอกรหัสเองก็ได้
        @endif
      </p>
    </div>

    {{-- ─── ปุ่มสแกนใหม่ (แสดงเมื่อมี token แล้ว) ────────── --}}
    <div id="rescanBox" style="{{ $token ? '' : 'display:none' }}margin-bottom:16px">
      <button type="button" id="rescanBtn" class="btn" style="width:100%;padding:12px;font-size:13px;border-radius:14px;background:#FFF3E0;color:#E65100;border:1px solid #FFE0B2">
        🔄 สแกน QR ใหม่
      </button>
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
    @php
      $lineConfigured = config('services.line.client_id');
      $googleConfigured = config('services.google.client_id');
    @endphp

    @if($lineConfigured)
      <a href="{{ route('social.redirect', 'line') }}" class="btn btn-line">เข้าสู่ระบบด้วย LINE</a>
    @else
      <button type="button" class="btn btn-line" disabled style="opacity:.5;cursor:not-allowed">
        LINE (ยังไม่ได้ตั้งค่า)
      </button>
    @endif

    @if($googleConfigured)
      <a href="{{ route('social.redirect', 'google') }}" class="btn btn-goog">เข้าสู่ระบบด้วย Google</a>
    @else
      <button type="button" class="btn btn-goog" disabled style="opacity:.5;cursor:not-allowed">
        Google (ยังไม่ได้ตั้งค่า)
      </button>
    @endif
  </div>
  <p class="hint" style="text-align:center;margin-top:12px">
    @if($lineConfigured || $googleConfigured)
      เข้าด้วย LINE หรือ Google = สมัครอัตโนมัติ ไม่ต้องกรอกอะไรเพิ่ม
    @else
      💡 ผู้ดูแลระบบยังไม่ได้ตั้งค่า LINE/Google Login<br>
      ดูวิธีตั้งค่าที่ <code>SOCIAL_LOGIN.md</code>
    @endif
  </p>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
(function () {
  'use strict';

  // ─── DOM elements ──────────────────────────────────────────
  var $ = function (id) { return document.getElementById(id); };
  var tokenInput   = $('tokenInput');
  var tokenHidden  = $('tokenHidden');
  var phoneInput   = $('phone');
  var tokenHint    = $('tokenHint');
  var scanButtons  = $('scanButtons');
  var scanLoading   = $('scanLoading');
  var scanError     = $('scanError');
  var scanErrorMsg  = $('scanErrorMsg');
  var retryBtn      = $('retryBtn');
  var rescanBox     = $('rescanBox');
  var rescanBtn     = $('rescanBtn');
  var cameraInput   = $('cameraInput');
  var galleryInput  = $('galleryInput');
  var liveScanBtn   = $('liveScanBtn');
  var liveCameraBox = $('liveCameraBox');
  var liveClose     = $('liveClose');
  var timerBar      = $('timerBar');
  var timerCount    = $('timerCount');
  var timerText     = $('timerText');
  var timerExpired  = $('timerExpired');
  var qrReader      = null;
  var isLocked      = {{ $token ? 'true' : 'false' }};

  // ─── 1. จำเบอร์จาก localStorage ───────────────────────────
  (function () {
    if (!phoneInput || phoneInput.value) return;
    try {
      var saved = localStorage.getItem('roamembers_phone');
      if (saved && /^0[0-9]{8,9}$/.test(saved)) phoneInput.value = saved;
    } catch(e) {}
  })();
  if (phoneInput) {
    phoneInput.addEventListener('change', function () {
      try { localStorage.setItem('roamembers_phone', this.value); } catch(e) {}
    });
  }

  // ─── 2. Countdown timer ────────────────────────────────────
  var remaining = {{ $scanExpiry }};
  function updateTimer() {
    if (remaining <= 0) {
      timerText.style.display = 'none';
      timerExpired.style.display = 'inline';
      timerBar.style.background = '#FFEBEE';
      timerBar.style.borderColor = '#EF9A9A';
      timerBar.style.color = '#B71C1C';
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
  if (timerBar && {{ $scanExpiry }} > 0) {
    timerBar.style.display = 'block';
    updateTimer();
  }

  // ─── 3. ฟังก์ชันกลาง: สแกนสำเร็จ ──────────────────────────
  function onScanSuccess(decodedText) {
    // หยุด live camera (ถ้าเปิดอยู่)
    stopLiveCamera();

    // ซ่อน loading / error
    scanLoading.style.display = 'none';
    scanError.style.display = 'none';

    // ใส่ค่า QR token
    if (tokenInput) {
      tokenInput.value = decodedText;
      tokenInput.readOnly = true;
      tokenInput.style.background = '#E8F5E9';
      tokenInput.style.color = '#1B5E20';
      tokenInput.style.fontWeight = '600';
    }
    if (tokenHidden) tokenHidden.value = decodedText;
    isLocked = true;

    // ซ่อนปุ่มสแกน แสดงปุ่มสแกนใหม่
    if (scanButtons) scanButtons.style.display = 'none';
    if (rescanBox) rescanBox.style.display = '';

    // อัปเดต hint
    if (tokenHint) {
      tokenHint.innerHTML = '✅ <b>สแกนสำเร็จ!</b> รหัส: <code>' + decodedText.substring(0, 20) + (decodedText.length > 20 ? '...' : '') + '</code>';
      tokenHint.style.color = '#1B5E20';
    }

    // โฟกัสเบอร์
    if (phoneInput) setTimeout(function () { phoneInput.focus(); }, 300);
  }

  // ─── 4. ฟังก์ชันกลาง: สแกนไม่สำเร็จ ───────────────────────
  function onScanError(msg) {
    scanLoading.style.display = 'none';
    scanError.style.display = 'block';
    scanErrorMsg.textContent = msg;
  }

  // ─── 5. สแกนจากรูปที่ถ่าย/เลือก ──────────────────────────
  function scanFromFile(file) {
    if (!file) return;

    // แสดง loading
    scanLoading.style.display = 'block';
    scanError.style.display = 'none';

    // ตรวจสอบ library
    if (typeof Html5Qrcode === 'undefined') {
      onScanError('โหลด library สแกนไม่สำเร็จ กรุณากรอกรหัส QR เอง');
      return;
    }

    // สร้าง temp element สำหรับ scanFile
    var tempId = 'qr-scan-' + Date.now();
    var tempDiv = document.createElement('div');
    tempDiv.id = tempId;
    tempDiv.style.cssText = 'position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden';
    document.body.appendChild(tempDiv);

    var reader = new Html5Qrcode(tempId);
    reader.scanFile(file, /* verbose= */ true)
      .then(function (decodedText) {
        onScanSuccess(decodedText);
        // cleanup
        reader.clear().catch(function () {});
        tempDiv.remove();
      })
      .catch(function (err) {
        // cleanup
        reader.clear().catch(function () {});
        tempDiv.remove();

        onScanError(
          'ไม่สามารถอ่าน QR/Barcode จากรูปนี้ได้\n'
          + 'กรุณาถ่ายรูปใหม่ให้ชัดขึ้น (แสงสว่าง, ไม่เบลอ, QR เต็มกรอบ)\n'
          + 'หรือกรอกรหัสเองในช่องด้านล่าง'
        );
      });
  }

  // ─── 6. Event: ถ่ายรูปสแกน (native camera) ───────────────
  if (cameraInput) {
    cameraInput.addEventListener('change', function (e) {
      var file = e.target.files[0];
      if (file) scanFromFile(file);
      // reset input เพื่อให้ถ่ายใหม่ได้
      this.value = '';
    });
  }

  // ─── 7. Event: เลือกรูปจาก gallery ────────────────────────
  if (galleryInput) {
    galleryInput.addEventListener('change', function (e) {
      var file = e.target.files[0];
      if (file) scanFromFile(file);
      this.value = '';
    });
  }

  // ─── 8. Live camera (สแกนสด — optional) ──────────────────
  function showLiveButton() {
    // แสดงปุ่มสแกนสดเฉพาะเมื่อ browser รองรับ getUserMedia
    if (liveScanBtn && navigator.mediaDevices && navigator.mediaDevices.getUserMedia
        && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
      liveScanBtn.style.display = 'block';
    }
  }
  showLiveButton();

  function startLiveCamera() {
    if (typeof Html5Qrcode === 'undefined') {
      onScanError('โหลด library สแกนไม่สำเร็จ');
      return;
    }

    liveCameraBox.style.display = 'block';
    scanButtons.style.display = 'none';

    try {
      qrReader = new Html5Qrcode('qr-reader');
      qrReader.start(
        { facingMode: 'environment' },
        {
          fps: 10,
          qrbox: { width: 250, height: 250 },
          aspectRatio: 1.0,
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
        function (decodedText) { onScanSuccess(decodedText); },
        function () {} // กำลังสแกน...
      ).catch(function (err) {
        stopLiveCamera();
        // ถ้า live camera ไม่ทำงาน → แจ้งแต่ยังให้ใช้ถ่ายรูปได้
        var msg = 'เปิดกล้องสดไม่ได้ — ใช้ปุ่ม "📷 ถ่ายรูปสแกน" แทน';
        if (String(err).indexOf('NotAllowedError') >= 0) {
          msg = 'คุณปฏิเสธการเข้าถึงกล้อง — กรุณาอนุญาตกล้องแล้วลองใหม่';
        }
        onScanError(msg);
      });
    } catch (e) {
      stopLiveCamera();
      onScanError('เกิดข้อผิดพลาด: ' + e.message + '\nใช้ปุ่ม "📷 ถ่ายรูปสแกน" แทน');
    }
  }

  function stopLiveCamera() {
    if (qrReader) {
      qrReader.stop().then(function () { qrReader.clear(); }).catch(function () {});
      qrReader = null;
    }
    liveCameraBox.style.display = 'none';
    if (!isLocked && scanButtons) scanButtons.style.display = '';
  }

  if (liveScanBtn) {
    liveScanBtn.addEventListener('click', startLiveCamera);
  }
  if (liveClose) {
    liveClose.addEventListener('click', stopLiveCamera);
  }

  // ─── 9. ปุ่มลองใหม่ / สแกนใหม่ ──────────────────────────
  if (retryBtn) {
    retryBtn.addEventListener('click', function () {
      scanError.style.display = 'none';
      // เปิดกล้องถ่ายรูปใหม่
      if (cameraInput) cameraInput.click();
    });
  }
  if (rescanBtn) {
    rescanBtn.addEventListener('click', function () {
      // รีเซ็ตทุกอย่าง
      if (tokenInput) {
        tokenInput.value = '';
        tokenInput.readOnly = false;
        tokenInput.style.background = '';
        tokenInput.style.color = '';
        tokenInput.style.fontWeight = '';
        tokenInput.placeholder = 'สแกน QR หรือกรอกรหัสเอง';
      }
      if (tokenHidden) tokenHidden.value = '';
      isLocked = false;
      scanError.style.display = 'none';
      scanLoading.style.display = 'none';
      if (scanButtons) scanButtons.style.display = '';
      if (rescanBox) rescanBox.style.display = 'none';
      if (tokenHint) {
        tokenHint.innerHTML = '📷 ถ่ายรูปสแกน หรือกรอกรหัสเองก็ได้';
        tokenHint.style.color = '';
      }
    });
  }

  // ─── 10. ขอตำแหน่ง GPS ────────────────────────────────────
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        $('lat').value = pos.coords.latitude.toFixed(7);
        $('lng').value = pos.coords.longitude.toFixed(7);
        $('accuracy').value = Math.round(pos.coords.accuracy);
        $('geo_status').value = 'granted';
      },
      function () { $('geo_status').value = 'denied'; },
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
    );
  }

  // ─── 11. Form submit ──────────────────────────────────────
  var form = $('scanForm');
  var submitBtn = $('submitBtn');
  form.addEventListener('submit', function (e) {
    if (remaining <= 0) {
      e.preventDefault();
      alert('เซสชันหมดอายุแล้ว กรุณาสแกน QR ใหม่');
      return;
    }
    setTimeout(function () {
      submitBtn.disabled = true;
      submitBtn.textContent = 'กำลังบันทึก...';
    }, 0);
  });

  // ─── 12. กรอกเบอร์เหลือแต่ตัวเลข ─────────────────────────
  if (phoneInput) {
    phoneInput.addEventListener('input', function () {
      this.value = this.value.replace(/[^0-9]/g, '');
    });
  }

  // ─── 13. โฟกัสเบอร์ถ้ามี QR จาก URL แล้ว ─────────────────
  @if($token)
    if (phoneInput && !phoneInput.value) phoneInput.focus();
  @endif

})();
</script>
@endpush
