<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
<meta name="theme-color" content="{{ $colors['primary'] }}">
<title>{{ $profile->display_name }} · RoaMembers</title>
<link rel="icon" href="{{ asset('brand/logo-96.png') }}">

<style>
  /*
   * หน้าร้านสาธารณะ — ใช้โครงเดียวกันทุกร้าน
   * ต่างกันแค่ชุดสีที่ร้านเลือก ส่งผ่าน CSS variable
   */
  :root{
    --t:{{ $colors['primary'] }};
    --t2:{{ $colors['secondary'] }};
    --ink:#14140F; --muted:#6E6E63; --line:#E8E8E0; --bg:#F4F4EE;
  }
  *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
  html,body{margin:0;padding:0}
  body{
    font-family:'Kanit','Prompt',-apple-system,'Segoe UI',sans-serif;
    background:var(--bg);color:var(--ink);
    display:flex;justify-content:center;min-height:100vh;
  }
  .shell{width:100%;max-width:480px;background:#fff;min-height:100vh;position:relative}

  .preview-bar{
    background:#FFF3CD;color:#7A5C00;padding:9px 14px;font-size:12.5px;text-align:center;
    border-bottom:1px solid #FFE9A8;
  }

  .cover{
    height:150px;position:relative;overflow:hidden;
    background:linear-gradient(135deg,var(--t),var(--t2));
  }
  .cover img{width:100%;height:100%;object-fit:cover}
  .cover::before{
    content:"";position:absolute;inset:0;z-index:1;
    background-image:radial-gradient(circle at 20% 30%, rgba(255,255,255,.18) 0 2px, transparent 3px),
                     radial-gradient(circle at 70% 60%, rgba(255,255,255,.12) 0 2px, transparent 3px);
    background-size:64px 64px;
  }
  .cover::after{
    content:"";position:absolute;left:-14%;right:-14%;bottom:-56px;height:96px;
    background:#fff;border-radius:50%;z-index:2;
  }
  .cover .emoji{position:absolute;right:18px;top:18px;font-size:56px;opacity:.28;z-index:1}
  .badge-open{
    position:absolute;left:16px;top:16px;z-index:3;
    background:rgba(255,255,255,.95);color:var(--t);
    font-size:11.5px;font-weight:800;padding:5px 12px;border-radius:99px;
  }

  .head{padding:0 20px 16px;position:relative;margin-top:-46px;z-index:3}
  .avatar{
    width:82px;height:82px;border-radius:22px;background:#fff;
    box-shadow:0 4px 14px rgba(0,0,0,.16);display:grid;place-items:center;
    font-size:38px;border:3px solid #fff;margin-bottom:11px;overflow:hidden;
  }
  .avatar img{width:100%;height:100%;object-fit:cover}
  .head .cat{
    display:inline-block;font-size:11px;font-weight:700;color:var(--t);
    background:color-mix(in srgb, var(--t) 12%, #fff);
    padding:3px 10px;border-radius:99px;margin-bottom:7px;
  }
  .head h1{margin:0 0 4px;font-size:21px;font-weight:800;line-height:1.3}
  .head .tag{margin:0;font-size:13.5px;color:var(--muted);line-height:1.6}

  .meta{display:flex;flex-wrap:wrap;gap:8px;padding:0 20px 16px}
  .meta span{
    font-size:11.5px;color:var(--muted);background:#F7F7F2;
    padding:6px 11px;border-radius:8px;
  }

  .sec{padding:0 20px}
  .sec h2{
    margin:20px 0 12px;font-size:15px;font-weight:800;
    display:flex;align-items:center;gap:8px;
  }
  .sec h2::before{content:"";width:4px;height:16px;border-radius:3px;background:var(--t)}

  .rw{
    display:flex;gap:12px;align-items:center;padding:13px;
    border:1.5px solid var(--line);border-radius:15px;margin-bottom:10px;
  }
  .rw.hot{border-color:color-mix(in srgb, var(--t) 40%, #fff);
          background:color-mix(in srgb, var(--t) 4%, #fff)}
  .rw .th{
    width:54px;height:54px;border-radius:13px;flex-shrink:0;display:grid;
    place-items:center;font-size:25px;overflow:hidden;
    background:color-mix(in srgb, var(--t) 12%, #fff);
  }
  .rw .th img{width:100%;height:100%;object-fit:cover}
  .rw .g{flex:1;min-width:0}
  .rw .g b{display:block;font-size:14.5px;margin-bottom:3px}
  .rw .g small{color:var(--muted);font-size:11.5px;display:block;line-height:1.5}
  .rw .cost{text-align:right;flex-shrink:0}
  .rw .cost .p{font-weight:800;color:var(--t);font-size:16px;white-space:nowrap}
  .rw .cost .u{font-size:10px;color:var(--muted)}
  .rw.out{opacity:.5}

  .desc{font-size:13.5px;line-height:1.75;color:#3A3A32;white-space:pre-line}

  .gal{display:flex;gap:8px;overflow-x:auto;padding-bottom:4px}
  .gal img{
    min-width:104px;height:78px;border-radius:12px;object-fit:cover;
    border:1.5px solid var(--line);
  }

  .cta{padding:20px;display:flex;gap:10px}
  .cta a{
    flex:1;padding:15px;border-radius:14px;font-weight:800;font-size:14.5px;
    text-align:center;text-decoration:none;
  }
  .cta .primary{background:linear-gradient(180deg,var(--t2),var(--t));color:#fff}
  .cta .ghost{background:#fff;color:var(--t);border:2px solid var(--t)}

  .foot{
    padding:18px 20px 28px;text-align:center;font-size:11.5px;
    color:#9E9E9E;line-height:1.8;border-top:1px solid var(--line);margin-top:16px;
  }
  .foot img{width:34px;height:34px;object-fit:contain;opacity:.7;margin-bottom:6px}
  .empty{text-align:center;padding:30px 16px;color:var(--muted);font-size:13px}
</style>
</head>
<body>
<div class="shell">

  @if(!empty($isPreview))
    <div class="preview-bar">
      กำลังดูตัวอย่าง — สถานะปัจจุบัน:
      <b>{{ $profile->status === 'active' ? 'เผยแพร่แล้ว' : 'ยังไม่เผยแพร่' }}</b>
    </div>
  @endif

  @php
    $blocks = $profile->blocks ?? [];
    $icons = ['cafe'=>'☕','restaurant'=>'🍜','carwash'=>'🚗','beauty'=>'💇','pharmacy'=>'💊','retail'=>'🏪','other'=>'🏬'];
    $typeNames = ['cafe'=>'ร้านกาแฟ','restaurant'=>'ร้านอาหาร','carwash'=>'คาร์แคร์','beauty'=>'ร้านเสริมสวย','pharmacy'=>'ร้านขายยา','retail'=>'มินิมาร์ท','other'=>'ร้านค้า'];
    $icon = $icons[$profile->business_type] ?? '🏪';
  @endphp

  <div class="cover">
    @if($profile->cover_path)
      <img src="{{ Storage::url($profile->cover_path) }}" alt="">
    @endif
    <span class="badge-open">เข้าร่วม RoaMembers</span>
    <span class="emoji">{{ $icon }}</span>
  </div>

  <div class="head">
    <div class="avatar">
      @if($profile->logo_path)
        <img src="{{ Storage::url($profile->logo_path) }}" alt="โลโก้">
      @else
        {{ $icon }}
      @endif
    </div>
    <span class="cat">{{ $typeNames[$profile->business_type] ?? 'ร้านค้า' }}</span>
    <h1>{{ $profile->display_name }}</h1>
    @if($profile->tagline)
      <p class="tag">{{ $profile->tagline }}</p>
    @endif
  </div>

  <div class="meta">
    @if(!empty($blocks['map']) && $profile->address)
      <span>📍 {{ Str::limit($profile->address, 40) }}</span>
    @endif
    @if(!empty($blocks['contact']) && $profile->phone)
      <span>📞 {{ $profile->phone }}</span>
    @endif
    @if(!empty($blocks['contact']) && $profile->line_id)
      <span>💬 {{ $profile->line_id }}</span>
    @endif
  </div>

  {{-- รายการแลกแต้ม --}}
  @if(!empty($blocks['rewards']))
    <div class="sec">
      <h2>แลกด้วยแต้ม</h2>

      @forelse($rewards as $rw)
        @php $left = $rw->stockLeft(); @endphp
        <div class="rw {{ $left === 0 ? 'out' : ($loop->first ? 'hot' : '') }}">
          <div class="th">
            @if($rw->image_path)
              <img src="{{ Storage::url($rw->image_path) }}" alt="">
            @else
              {{ $rw->displayIcon() }}
            @endif
          </div>
          <div class="g">
            <b>{{ $rw->name }}</b>
            @if($rw->description)<small>{{ $rw->description }}</small>@endif
            @if($left !== null)
              <small>{{ $left > 0 ? "เหลือ {$left} สิทธิ์" : 'หมดแล้ว' }}</small>
            @endif
          </div>
          <div class="cost">
            <div class="p">{{ number_format($rw->points_cost) }}</div>
            <div class="u">แต้ม</div>
          </div>
        </div>
      @empty
        <div class="empty">ร้านนี้ยังไม่ได้ตั้งรายการแลกแต้ม</div>
      @endforelse
    </div>
  @endif

  {{-- รายละเอียดร้าน --}}
  @if($profile->description)
    <div class="sec">
      <h2>เกี่ยวกับร้าน</h2>
      <div class="desc">{{ $profile->description }}</div>
    </div>
  @endif

  {{-- แกลเลอรี --}}
  @if(!empty($blocks['gallery']) && !empty($profile->gallery))
    <div class="sec">
      <h2>บรรยากาศร้าน</h2>
      <div class="gal">
        @foreach($profile->gallery as $img)
          <img src="{{ Storage::url($img) }}" alt="">
        @endforeach
      </div>
    </div>
  @endif

  {{-- เวลาทำการ --}}
  @if(!empty($blocks['hours']) && !empty($profile->open_hours))
    <div class="sec">
      <h2>เวลาทำการ</h2>
      @php $dayNames = ['mon'=>'จันทร์','tue'=>'อังคาร','wed'=>'พุธ','thu'=>'พฤหัสบดี','fri'=>'ศุกร์','sat'=>'เสาร์','sun'=>'อาทิตย์']; @endphp
      @foreach($profile->open_hours as $day => $hours)
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed var(--line);font-size:13px">
          <span style="color:var(--muted)">{{ $dayNames[$day] ?? $day }}</span>
          <span style="font-weight:600">
            {{ is_array($hours) ? implode(' - ', $hours) : $hours }}
          </span>
        </div>
      @endforeach
    </div>
  @endif

  <div class="cta">
    <a href="{{ route('scan.form') }}" class="ghost">สะสมแต้ม</a>
    <a href="{{ route('scan.wallet') }}" class="primary">ดูแต้มของฉัน</a>
  </div>

  <div class="foot">
    <img src="{{ asset('brand/logo-96.png') }}" alt="RoaMembers"><br>
    ร้านนี้เข้าร่วมโครงการสะสมแต้ม RoaMembers<br>
    สแกน QR บนสินค้าเพื่อรับแต้ม แล้วนำมาแลกที่ร้านนี้ได้
  </div>
</div>
</body>
</html>
