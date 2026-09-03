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
      <div style="text-align:right;font-size:12px;color:var(--muted)">
        {{ auth()->user()->node?->name }}
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
@stack('scripts')
</body>
</html>
