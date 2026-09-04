@extends('setup.layout')

@section('content')

{{-- ═══════ Step 1: Requirements ═══════ --}}
@if($step === 1)
<div class="card">
  <h2>🔍 ตรวจสอบระบบ</h2>
  <p class="sub">เช็คว่าเซิร์ฟเวอร์พร้อมติดตั้งหรือไม่</p>

  <table class="checks">
    @foreach($checks as $c)
      <tr>
        <td>{{ $c['name'] }}</td>
        <td>{{ $c['need'] }}</td>
        <td class="{{ isset($c['warn']) && $c['warn'] ? 'warn' : ($c['pass'] ? 'pass' : 'fail') }}">
          {{ $c['have'] }}
        </td>
      </tr>
    @endforeach
  </table>

  @if(!$allPassed)
    <div class="alert alert-bad" style="margin-top:16px">
      <b>⚠️ ยังไม่พร้อมติดตั้ง</b><br>
      กรุณาติดตั้งส่วนที่ขาดก่อน แล้วกดตรวจสอบใหม่
    </div>
  @else
    <div class="alert alert-ok" style="margin-top:16px">
      ✅ ระบบพร้อมติดตั้ง! กด "ถัดไป" เพื่อเริ่มตั้งค่า
    </div>
  @endif

  <div class="btns">
    <a href="/setup" class="btn btn-back">🔄 ตรวจสอบใหม่</a>
    @if($allPassed)
      <a href="/setup/database" class="btn btn-main">ถัดไป →</a>
    @endif
  </div>
</div>
@endif

{{-- ═══════ Step 2: Database ═══════ --}}
@if($step === 2)
<div class="card">
  <h2>🗄️ ตั้งค่าฐานข้อมูล</h2>
  <p class="sub">เลือกประเภทฐานข้อมูลที่ต้องการใช้</p>

  @if($errors->any())
    <div class="alert alert-bad">
      @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
  @endif

  <form method="POST" action="/setup/database">
    @csrf

    <div class="radio-cards">
      <label class="radio-card {{ $driver === 'sqlite' ? 'selected' : '' }}" onclick="selectDb('sqlite')">
        <input type="radio" name="driver" value="sqlite" {{ $driver === 'sqlite' ? 'checked' : '' }}>
        <div class="icon">📁</div>
        <b>SQLite</b>
        <span>ง่าย ไม่ต้องตั้งค่า<br>เหมาะสำหรับเริ่มต้น</span>
      </label>
      <label class="radio-card {{ $driver === 'mysql' ? 'selected' : '' }}" onclick="selectDb('mysql')">
        <input type="radio" name="driver" value="mysql" {{ $driver === 'mysql' ? 'checked' : '' }}>
        <div class="icon">🐬</div>
        <b>MySQL</b>
        <span>สำหรับ production<br>รองรับผู้ใช้จำนวนมาก</span>
      </label>
    </div>

    <div id="mysqlFields" style="{{ $driver === 'mysql' ? '' : 'display:none' }}">
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px">
        <div class="field">
          <label>Host</label>
          <input class="input" name="host" value="{{ old('host', $db['host']) }}" placeholder="127.0.0.1">
        </div>
        <div class="field">
          <label>Port</label>
          <input class="input" name="port" value="{{ old('port', $db['port']) }}" placeholder="3306">
        </div>
      </div>
      <div class="field">
        <label>Database Name</label>
        <input class="input" name="database" value="{{ old('database', $db['database']) }}" placeholder="roamembers">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="field">
          <label>Username</label>
          <input class="input" name="username" value="{{ old('username', $db['username']) }}">
        </div>
        <div class="field">
          <label>Password</label>
          <input class="input" type="password" name="password" value="{{ old('password', $db['password']) }}">
        </div>
      </div>
    </div>

    <div class="btns">
      <a href="/setup" class="btn btn-back">← ย้อนกลับ</a>
      <button type="submit" class="btn btn-main">ถัดไป →</button>
    </div>
  </form>
