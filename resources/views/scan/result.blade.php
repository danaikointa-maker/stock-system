@extends('layouts.public')
@section('title', $result['ok'] ? 'รับแต้มสำเร็จ · ' . config('app.name', 'RaoMembers') : 'สแกนไม่สำเร็จ · ' . config('app.name', 'RaoMembers'))

@push('head')
<style>
  .win{
    background:
      radial-gradient(circle at 20% 20%, rgba(255,255,255,.18) 0 2px, transparent 3px),
      linear-gradient(160deg,var(--green-light),var(--green));
    color:#fff;padding:32px 20px 58px;text-align:center;position:relative;overflow:hidden;
  }
  .lose{
    background:linear-gradient(160deg,#78909C,#455A64);
    color:#fff;padding:32px 20px 58px;text-align:center;position:relative;
  }
  .confetti{position:absolute;inset:0;pointer-events:none}
  .confetti i{position:absolute;width:9px;height:14px;border-radius:2px;opacity:.9;animation:fall 2.6s linear infinite}
  @keyframes fall{
    0%{transform:translateY(-30px) rotate(0);opacity:0}
    12%{opacity:1}
    100%{transform:translateY(340px) rotate(420deg);opacity:0}
  }
  .badge-pop{
    width:100px;height:100px;margin:0 auto 14px;border-radius:50%;
    background:var(--gold);color:var(--brand-dark);
    display:grid;place-items:center;font-size:42px;font-weight:800;
    box-shadow:0 0 0 9px rgba(255,255,255,.20), 0 12px 30px rgba(0,0,0,.26);
    animation:pop .55s cubic-bezier(.2,1.5,.4,1) both;
  }
  .badge-sad{
    width:100px;height:100px;margin:0 auto 14px;border-radius:50%;
    background:rgba(255,255,255,.18);display:grid;place-items:center;font-size:44px;
  }
  @keyframes pop{0%{transform:scale(.3);opacity:0}100%{transform:scale(1);opacity:1}}
  .plus{font-size:50px;font-weight:800;color:var(--gold);line-height:1;margin:6px 0;text-shadow:0 4px 0 var(--gold-dark)}
  .bal-card{
    background:linear-gradient(135deg,var(--gold),#FFB300);
    border-radius:18px;padding:18px;text-align:center;margin-bottom:16px;
    box-shadow:0 6px 18px rgba(255,178,0,.32);
  }
  .bal-card .lbl{font-size:12.5px;color:#7A5C00;font-weight:600}
  .bal-card .num{font-size:40px;font-weight:800;color:var(--brand-dark);line-height:1.15}
  .rowinfo{display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px dashed var(--line);font-size:13.5px}
  .rowinfo:last-child{border:0}
  .rowinfo span:first-child{color:var(--muted)}
  .rowinfo span:last-child{font-weight:700}
  .wallet-row{
    display:flex;justify-content:space-between;align-items:center;
    padding:12px 14px;border:1.5px solid var(--line);border-radius:13px;margin-bottom:9px;
  }
  .wallet-row b{color:var(--brand);font-size:16px}
</style>
@endpush

@section('body')
@if($result['ok'])
  <div class="win">
    <div class="confetti">
      @foreach([['8%','#FFC400','0s'],['22%','#fff','.4s'],['38%','#FFC400','.9s'],['56%','#FF8A80','.2s'],['72%','#fff','1.2s'],['88%','#FFC400','.7s']] as [$l,$c,$d])
        <i style="left:{{ $l }};background:{{ $c }};animation-delay:{{ $d }}"></i>
      @endforeach
    </div>
    <div class="badge-pop">✓</div>
    <h2 style="margin:0 0 6px;font-size:24px;font-weight:800">สะสมแต้มสำเร็จ!</h2>
    <div class="plus">+{{ number_format($result['points']) }}</div>
    <div style="font-size:13.5px;opacity:.94">{{ $result['product'] ?? 'สินค้า' }}</div>
  </div>
@else
  <div class="lose">
    <div class="badge-sad">😕</div>
    <h2 style="margin:0 0 8px;font-size:22px;font-weight:800">สแกนไม่สำเร็จ</h2>
    <div style="font-size:14px;opacity:.95;line-height:1.6">{{ $result['message'] }}</div>
  </div>
@endif

<div class="sheet">
  <div class="grabber"></div>

  @if($customer)
    <div class="bal-card">
      <div class="lbl">แต้มสะสมทั้งหมด</div>
      <div class="num">{{ number_format($total) }}</div>
      @if($expiring->isNotEmpty())
        <div style="font-size:11.5px;color:#7A5C00;margin-top:2px">
          {{ number_format($expiring->sum('points_left')) }} แต้ม หมดอายุ
          {{ $expiring->first()->expires_at->thaidate('j M Y') ?? $expiring->first()->expires_at->format('j M Y') }}
        </div>
      @endif
    </div>

    {{-- แต้มแยกตามร้าน --}}
    @if($wallets->count() > 1)
      <div style="font-size:13.5px;font-weight:800;margin:18px 0 10px">แต้มของคุณแยกตามร้าน</div>
      @foreach($wallets as $w)
        <div class="wallet-row">
          <span style="font-size:13.5px">{{ $w->issuer->name ?? 'ร้านค้า' }}</span>
          <b>{{ number_format($w->balance) }}</b>
        </div>
      @endforeach
    @endif

    @if($result['ok'])
      <div style="margin-top:18px">
        <div class="rowinfo">
          <span>สแกนเมื่อ</span>
          <span>{{ now()->format('j M Y · H:i') }}</span>
        </div>
        <div class="rowinfo">
          <span>ได้รับ</span>
          <span>{{ number_format($result['points']) }} แต้ม</span>
        </div>
      </div>
    @endif

    <a href="{{ route('scan.wallet') }}" class="btn btn-main" style="margin-top:18px">
      💰 ดูกระเป๋าแต้ม 🎁
    </a>
    <a href="{{ route('scan.form') }}" class="btn btn-ghost" style="margin-top:10px">
      📷 สแกนซองถัดไป
    </a>
  @else
    <div class="alert a-info">กรุณายืนยันเบอร์โทรเพื่อเก็บแต้ม</div>
    <a href="{{ route('scan.form') }}" class="btn btn-main">กลับไปกรอกเบอร์</a>
  @endif
</div>
@endsection
