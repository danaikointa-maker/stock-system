<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', config('app.name', 'RaoMembers'))</title>
<link rel="icon" href="{{ brand_favicon() }}">
<link rel="apple-touch-icon" href="{{ brand_logo() }}">
<style>
:root{
  --bg:#f1f5f9; --card:#fff; --ink:#0f172a; --muted:#64748b; --line:#e2e8f0;
  --brand:#f97316; --brand-dark:#ea580c; --brand-light:#fdba74;
  --primary:#3b82f6; --primary-dark:#2563eb; --primary-light:#93c5fd;
  --ok:#10b981; --ok-dark:#059669; --ok-light:#a7f3d0;
  --warn:#f59e0b; --warn-dark:#d97706; --warn-light:#fde68a;
  --bad:#ef4444; --bad-dark:#dc2626; --bad-light:#fca5a5;
  --info:#6366f1; --info-dark:#4f46e5; --info-light:#c7d2fe;
  --sidebar-from:#0f172a; --sidebar-to:#1e293b;
  --radius:12px; --shadow:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06);
  --shadow-md:0 4px 6px rgba(0,0,0,.07),0 2px 4px rgba(0,0,0,.06);
  --shadow-lg:0 10px 25px rgba(0,0,0,.1),0 4px 10px rgba(0,0,0,.05);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Noto Sans Thai","Sarabun",-apple-system,"Segoe UI",sans-serif;
  background:var(--bg);color:var(--ink);font-size:14px;line-height:1.6}
a{color:var(--primary);text-decoration:none;transition:color .15s}
a:hover{color:var(--primary-dark)}
.layout{display:flex;min-height:100vh}

/* ═══ Sidebar — Dark Premium ═══ */
.sidebar{width:260px;background:linear-gradient(180deg,var(--sidebar-from) 0%,var(--sidebar-to) 100%);
  color:#94a3b8;flex-shrink:0;display:flex;flex-direction:column;
  box-shadow:4px 0 20px rgba(0,0,0,.15)}
.sidebar .brand{padding:22px 20px;border-bottom:1px solid rgba(255,255,255,.06);
  background:linear-gradient(135deg,rgba(249,115,22,.08) 0%,transparent 60%)}