</div>

<script>
function selectDb(type) {
  document.getElementById('mysqlFields').style.display = type === 'mysql' ? '' : 'none';
  document.querySelectorAll('.radio-card').forEach(c => c.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
}
</script>
@endif

{{-- ═══════ Step 3: App Config ═══════ --}}
@if($step === 3)
<div class="card">
  <h2>⚙️ ตั้งค่าแอป</h2>
  <p class="sub">ข้อมูลพื้นฐานของระบบ</p>

  @if($errors->any())
    <div class="alert alert-bad">
      @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
  @endif

  <form method="POST" action="/setup/app">
    @csrf
    <div class="field">
      <label>ชื่อระบบ</label>
      <input class="input" name="name" value="{{ old('name', $app['name']) }}" placeholder="RaoMembers" required>
      <p class="hint">แสดงที่ header และ title ของทุกหน้า</p>
    </div>
    <div class="field">
      <label>URL ของเว็บ</label>
      <input class="input" name="url" type="url" value="{{ old('url', $app['url']) }}" required>
      <p class="hint">URL ที่ผู้ใช้เข้าถึง เช่น https://members.myshop.com</p>
    </div>
    <div class="field">
      <label>เขตเวลา</label>
      <select class="input" name="timezone">
        <option value="Asia/Bangkok" {{ $app['timezone'] === 'Asia/Bangkok' ? 'selected' : '' }}>Bangkok (GMT+7)</option>
        <option value="Asia/Singapore" {{ $app['timezone'] === 'Asia/Singapore' ? 'selected' : '' }}>Singapore (GMT+8)</option>
        <option value="UTC" {{ $app['timezone'] === 'UTC' ? 'selected' : '' }}>UTC</option>
      </select>
    </div>
    <div class="field">
      <label class="toggle">
        <input type="checkbox" name="debug" value="true" {{ $app['debug'] === 'true' ? 'checked' : '' }}>
        <span>โหมดดีบั๊ก (Debug Mode)</span>
      </label>
      <p class="hint">⚠️ เปิดเฉพาะตอนพัฒนา — ปิดเมื่อใช้งานจริง</p>
    </div>
    <div class="btns">
      <a href="/setup/database" class="btn btn-back">← ย้อนกลับ</a>
      <button type="submit" class="btn btn-main">ถัดไป →</button>
    </div>
  </form>
</div>
@endif

{{-- ═══════ Step 4: Admin Account ═══════ --}}
@if($step === 4)
<div class="card">
  <h2>👤 สร้างผู้ดูแลระบบ</h2>
  <p class="sub">บัญชี admin สำหรับจัดการระบบ</p>

  @if($errors->any())
    <div class="alert alert-bad">
      @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
  @endif

  <form method="POST" action="/setup/admin">
    @csrf
    <div class="field">
      <label>ชื่อ</label>
      <input class="input" name="name" value="{{ old('name', 'Admin') }}" required>
    </div>
    <div class="field">
      <label>อีเมล</label>
      <input class="input" name="email" type="email" value="{{ old('email') }}" placeholder="admin@myshop.com" required>
      <p class="hint">ใช้ล็อกอินเข้าระบบ</p>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="field">
        <label>รหัสผ่าน</label>
        <input class="input" name="password" type="password" required minlength="8">
      </div>
      <div class="field">
        <label>ยืนยันรหัสผ่าน</label>
        <input class="input" name="password_confirmation" type="password" required>
      </div>
    </div>
    <div class="btns">
      <a href="/setup/app" class="btn btn-back">← ย้อนกลับ</a>
      <button type="submit" class="btn btn-main">ถัดไป →</button>
    </div>
  </form>
</div>
@endif

{{-- ═══════ Step 5: Social Login ═══════ --}}
@if($step === 5)
<div class="card">
  <h2>🔐 Social Login <span style="font-size:12px;color:var(--muted)">(ไม่บังคับ)</span></h2>
  <p class="sub">ให้ลูกค้าเข้าสู่ระบบด้วย LINE หรือ Google — ข้ามได้ ตั้งค่าทีหลังที่หน้า Admin</p>

  <form method="POST" action="/setup/social">
    @csrf
    <h3 style="font-size:14px;margin-bottom:10px;color:var(--ok)">🟢 LINE Login</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px">
      <div class="field">
        <label>Channel ID</label>
        <input class="input" name="line_id" value="{{ $social['line_id'] }}">
      </div>
      <div class="field">
        <label>Channel Secret</label>
        <input class="input" name="line_secret" value="{{ $social['line_secret'] }}">
      </div>
    </div>

    <h3 style="font-size:14px;margin-bottom:10px;color:#4285F4">🔵 Google OAuth</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px">
      <div class="field">
        <label>Client ID</label>
        <input class="input" name="google_id" value="{{ $social['google_id'] }}">
      </div>
      <div class="field">
        <label>Client Secret</label>
        <input class="input" name="google_secret" value="{{ $social['google_secret'] }}">
      </div>
    </div>

    <div class="btns">
      <a href="/setup/admin" class="btn btn-back">← ย้อนกลับ</a>
      <button type="submit" class="btn btn-main">ข้าม / ถัดไป →</button>
    </div>
  </form>
</div>
@endif

{{-- ═══════ Step 6: Install ═══════ --}}
@if($step === 6)
<div class="card">
  <h2>🚀 ติดตั้งระบบ</h2>
  <p class="sub">กดปุ่มด้านล่างเพื่อเริ่มติดตั้ง — ใช้เวลาประมาณ 30 วินาที</p>

  <div id="installLog"></div>

  <div id="installDone" style="display:none" class="alert alert-ok">
    <b>✅ ติดตั้งเสร็จสมบูรณ์!</b><br>
    กำลังพาไปหน้าล็อกอิน...
  </div>

  <div id="installFailed" style="display:none" class="alert alert-bad">
    <b>❌ ติดตั้งไม่สำเร็จ</b><br>
    กรุณาตรวจสอบ log ด้านบน แล้วลองใหม่
  </div>

  <div class="btns">
    <a href="/setup/social" class="btn btn-back" id="backBtn">← ย้อนกลับ</a>
    <button type="button" class="btn btn-main" id="installBtn" onclick="runInstall()">
      🔧 เริ่มติดตั้ง
    </button>
  </div>
</div>

<script>
function runInstall() {
  var btn = document.getElementById('installBtn');
  var log = document.getElementById('installLog');
  var backBtn = document.getElementById('backBtn');

  btn.disabled = true;
  btn.textContent = '⏳ กำลังติดตั้ง...';
  log.style.display = 'block';
  log.innerHTML = '';
  backBtn.style.display = 'none';

  fetch((window.__baseUrl || '') + '/setup/install', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json'
    }
  })
  .then(r => r.json())
  .then(data => {
    data.results.forEach(r => {
      log.innerHTML += '<div class="' + (r.ok ? 'ok' : 'fail') + '">' + (r.ok ? '✓' : '✗') + ' ' + r.msg + '</div>';
    });
    log.scrollTop = log.scrollHeight;

    if (data.success) {
      document.getElementById('installDone').style.display = 'block';
      btn.textContent = '✅ เสร็จสิ้น!';
      setTimeout(function() {
        window.location.href = (window.__baseUrl || '') + '/login';
      }, 2000);
    } else {
      document.getElementById('installFailed').style.display = 'block';
      btn.disabled = false;
      btn.textContent = '🔄 ลองใหม่';
      backBtn.style.display = '';
    }
  })
  .catch(err => {
    log.innerHTML += '<div class="fail">✗ Network error: ' + err.message + '</div>';
    document.getElementById('installFailed').style.display = 'block';
    btn.disabled = false;
    btn.textContent = '🔄 ลองใหม่';
    backBtn.style.display = '';
  });
}
</script>
@endif

@endsection
