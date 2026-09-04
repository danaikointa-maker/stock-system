<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
<meta name="theme-color" content="#F04800">
<title>@yield('title', 'RoaMembers — สะสมแต้ม')</title>

{{-- ป้องกันการฝังหน้านี้ในเว็บอื่น --}}
<meta name="referrer" content="strict-origin-when-cross-origin">

<link rel="icon" href="{{ asset('brand/logo.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('brand/logo.svg') }}">

<style>
  /* ===== สีแบรนด์ RoaMembers (ดึงจากโลโก้จริง) ===== */
  :root{
    --brand:#F04800;        /* ส้ม */
    --brand-dark:#C23800;
    --brand-light:#FF6B2B;
    --green:#006018;        /* เขียว */
    --green-light:#0C8A2C;
    --gold:#FFC400;
    --gold-dark:#B8860B;
    --ink:#14140F;
    --muted:#6E6E63;
    --line:#E8E8E0;
    --bg:#F4F4EE;
  }
  *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
  html,body{margin:0;padding:0}
  body{
    font-family:'Kanit','Prompt',-apple-system,'Segoe UI',sans-serif;
    background:var(--bg);color:var(--ink);
    min-height:100vh;display:flex;justify-content:center;
  }
  .shell{width:100%;max-width:480px;background:#fff;min-height:100vh;position:relative}

  /* ===== ส่วนหัวแดง ===== */
  .hero{
    background:
      radial-gradient(circle at 18% 12%, rgba(255,255,255,.20) 0 2px, transparent 3px),
      radial-gradient(circle at 78% 26%, rgba(255,255,255,.16) 0 2px, transparent 3px),
      radial-gradient(ellipse at 50% -10%, var(--brand-light) 0%, var(--brand) 45%, var(--brand-dark) 100%);
    padding:20px 20px 58px;position:relative;overflow:hidden;color:#fff;
  }
  .hero::after{
    content:"";position:absolute;top:-70px;right:-70px;width:190px;height:190px;
    background:conic-gradient(from 20deg, var(--gold), #FFB300, var(--gold));
    opacity:.18;border-radius:42px;transform:rotate(28deg);
  }
  .brandbar{display:flex;align-items:center;gap:11px;position:relative;z-index:2}
  .brandbar img{
    width:50px;height:50px;object-fit:contain;background:rgba(255,255,255,.96);
    border-radius:14px;padding:4px;box-shadow:0 4px 12px rgba(0,0,0,.22);
  }
  .brandbar .name{font-weight:800;font-size:19px;letter-spacing:.3px;text-shadow:0 2px 0 rgba(0,0,0,.2)}
  .brandbar .sub{font-size:11.5px;opacity:.92;display:block;font-weight:400}

  .headline{position:relative;z-index:2;margin-top:16px}
  .headline h1{margin:0;font-size:26px;line-height:1.15;font-weight:800;text-shadow:0 3px 0 rgba(0,0,0,.16)}
  .headline h1 em{font-style:normal;display:block;color:var(--gold);font-size:31px;text-shadow:0 3px 0 var(--gold-dark)}
  .headline p{margin:9px 0 0;font-size:13.5px;opacity:.95;line-height:1.55}

  /* ===== แผ่นเนื้อหา ===== */
  .sheet{
    background:#fff;border-radius:24px 24px 0 0;margin-top:-38px;
    position:relative;z-index:3;padding:22px 20px 32px;
  }
  .grabber{width:44px;height:5px;background:#E0E0E0;border-radius:99px;margin:0 auto 18px}

  /* ===== ฟอร์ม ===== */
  .field{margin-bottom:15px}
  .field label{display:block;font-size:14px;font-weight:700;margin-bottom:7px}
  .req{color:var(--brand);font-size:12.5px}
  .opt{color:var(--muted);font-weight:500;font-size:12px}
  .input{
    width:100%;padding:15px 16px;font-size:17px;font-family:inherit;
    border:2px solid var(--line);border-radius:14px;outline:none;background:#FAFAFA;
    transition:border-color .18s, box-shadow .18s;
  }
  .input:focus{border-color:var(--brand);background:#fff;box-shadow:0 0 0 4px rgba(240,72,0,.12)}
  .input::placeholder{color:#BDBDBD}
  .input.err{border-color:#D32F2F;background:#FFF5F5}
  .hint{font-size:12px;color:var(--muted);margin-top:6px;line-height:1.5}
  .errmsg{font-size:12.5px;color:#D32F2F;margin-top:6px;font-weight:600}

  .btn{
    width:100%;padding:17px;border:none;border-radius:16px;font-size:17px;
    font-weight:800;font-family:inherit;cursor:pointer;display:flex;
    align-items:center;justify-content:center;gap:9px;text-decoration:none;
    transition:transform .12s;
  }
  .btn:active{transform:translateY(2px)}
  .btn-main{
    background:linear-gradient(180deg,var(--brand-light),var(--brand));color:#fff;
    box-shadow:0 4px 0 var(--brand-dark), 0 10px 22px rgba(240,72,0,.30);
  }
  .btn-main:disabled{opacity:.6;cursor:not-allowed}
  .btn-line{background:#06C755;color:#fff;box-shadow:0 4px 0 #049a43}
  .btn-goog{background:#fff;color:#3C4043;border:2px solid #DADCE0;box-shadow:0 4px 0 #E8EAED}
  .btn-ghost{background:#fff;color:var(--brand);border:2px solid var(--brand)}

  .divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:var(--muted);font-size:13px}
  .divider::before,.divider::after{content:"";flex:1;height:1px;background:var(--line)}

  .consent{
    display:flex;gap:10px;align-items:flex-start;margin-top:16px;
    background:#FFF9E6;border:1px solid #FFE9A8;border-radius:12px;padding:12px;
  }
  .consent input{margin-top:3px;width:17px;height:17px;accent-color:var(--brand);flex-shrink:0}
  .consent label{font-size:11.5px;color:#7A5C00;line-height:1.6}

  .alert{padding:13px 15px;border-radius:13px;font-size:13.5px;margin-bottom:16px;line-height:1.55}
  .a-ok{background:#E8F5E9;color:#1B5E20;border:1px solid #A5D6A7}
  .a-bad{background:#FFEBEE;color:#B71C1C;border:1px solid #EF9A9A}
  .a-info{background:#E3F2FD;color:#0D47A1;border:1px solid #90CAF9}

  .foot{padding:16px 20px 26px;text-align:center;font-size:11.5px;color:#9E9E9E;line-height:1.7}
  .foot a{color:var(--muted)}
</style>
@stack('head')
</head>
<body>
<div class="shell">
  @yield('body')

  <div class="foot">
    RoaMembers · ระบบสะสมแต้ม<br>
    @if(!empty($customer))
      <form method="POST" action="{{ route('scan.forget') }}" style="display:inline">
        @csrf
        <button type="submit" style="background:none;border:none;color:#9E9E9E;font-family:inherit;font-size:11.5px;text-decoration:underline;cursor:pointer;padding:6px">
          ออกจากระบบ
        </button>
      </form>
    @endif
  </div>
</div>
@stack('scripts')
</body>
</html>
