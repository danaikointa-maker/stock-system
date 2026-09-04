<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ติดตั้งระบบ · RoaMembers</title>
<style>
:root{--brand:#F04800;--brand-dark:#C23800;--ok:#006018;--warn:#C77700;--bad:#C62828;--bg:#F6F5F0;--card:#fff;--ink:#1A1A14;--muted:#6E6E63;--line:#E6E4DA}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Noto Sans Thai","Sarabun",-apple-system,sans-serif;background:var(--bg);color:var(--ink);font-size:14px;line-height:1.6;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:20px}
.wizard{max-width:720px;width:100%;margin:20px auto}
.logo{text-align:center;margin-bottom:24px}
.logo img{width:60px;height:60px;border-radius:14px;margin-bottom:8px}
.logo b{font-size:20px;display:block;color:var(--ink)}
.logo span{font-size:12px;color:var(--muted)}

/* Progress */
.progress{display:flex;gap:4px;margin-bottom:24px}
.progress .step{flex:1;height:6px;border-radius:3px;background:var(--line)}
.progress .step.done{background:var(--ok)}
.progress .step.active{background:var(--brand)}

/* Card */
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:28px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.card h2{font-size:18px;margin-bottom:4px}
.card .sub{font-size:13px;color:var(--muted);margin-bottom:20px}

/* Form */
.field{margin-bottom:16px}
.field label{font-weight:600;font-size:13px;margin-bottom:4px;display:block}
.field .hint{font-size:12px;color:var(--muted);margin-top:2px}
.field .err{font-size:12px;color:var(--bad);margin-top:2px}
.input{width:100%;padding:10px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fff;transition:border .2s}
.input:focus{border-color:var(--brand);outline:none}
.input.err{border-color:var(--bad)}
select.input{appearance:none;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236E6E63' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") right 12px center no-repeat #fff}

/* Toggle */
.toggle{display:flex;align-items:center;gap:10px}
.toggle input{width:42px;height:24px;appearance:none;background:var(--line);border-radius:12px;position:relative;cursor:pointer;transition:.2s}
.toggle input:checked{background:var(--ok)}
.toggle input::after{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;top:3px;left:3px;transition:.2s}
.toggle input:checked::after{left:21px}

/* Buttons */
.btns{display:flex;gap:10px;margin-top:20px}
.btn{padding:12px 24px;border-radius:12px;border:none;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:.15s}
.btn-main{background:var(--brand);color:#fff;flex:1}
.btn-main:hover{background:var(--brand-dark)}
.btn-back{background:var(--line);color:var(--ink)}
.btn-back:hover{background:#d5d3ca}
.btn:disabled{opacity:.5;cursor:not-allowed}

/* Checks table */
.checks{width:100%;border-collapse:collapse;font-size:13px}
.checks td{padding:8px 10px;border-bottom:1px solid var(--line)}
.checks .pass{color:var(--ok);font-weight:700}
.checks .fail{color:var(--bad);font-weight:700}
.checks .warn{color:var(--warn);font-weight:700}

/* Radio cards */
.radio-cards{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
.radio-card{border:2px solid var(--line);border-radius:12px;padding:16px;cursor:pointer;text-align:center;transition:.15s}
.radio-card:hover{border-color:var(--brand)}
.radio-card.selected{border-color:var(--brand);background:#FFF5F0}
.radio-card input{display:none}
.radio-card .icon{font-size:28px;margin-bottom:6px}
.radio-card b{display:block;font-size:14px}
.radio-card span{font-size:12px;color:var(--muted)}

/* Install log */
#installLog{background:#1A1A14;color:#CFD8CF;border-radius:12px;padding:16px;font-family:"SF Mono",Consolas,monospace;font-size:12px;max-height:400px;overflow-y:auto;margin:16px 0;display:none;line-height:1.8}
#installLog .ok{color:#4CAF50}
#installLog .fail{color:#EF5350}

/* Alert */
.alert{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-size:13px}
.alert-ok{background:#E8F5E9;border:1px solid #A5D6A7;color:#1B5E20}
.alert-bad{background:#FFEBEE;border:1px solid #EF9A9A;color:#B71C1C}
.alert-warn{background:#FFF3E0;border:1px solid #FFE0B2;color:#E65100}
</style>
</head>
<body>
<div class="wizard">
  <div class="logo">
    <img src="{{ asset('brand/logo.svg') }}" alt="">
    <b>RoaMembers</b>
    <span>ระบบติดตั้งครั้งแรก · Step {{ $step }}/{{ $totalSteps }}</span>
  </div>

  {{-- Progress bar --}}
  <div class="progress">
    @for($i = 1; $i <= $totalSteps; $i++)
      <div class="step {{ $i < $step ? 'done' : ($i === $step ? 'active' : '') }}"></div>
    @endfor
  </div>

  @yield('content')
</div>
</body>
</html>