.sidebar .brand b{color:#fff;font-size:16px;display:flex;align-items:center;gap:10px}
.sidebar .brand b img{width:32px;height:32px;object-fit:contain;background:#fff;border-radius:8px;padding:3px;
  box-shadow:0 2px 8px rgba(0,0,0,.2)}
.sidebar .brand span{font-size:11px;color:#64748b;display:block;margin-top:4px;margin-left:42px}
.sidebar nav{padding:8px 0;flex:1;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#334155 transparent}
.sidebar nav::-webkit-scrollbar{width:4px}
.sidebar nav::-webkit-scrollbar-thumb{background:#334155;border-radius:4px}
.sidebar .group{font-size:10px;letter-spacing:.1em;color:#475569;padding:18px 20px 6px;
  text-transform:uppercase;font-weight:700}
.sidebar a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:#94a3b8;font-size:13.5px;
  transition:all .2s ease;border-left:3px solid transparent;position:relative;overflow:hidden}
.sidebar a:hover{background:rgba(255,255,255,.05);color:#e2e8f0;border-left-color:rgba(249,115,22,.5)}
.sidebar a.on{background:linear-gradient(90deg,rgba(249,115,22,.15),transparent);
  color:#fff;font-weight:600;border-left-color:var(--brand)}
.sidebar .who{padding:16px 20px;border-top:1px solid rgba(255,255,255,.06);font-size:12px;
  background:rgba(0,0,0,.15)}
.sidebar .who b{color:#e2e8f0;display:block;font-size:13px}
.sidebar .who span{color:#64748b}

/* ═══ Main ═══ */
.main{flex:1;min-width:0;display:flex;flex-direction:column}
.topbar{background:#fff;border-bottom:1px solid var(--line);padding:14px 28px;
  display:flex;justify-content:space-between;align-items:center;gap:16px;
  box-shadow:0 1px 3px rgba(0,0,0,.04)}
.topbar h1{font-size:18px;font-weight:700;color:var(--ink)}
.topbar .crumb{font-size:12px;color:var(--muted);margin-top:2px}
.content{padding:24px 28px;flex:1}

/* ═══ Cards — Premium ═══ */
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);
  margin-bottom:20px;box-shadow:var(--shadow);transition:box-shadow .2s,transform .2s;overflow:hidden}
.card:hover{box-shadow:var(--shadow-md)}
.card>h3{padding:15px 18px;border-bottom:1px solid var(--line);font-size:14px;font-weight:700;
  display:flex;justify-content:space-between;align-items:center;gap:10px;
  background:linear-gradient(180deg,#fafbfc,#fff)}
.card>.body{padding:18px}
.grid{display:grid;gap:16px}
.g2{grid-template-columns:repeat(auto-fit,minmax(320px,1fr))}
.g4{grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}

/* ═══ KPI — Multi-color accents ═══ */
.kpi{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:18px 20px;
  box-shadow:var(--shadow);position:relative;overflow:hidden;transition:all .25s ease}
.kpi:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg)}
.kpi::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,var(--primary),var(--info))}
.kpi.warn::before{background:linear-gradient(90deg,var(--warn),#fb923c)}
.kpi.bad::before{background:linear-gradient(90deg,var(--bad),#f472b6)}
.kpi.ok::before{background:linear-gradient(90deg,var(--ok),#34d399)}
.kpi .lbl{font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.03em}
.kpi .val{font-size:26px;font-weight:800;margin-top:4px;letter-spacing:-.02em}
.kpi .sub{font-size:11.5px;color:var(--muted);margin-top:4px}
.kpi.warn .val{color:var(--warn-dark)} .kpi.bad .val{color:var(--bad-dark)} .kpi.ok .val{color:var(--ok-dark)}

/* ═══ Tables — Premium ═══ */
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;padding:12px 14px;background:linear-gradient(180deg,#f8fafc,#f1f5f9);
  border-bottom:2px solid var(--line);font-weight:700;color:#475569;font-size:11.5px;
  text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
td{padding:11px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
tbody tr{transition:background .15s}
tbody tr:hover{background:linear-gradient(90deg,#f8fafc,#eff6ff)}
.num{text-align:right;font-variant-numeric:tabular-nums}
.empty{padding:40px;text-align:center;color:var(--muted)}

/* ═══ Badges ═══ */
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.b-gray{background:#f1f5f9;color:#475569}
.b-blue{background:linear-gradient(135deg,#dbeafe,#eff6ff);color:#1d4ed8}
.b-green{background:linear-gradient(135deg,#dcfce7,#f0fdf4);color:#15803d}
.b-amber{background:linear-gradient(135deg,#fef3c7,#fffbeb);color:#b45309}
.b-red{background:linear-gradient(135deg,#fee2e2,#fef2f2);color:#dc2626}

/* ═══ Buttons — Premium + Ripple ═══ */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;
  padding:8px 16px;border-radius:8px;border:1px solid var(--line);
  background:#fff;cursor:pointer;font-size:13px;font-family:inherit;color:var(--ink);
  font-weight:600;position:relative;overflow:hidden;
  transition:all .2s cubic-bezier(.4,0,.2,1);box-shadow:0 1px 2px rgba(0,0,0,.05)}
.btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);background:#f8fafc}
.btn:active{transform:translateY(0);box-shadow:none}
.btn-p{background:linear-gradient(135deg,var(--brand),var(--brand-dark));border-color:var(--brand-dark);
  color:#fff;box-shadow:0 2px 8px rgba(249,115,22,.3)}
.btn-p:hover{background:linear-gradient(135deg,#fb923c,var(--brand));box-shadow:0 4px 14px rgba(249,115,22,.4)}
.btn-ok{background:linear-gradient(135deg,var(--ok),var(--ok-dark));border-color:var(--ok-dark);
  color:#fff;box-shadow:0 2px 8px rgba(16,185,129,.3)}
.btn-ok:hover{box-shadow:0 4px 14px rgba(16,185,129,.4)}
.btn-d{background:#fff;border-color:#fca5a5;color:var(--bad)}
.btn-d:hover{background:#fef2f2;border-color:var(--bad)}
.btn-blue{background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-color:var(--primary-dark);
  color:#fff;box-shadow:0 2px 8px rgba(59,130,246,.3)}
.btn-blue:hover{box-shadow:0 4px 14px rgba(59,130,246,.4)}
.btn-sm{padding:5px 10px;font-size:12px;border-radius:6px}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none!important;box-shadow:none!important}

/* Ripple effect */
.ripple{position:absolute;border-radius:50%;background:rgba(255,255,255,.35);
  transform:scale(0);animation:rippleAnim .6s ease-out;pointer-events:none}
@keyframes rippleAnim{to{transform:scale(4);opacity:0}}

/* ═══ Forms ═══ */
label{display:block;font-size:12.5px;font-weight:700;margin-bottom:5px;color:#475569}
input,select,textarea{width:100%;padding:9px 13px;border:1.5px solid #d1d5db;border-radius:8px;
  font-family:inherit;font-size:13.5px;background:#fff;color:var(--ink);
  transition:border-color .2s,box-shadow .2s}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.field{margin-bottom:16px}
.err{color:var(--bad);font-size:12px;margin-top:4px;font-weight:600}
.filters{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.filters .field{margin:0;min-width:140px}

/* ═══ Alerts — Animated ═══ */
.alert{padding:13px 17px;border-radius:var(--radius);margin-bottom:18px;font-size:13.5px;
  display:flex;align-items:flex-start;gap:10px;animation:alertIn .4s cubic-bezier(.16,1,.3,1);
  border-left:4px solid}
@keyframes alertIn{from{opacity:0;transform:translateY(-10px) scale(.98)}to{opacity:1;transform:none}}
.a-ok{background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#14532d;border-color:var(--ok)}
.a-bad{background:linear-gradient(135deg,#fef2f2,#fee2e2);color:#7f1d1d;border-color:var(--bad)}
.a-info{background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#1e3a5f;border-color:var(--primary)}
.alert-icon{font-size:18px;flex-shrink:0;margin-top:1px}

/* ═══ Toast notifications ═══ */
.toast-container{position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:10px;
  pointer-events:none;max-width:380px}
.toast{pointer-events:auto;display:flex;align-items:flex-start;gap:12px;padding:14px 18px;
  border-radius:12px;background:#fff;box-shadow:0 10px 40px rgba(0,0,0,.15),0 2px 8px rgba(0,0,0,.1);
  border-left:4px solid var(--primary);animation:toastIn .4s cubic-bezier(.16,1,.3,1);
  position:relative;overflow:hidden;min-width:300px}
.toast.out{animation:toastOut .3s ease-in forwards}
@keyframes toastIn{from{opacity:0;transform:translateX(100%) scale(.9)}to{opacity:1;transform:none}}
@keyframes toastOut{to{opacity:0;transform:translateX(100%) scale(.9)}}
.toast-icon{font-size:20px;flex-shrink:0}
.toast-body{flex:1;min-width:0}
.toast-title{font-weight:700;font-size:13px;margin-bottom:2px}
.toast-msg{font-size:12.5px;color:var(--muted);line-height:1.5}
.toast-close{background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;padding:2px;
  transition:color .15s}
.toast-close:hover{color:var(--ink)}
.toast-progress{position:absolute;bottom:0;left:0;height:3px;border-radius:0 0 0 12px;
  animation:toastProgress 5s linear forwards}
@keyframes toastProgress{from{width:100%}to{width:0}}
.toast.success{border-left-color:var(--ok)}
.toast.success .toast-progress{background:var(--ok)}
.toast.error{border-left-color:var(--bad)}
.toast.error .toast-progress{background:var(--bad)}
.toast.warning{border-left-color:var(--warn)}
.toast.warning .toast-progress{background:var(--warn)}
.toast.info{border-left-color:var(--info)}
.toast.info .toast-progress{background:var(--info)}

/* ═══ Misc ═══ */
.bar{height:7px;background:#f1f5f9;border-radius:5px;overflow:hidden;min-width:70px}
.bar>i{display:block;height:100%;border-radius:5px;
  background:linear-gradient(90deg,var(--primary),var(--info))}
.tree-in{display:inline-block;color:#94a3b8}
code{background:#f1f5f9;padding:2px 7px;border-radius:5px;font-size:12px;color:#7c3aed}
.spark{display:flex;align-items:flex-end;gap:3px;height:64px}
.spark>i{flex:1;max-width:34px;background:linear-gradient(180deg,var(--brand),var(--brand-dark));
  border-radius:3px 3px 0 0;min-height:2px;opacity:.85;transition:opacity .15s}
.spark>i:hover{opacity:1}
.pager{display:flex;gap:5px;padding:12px 16px;flex-wrap:wrap}
.pager a,.pager span{padding:6px 11px;border:1px solid var(--line);border-radius:7px;font-size:12.5px;
  transition:all .15s}
.pager a:hover{border-color:var(--primary);color:var(--primary);background:#eff6ff}
.pager .on{background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700}
/* ═══ Action buttons by role ═══ */
.btn-create{background:linear-gradient(135deg,#10b981,#059669);border-color:#059669;color:#fff;box-shadow:0 2px 8px rgba(16,185,129,.3)}
.btn-create:hover{box-shadow:0 4px 14px rgba(16,185,129,.4)}
.btn-edit{background:linear-gradient(135deg,#3b82f6,#2563eb);border-color:#2563eb;color:#fff;box-shadow:0 2px 8px rgba(59,130,246,.3)}
.btn-edit:hover{box-shadow:0 4px 14px rgba(59,130,246,.4)}
.btn-view{background:linear-gradient(135deg,#8b5cf6,#7c3aed);border-color:#7c3aed;color:#fff;box-shadow:0 2px 8px rgba(139,92,246,.3)}
.btn-view:hover{box-shadow:0 4px 14px rgba(139,92,246,.4)}
.btn-approve{background:linear-gradient(135deg,#f59e0b,#d97706);border-color:#d97706;color:#fff;box-shadow:0 2px 8px rgba(245,158,11,.3)}
.btn-approve:hover{box-shadow:0 4px 14px rgba(245,158,11,.4)}
.btn-ship{background:linear-gradient(135deg,#6366f1,#4f46e5);border-color:#4f46e5;color:#fff;box-shadow:0 2px 8px rgba(99,102,241,.3)}
.btn-ship:hover{box-shadow:0 4px 14px rgba(99,102,241,.4)}
.btn-receive{background:linear-gradient(135deg,#06b6d4,#0891b2);border-color:#0891b2;color:#fff;box-shadow:0 2px 8px rgba(6,182,212,.3)}
.btn-receive:hover{box-shadow:0 4px 14px rgba(6,182,212,.4)}

/* ═══ Section title bars — color-coded ═══ */
.section-bar{padding:14px 18px;border-radius:var(--radius) var(--radius) 0 0;font-weight:700;font-size:14px;
  display:flex;align-items:center;gap:8px;color:#fff}
.section-bar.sales{background:linear-gradient(135deg,#10b981,#059669)}
.section-bar.stock{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.section-bar.transfer{background:linear-gradient(135deg,#f59e0b,#d97706)}
.section-bar.alert{background:linear-gradient(135deg,#ef4444,#dc2626)}
.section-bar.team{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.section-bar.report{background:linear-gradient(135deg,#6366f1,#4f46e5)}
.section-bar.qr{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.section-bar.shop{background:linear-gradient(135deg,#ec4899,#db2777)}

/* Chart container */
.chart-wrap{position:relative;width:100%;max-height:300px}
.chart-wrap canvas{width:100%!important;max-height:300px}

@media(max-width:860px){.sidebar{display:none}.content{padding:16px}}
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
@stack('head')
</head>
<body>
<div class="layout">
  @include('partials.sidebar')

  <div class="main">
    <div class="topbar">
      <div>
        <h1>@yield('title', config('app.name', 'RaoMembers'))</h1>
        <div class="crumb">@yield('crumb')</div>
      </div>
      <div style="display:flex;align-items:center;gap:12px">
        <a href="{{ route('workflow') }}" class="btn btn-sm btn-blue" title="คู่มือขั้นตอนการทำงาน">
          📋 ขั้นตอนการทำงาน
        </a>
        <button type="button" id="helpBtn" class="btn btn-sm" style="color:var(--muted);border-color:var(--line)" title="ดูคู่มือการใช้งาน">
          📖 คู่มือ
        </button>
        <span style="font-size:12px;color:var(--muted)">{{ auth()->user()->node?->name }}</span>
        <span class="badge b-blue">{{ auth()->user()->role->label() }}</span>
      </div>
    </div>

    <div class="content">
      @if(session('status'))
        <div class="alert a-ok"><span class="alert-icon">✅</span><div>{{ session('status') }}</div></div>
      @endif
      @if(session('temp_password'))
        <div class="alert a-info"><span class="alert-icon">🔑</span>
          <div>รหัสผ่านชั่วคราว: <code>{{ session('temp_password') }}</code> — กรุณาคัดลอกและแจ้งผู้ใช้ทันที ระบบจะไม่แสดงอีก</div>
        </div>
      @endif
      @if($errors->any())
        <div class="alert a-bad"><span class="alert-icon">❌</span>
          <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        </div>
      @endif

      @yield('content')
    </div>
  </div>
</div>

{{-- Toast container --}}
<div class="toast-container" id="toastContainer"></div>

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
.help-overlay{position:fixed;inset:0;z-index:9999;display:flex;justify-content:center;align-items:flex-start;
  padding:30px 16px;overflow-y:auto;background:rgba(10,12,8,0);backdrop-filter:blur(0px);
  pointer-events:none;transition:background .35s ease,backdrop-filter .35s ease}
.help-overlay.show{background:rgba(10,12,8,.55);backdrop-filter:blur(8px);pointer-events:auto}
.help-overlay.closing{background:rgba(10,12,8,0);backdrop-filter:blur(0px)}
.help-dialog{background:#fff;border-radius:20px;max-width:720px;width:100%;margin:20px auto;
  box-shadow:0 25px 80px rgba(0,0,0,.25);opacity:0;transform:translateY(30px) scale(.96);
  transition:opacity .4s cubic-bezier(.16,1,.3,1),transform .4s cubic-bezier(.16,1,.3,1)}
.help-overlay.show .help-dialog{opacity:1;transform:none}
.help-overlay.closing .help-dialog{opacity:0;transform:translateY(16px) scale(.97);transition-duration:.25s}
.help-header{display:flex;justify-content:space-between;align-items:center;padding:22px 26px 18px;
  border-bottom:1px solid var(--line);background:linear-gradient(180deg,#fafbfc,#fff);border-radius:20px 20px 0 0}
.help-header h2{font-size:17px;font-weight:800}
.help-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);
  padding:6px 10px;border-radius:8px;transition:all .15s}
.help-close:hover{background:#f1f5f9;color:var(--ink)}
.help-body{padding:26px;font-size:14px;line-height:1.85;max-height:62vh;overflow-y:auto;scroll-behavior:smooth}
.help-footer{padding:16px 26px;border-top:1px solid var(--line);background:#fafbfc;border-radius:0 0 20px 20px}
#helpNavBtns{display:flex;gap:6px;flex-wrap:wrap;justify-content:center}
#helpNavBtns .btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-md)}
#helpContent h3{font-size:15px;margin:22px 0 8px;color:var(--brand);display:flex;align-items:center;gap:6px}
#helpContent h3:first-child{margin-top:0}
#helpContent h4{font-size:13.5px;margin:16px 0 6px;font-weight:700}
#helpContent ul,#helpContent ol{margin:8px 0 12px 20px}
#helpContent li{margin-bottom:5px}
#helpContent b{color:var(--ink)}
#helpContent code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px}
#helpContent p{margin-bottom:8px}
#helpContent .tip-box{background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:13px 16px;margin:12px 0;font-size:13px;line-height:1.7}
#helpContent .tip-box b{color:#15803d}
#helpContent .warn-box{background:#fefce8;border:1px solid #fde047;border-radius:10px;padding:13px 16px;margin:12px 0;font-size:13px;line-height:1.7}
#helpContent .warn-box b{color:#a16207}
#helpContent .info-box{background:#eff6ff;border:1px solid #93c5fd;border-radius:10px;padding:13px 16px;margin:12px 0;font-size:13px;line-height:1.7}
#helpContent .info-box b{color:#1e40af}
#helpContent{transition:opacity .2s ease}
#helpContent.fading{opacity:.3}
</style>

<script>
/* ═══ Base path ═══ */
var B = (function(){
  var sn = '{{ $_SERVER["SCRIPT_NAME"] ?? "" }}';
  var m = sn.match(/^(.+)\/public\/index\.php$/);
  if (m) return m[1].replace(/\/+$/, '');
  m = sn.match(/^(.+)\/index\.php$/);
  if (m) { var b = m[1].replace(/\/+$/, ''); return (b && b !== '/public') ? b : ''; }
  return '';
})();

/* ═══ Ripple Effect on Buttons ═══ */
document.addEventListener('click', function(e) {
  var btn = e.target.closest('.btn');
  if (!btn) return;
  var rect = btn.getBoundingClientRect();
  var ripple = document.createElement('span');
  ripple.className = 'ripple';
  var size = Math.max(rect.width, rect.height);
  ripple.style.width = ripple.style.height = size + 'px';
  ripple.style.left = (e.clientX - rect.left - size/2) + 'px';
  ripple.style.top = (e.clientY - rect.top - size/2) + 'px';
  btn.appendChild(ripple);
  setTimeout(function(){ ripple.remove(); }, 600);
});

/* ═══ Toast System ═══ */
var toastContainer = document.getElementById('toastContainer');
window.showToast = function(type, title, msg, duration) {
  duration = duration || 5000;
  var icons = {success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};
  var toast = document.createElement('div');
  toast.className = 'toast ' + (type||'info');
  toast.innerHTML = '<span class="toast-icon">'+(icons[type]||'ℹ️')+'</span>'
    +'<div class="toast-body"><div class="toast-title">'+title+'</div>'
    +(msg?'<div class="toast-msg">'+msg+'</div>':'')+'</div>'
    +'<button class="toast-close" onclick="this.parentElement.classList.add(\'out\');setTimeout(function(){this.remove()}.bind(this.parentElement),300)">✕</button>'
    +'<div class="toast-progress" style="animation-duration:'+(duration/1000)+'s"></div>';
  toastContainer.appendChild(toast);
  setTimeout(function(){
    toast.classList.add('out');
    setTimeout(function(){ toast.remove(); }, 300);
  }, duration);
};

/* Show session alerts as toasts */
@if(session('toast_success'))
  showToast('success', 'สำเร็จ', '{{ session('toast_success') }}');
@endif
@if(session('toast_error'))
  showToast('error', 'ผิดพลาด', '{{ session('toast_error') }}');
@endif
@if(session('toast_warning'))
  showToast('warning', 'แจ้งเตือน', '{{ session('toast_warning') }}');
@endif

/* ═══ Help Modal ═══ */
(function(){
  var modal = document.getElementById('helpModal');
  var dialog = modal.querySelector('.help-dialog');
  var helpTitle = document.getElementById('helpTitle');
  var helpContent = document.getElementById('helpContent');
  var helpNavBtns = document.getElementById('helpNavBtns');
  var helpBtn = document.getElementById('helpBtn');
  var helpClose = document.getElementById('helpClose');
  var currentPage = '', cachedPages = [], isClosing = false;

  function loadHelp(page) {
    helpContent.classList.add('fading');
    setTimeout(function(){
      var url = B + '/help?url=' + encodeURIComponent(window.location.pathname);
      if (page) url += '&page=' + encodeURIComponent(page);
      fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function(r){ return r.json(); })
        .then(function(data){
          helpTitle.textContent = data.title || '📖 คู่มือ';
          helpContent.innerHTML = data.content || '<p>ไม่มีเนื้อหา</p>';
          helpContent.scrollTop = 0;
          currentPage = data.current || '';
          cachedPages = data.pages || [];
          renderNav();
          helpContent.classList.remove('fading');
        })
        .catch(function(){
          helpContent.innerHTML = '<p style="color:var(--bad)">โหลดคู่มือไม่สำเร็จ</p>';
          helpContent.classList.remove('fading');
        });
    }, 200);
  }

  function renderNav() {
    helpNavBtns.innerHTML = '';
    cachedPages.forEach(function(p){
      var btn = document.createElement('button');
      btn.className = 'btn btn-sm';
      if (p.key === currentPage) {
        btn.style.background = 'linear-gradient(135deg,var(--brand),var(--brand-dark))';
        btn.style.color = '#fff'; btn.style.borderColor = 'var(--brand-dark)';
      }
      btn.textContent = (p.icon||'')+' '+(p.label||p.key);
      btn.addEventListener('click', function(){ loadHelp(p.key); });
      helpNavBtns.appendChild(btn);
    });
  }

  function openHelp() {
    if (isClosing) return;
    modal.setAttribute('aria-hidden','false');
    modal.classList.remove('closing');
    modal.classList.add('show');
    loadHelp();
  }
  function closeHelp() {
    if (isClosing || !modal.classList.contains('show')) return;
    isClosing = true;
    modal.classList.add('closing');
    dialog.addEventListener('transitionend', function h(e){
      if (e.propertyName !== 'opacity') return;
      dialog.removeEventListener('transitionend', h);
      modal.classList.remove('show','closing');
      modal.setAttribute('aria-hidden','true');
      isClosing = false;
    });
    setTimeout(function(){
      if(isClosing){ modal.classList.remove('show','closing');
        modal.setAttribute('aria-hidden','true'); isClosing=false; }
    }, 400);
  }

  helpBtn.addEventListener('click', openHelp);
  helpClose.addEventListener('click', closeHelp);
  modal.addEventListener('click', function(e){ if(e.target===modal) closeHelp(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeHelp(); });
})();
</script>

@stack('scripts')
</body>
</html>
