<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'RoaMembers')</title>
<style>
:root{
  /* สีแบรนด์ RoaMembers — ดึงจากโลโก้จริง (ส้ม #F04800 / เขียว #006018 / ทอง) */
  --bg:#F6F5F0; --card:#fff; --ink:#1A1A14; --muted:#6E6E63; --line:#E6E4DA;
  --brand:#F04800; --brand-dark:#C23800; --ok:#006018; --warn:#C77700; --bad:#C62828;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Noto Sans Thai","Sarabun",-apple-system,"Segoe UI",sans-serif;
  background:var(--bg);color:var(--ink);font-size:14px;line-height:1.6}
a{color:var(--brand);text-decoration:none}
.layout{display:flex;min-height:100vh}

/* Sidebar */
.sidebar{width:250px;background:#14261A;color:#CFD8CF;flex-shrink:0;display:flex;flex-direction:column}
.sidebar .brand{padding:20px 18px;border-bottom:1px solid #24402C}
.sidebar .brand b{color:#fff;font-size:16px;display:block}
.sidebar .brand span{font-size:11px;color:#8FA894}
.sidebar nav{padding:10px 0;flex:1;overflow-y:auto}
.sidebar .group{font-size:10px;letter-spacing:.08em;color:#7A9180;padding:14px 18px 6px;text-transform:uppercase}
.sidebar a{display:flex;align-items:center;gap:10px;padding:9px 18px;color:#CFD8CF;font-size:13.5px}
.sidebar a:hover{background:#1E3826;color:#fff}
.sidebar a.on{background:var(--brand);color:#fff;font-weight:600}
.sidebar .who{padding:14px 18px;border-top:1px solid #24344f;font-size:12px}
.sidebar .who b{color:#fff;display:block;font-size:13px}
.sidebar .who span{color:#7d8db0}

/* Main */
.main{flex:1;min-width:0;display:flex;flex-direction:column}
.topbar{background:#fff;border-bottom:1px solid var(--line);padding:12px 24px;
  display:flex;justify-content:space-between;align-items:center;gap:16px}
.topbar h1{font-size:17px;font-weight:700}
.topbar .crumb{font-size:12px;color:var(--muted)}
.content{padding:22px 24px;flex:1}

/* Components */
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;margin-bottom:18px}
.card>h3{padding:13px 16px;border-bottom:1px solid var(--line);font-size:14px;
  display:flex;justify-content:space-between;align-items:center;gap:10px}
.card>.body{padding:16px}
.grid{display:grid;gap:14px}
.g2{grid-template-columns:repeat(auto-fit,minmax(320px,1fr))}
.g4{grid-template-columns:repeat(auto-fit,minmax(190px,1fr))}

.kpi{background:#fff;border:1px solid var(--line);border-radius:10px;padding:14px 16px}
.kpi .lbl{font-size:12px;color:var(--muted)}
.kpi .val{font-size:23px;font-weight:700;margin-top:3px;letter-spacing:-.02em}
.kpi .sub{font-size:11.5px;color:var(--muted);margin-top:2px}
.kpi.warn .val{color:var(--warn)} .kpi.bad .val{color:var(--bad)} .kpi.ok .val{color:var(--ok)}

table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;padding:9px 12px;background:#f7f9fc;border-bottom:1px solid var(--line);
  font-weight:600;color:#42506b;font-size:12px;white-space:nowrap}
td{padding:9px 12px;border-bottom:1px solid #eef1f6;vertical-align:middle}
tbody tr:hover{background:#fafbfe}
.num{text-align:right;font-variant-numeric:tabular-nums}
.empty{padding:34px;text-align:center;color:var(--muted)}

.badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}
.b-gray{background:#eef1f6;color:#54607a}
.b-blue{background:#e3ecfd;color:#1e4499}
.b-green{background:#dcfce7;color:#15803d}
.b-amber{background:#fef3c7;color:#a16207}
.b-red{background:#fee2e2;color:#b91c1c}

.btn{display:inline-block;padding:7px 14px;border-radius:7px;border:1px solid var(--line);
  background:#fff;cursor:pointer;font-size:13px;font-family:inherit;color:var(--ink)}
.btn:hover{background:#f5f7fb}
.btn-p{background:var(--brand);border-color:var(--brand);color:#fff}
.btn-p:hover{background:var(--brand-dark)}
.btn-d{background:#fff;border-color:#f3b8b8;color:var(--bad)}
.btn-d:hover{background:#fef2f2}
.btn-sm{padding:4px 9px;font-size:12px}

label{display:block;font-size:12.5px;font-weight:600;margin-bottom:4px;color:#42506b}
input,select,textarea{width:100%;padding:8px 11px;border:1px solid #d5dce8;border-radius:7px;
  font-family:inherit;font-size:13.5px;background:#fff;color:var(--ink)}
input:focus,select:focus,textarea:focus{outline:2px solid #bfd2fa;border-color:var(--brand)}
.field{margin-bottom:14px}
.err{color:var(--bad);font-size:12px;margin-top:3px}
.filters{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.filters .field{margin:0;min-width:140px}

.alert{padding:11px 15px;border-radius:8px;margin-bottom:16px;font-size:13.5px}
.a-ok{background:#dcfce7;color:#14532d;border:1px solid #86efac}
.a-bad{background:#fee2e2;color:#7f1d1d;border:1px solid #fca5a5}
.a-info{background:#e0f2fe;color:#075985;border:1px solid #7dd3fc}

.bar{height:7px;background:#eef1f6;border-radius:5px;overflow:hidden;min-width:70px}
.bar>i{display:block;height:100%;background:var(--brand);border-radius:5px}
.tree-in{display:inline-block;color:#adb7c9}
code{background:#f1f4f9;padding:2px 6px;border-radius:4px;font-size:12px}
.spark{display:flex;align-items:flex-end;gap:3px;height:64px}
.spark>i{flex:1;max-width:34px;background:var(--brand);border-radius:2px 2px 0 0;min-height:2px;opacity:.85}
.spark>i:hover{opacity:1}
.pager{display:flex;gap:5px;padding:12px 16px;flex-wrap:wrap}
.pager a,.pager span{padding:5px 10px;border:1px solid var(--line);border-radius:6px;font-size:12.5px}
.pager .on{background:var(--brand);color:#fff;border-color:var(--brand)}
@media(max-width:860px){.sidebar{display:none}.content{padding:14px}}
</style>
@stack('head')
</head>
<body>
<div class="layout">
  @include('partials.sidebar')

  <div class="main">
    <div class="topbar">
      <div>
        <h1>@yield('title', 'RoaMembers')</h1>
        <div class="crumb">@yield('crumb')</div>
      </div>
      <div style="text-align:right;font-size:12px;color:var(--muted);display:flex;align-items:center;gap:10px">
        <button type="button" id="helpBtn" style="background:none;border:1px solid var(--line);border-radius:7px;padding:5px 12px;font-size:12px;cursor:pointer;font-family:inherit;color:var(--muted)" title="ดูคู่มือการใช้งาน">
          📖 คู่มือ
        </button>
        <span>{{ auth()->user()->node?->name }}</span>
        <span class="badge b-blue">{{ auth()->user()->role->label() }}</span>
      </div>
    </div>

    <div class="content">
      @if(session('status'))
        <div class="alert a-ok">{{ session('status') }}</div>
      @endif
      @if(session('temp_password'))
        <div class="alert a-info">
          รหัสผ่านชั่วคราว: <code>{{ session('temp_password') }}</code>
          — กรุณาคัดลอกและแจ้งผู้ใช้ทันที ระบบจะไม่แสดงอีก
        </div>
      @endif
      @if($errors->any())
        <div class="alert a-bad">
          @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
      @endif

      @yield('content')
    </div>
  </div>
</div>
{{-- ═══ Help Modal ═══ --}}
<div id="helpModal" class="help-overlay" aria-hidden="true">
  <div class="help-dialog">
    <div class="help-header">
      <h2 id="helpTitle">📖 คู่มือการใช้งาน</h2>
      <button type="button" id="helpClose" class="help-close" aria-label="ปิด">✕</button>
    </div>
    <div id="helpContent" class="help-body">
      <p style="text-align:center;color:var(--muted)">กำลังโหลด...</p>
    </div>
    <div class="help-footer">
      <div id="helpNavBtns"></div>
    </div>
  </div>
</div>
<style>
/* ── Help Modal Animations ── */
.help-overlay{
  position:fixed;inset:0;z-index:9999;
  display:flex;justify-content:center;align-items:flex-start;
  padding:30px 16px;overflow-y:auto;
  background:rgba(10,12,8,0);
  backdrop-filter:blur(0px);
  pointer-events:none;
  transition:background .35s ease,backdrop-filter .35s ease;
}
.help-overlay.show{
  background:rgba(10,12,8,.55);
  backdrop-filter:blur(6px);
  pointer-events:auto;
}
.help-overlay.closing{
  background:rgba(10,12,8,0);
  backdrop-filter:blur(0px);
}

.help-dialog{
  background:#fff;border-radius:18px;max-width:720px;width:100%;
  margin:20px auto;
  box-shadow:0 25px 80px rgba(0,0,0,.25),0 8px 20px rgba(0,0,0,.1);
  opacity:0;
  transform:translateY(24px) scale(.97);
  transition:opacity .35s cubic-bezier(.16,1,.3,1),transform .35s cubic-bezier(.16,1,.3,1);
}
.help-overlay.show .help-dialog{
  opacity:1;
  transform:translateY(0) scale(1);
}
.help-overlay.closing .help-dialog{
  opacity:0;
  transform:translateY(16px) scale(.97);
  transition:opacity .25s ease-in,transform .25s ease-in;
}

.help-header{
  display:flex;justify-content:space-between;align-items:center;
  padding:20px 24px 16px;
  border-bottom:1px solid var(--line);
}
.help-header h2{font-size:17px;margin:0;font-weight:700}
.help-close{
  background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);
  padding:4px 10px;border-radius:8px;transition:background .15s,color .15s;
}
.help-close:hover{background:#f0f0ec;color:var(--ink)}

.help-body{
  padding:24px;font-size:14px;line-height:1.85;max-height:65vh;overflow-y:auto;
  scroll-behavior:smooth;
}
.help-footer{
  padding:14px 24px;border-top:1px solid var(--line);
}
#helpNavBtns{display:flex;gap:6px;flex-wrap:wrap;justify-content:center}
#helpNavBtns .btn{transition:all .15s ease}
#helpNavBtns .btn:hover{transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,.1)}

/* ── Help Content Typography ── */
#helpContent h3{font-size:15px;margin:20px 0 8px;color:var(--brand);display:flex;align-items:center;gap:6px}
#helpContent h3:first-child{margin-top:0}
#helpContent h4{font-size:13.5px;margin:14px 0 6px;color:var(--ink);font-weight:600}
#helpContent ul,#helpContent ol{margin:8px 0 12px 20px}
#helpContent li{margin-bottom:5px}
#helpContent b{color:var(--ink)}
#helpContent code{background:#f1f4f9;padding:2px 6px;border-radius:4px;font-size:12px}
#helpContent p{margin-bottom:8px}

/* Tip / Warning boxes inside help */
#helpContent .tip-box{
  background:#f0fdf4;border:1px solid #86efac;border-radius:10px;
  padding:12px 16px;margin:12px 0;font-size:13px;line-height:1.7;
}
#helpContent .tip-box b{color:#15803d}
#helpContent .warn-box{
  background:#fefce8;border:1px solid #fde047;border-radius:10px;
  padding:12px 16px;margin:12px 0;font-size:13px;line-height:1.7;
}
#helpContent .warn-box b{color:#a16207}
#helpContent .info-box{
  background:#eff6ff;border:1px solid #93c5fd;border-radius:10px;
  padding:12px 16px;margin:12px 0;font-size:13px;line-height:1.7;
}
#helpContent .info-box b{color:#1e40af}

/* Content fade transition */
#helpContent{transition:opacity .2s ease}
#helpContent.fading{opacity:.3}
</style>

<script>
(function(){
  var B = (function(){
    var sn = '{{ $_SERVER["SCRIPT_NAME"] ?? "" }}';
    var m = sn.match(/^(.+)\/public\/index\.php$/);
    if (m) return m[1].replace(/\/+$/, '');
    m = sn.match(/^(.+)\/index\.php$/);
    if (m) { var b = m[1].replace(/\/+$/, ''); return (b && b !== '/public') ? b : ''; }
    return '';
  })();

  var modal = document.getElementById('helpModal');
  var dialog = modal.querySelector('.help-dialog');
  var helpTitle = document.getElementById('helpTitle');
  var helpContent = document.getElementById('helpContent');
  var helpNavBtns = document.getElementById('helpNavBtns');
  var helpBtn = document.getElementById('helpBtn');
  var helpClose = document.getElementById('helpClose');
  var currentPage = '';
  var cachedPages = [];
  var isClosing = false;

  function loadHelp(page) {
    // Fade content out → load → fade in
    helpContent.classList.add('fading');
    setTimeout(function(){
      var url = B + '/help?url=' + encodeURIComponent(window.location.pathname);
      if (page) url += '&page=' + encodeURIComponent(page);
      fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          helpTitle.textContent = data.title || '📖 คู่มือ';
          helpContent.innerHTML = data.content || '<p>ไม่มีเนื้อหา</p>';
          helpContent.scrollTop = 0;
          currentPage = data.current || '';
          cachedPages = data.pages || [];
          renderNav();
          helpContent.classList.remove('fading');
        })
        .catch(function() {
          helpContent.innerHTML = '<p style="color:var(--bad)">โหลดคู่มือไม่สำเร็จ กรุณาลองใหม่</p>';
          helpContent.classList.remove('fading');
        });
    }, 200);
  }

  function renderNav() {
    helpNavBtns.innerHTML = '';
    cachedPages.forEach(function(p) {
      var btn = document.createElement('button');
      btn.className = 'btn btn-sm';
      if (p.key === currentPage) {
        btn.style.background = 'var(--brand)';
        btn.style.color = '#fff';
        btn.style.borderColor = 'var(--brand)';
      }
      btn.textContent = (p.icon || '') + ' ' + (p.label || p.key);
      btn.addEventListener('click', function() { loadHelp(p.key); });
      helpNavBtns.appendChild(btn);
    });
  }

  function openHelp() {
    if (isClosing) return;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.remove('closing');
    modal.classList.add('show');
    loadHelp();
  }

  function closeHelp() {
    if (isClosing || !modal.classList.contains('show')) return;
    isClosing = true;
    modal.classList.add('closing');
    // Wait for animation to finish
    dialog.addEventListener('transitionend', function handler(e) {
      if (e.propertyName !== 'opacity') return;
      dialog.removeEventListener('transitionend', handler);
      modal.classList.remove('show', 'closing');
      modal.setAttribute('aria-hidden', 'true');
      isClosing = false;
    });
    // Fallback in case transitionend doesn't fire
    setTimeout(function() {
      if (isClosing) {
        modal.classList.remove('show', 'closing');
        modal.setAttribute('aria-hidden', 'true');
        isClosing = false;
      }
    }, 400);
  }

  helpBtn.addEventListener('click', openHelp);
  helpClose.addEventListener('click', closeHelp);
  modal.addEventListener('click', function(e) { if (e.target === modal) closeHelp(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeHelp(); });
})();
</script>

@stack('scripts')
</body>
</html>
