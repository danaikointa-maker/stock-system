@extends('layouts.public')
@section('title', 'กระเป๋าแต้ม · RoaMembers')

@push('head')
<style>
  .wallet-top{
    background:
      radial-gradient(circle at 20% 20%, rgba(255,255,255,.16) 0 2px, transparent 3px),
      linear-gradient(160deg,var(--brand-light),var(--brand-dark));
    padding:20px 20px 52px;color:#fff;
  }
  .wallet-top .who{font-size:12.5px;opacity:.92}
  .wallet-top .pts{font-size:44px;font-weight:800;color:var(--gold);line-height:1.1;text-shadow:0 3px 0 var(--gold-dark)}
  .tabbar{display:flex;gap:6px;background:#F5F5F5;border-radius:12px;padding:5px;margin-bottom:16px}
  .tabbar button{
    flex:1;padding:10px;border:none;border-radius:9px;background:transparent;
    font-family:inherit;font-size:13px;font-weight:700;color:var(--muted);cursor:pointer;
  }
  .tabbar button.on{background:#fff;color:var(--brand);box-shadow:0 2px 6px rgba(0,0,0,.09)}
  .pane{display:none}
  .pane.on{display:block}
  .wallet-row{
    display:flex;justify-content:space-between;align-items:center;
    padding:13px 14px;border:1.5px solid var(--line);border-radius:14px;margin-bottom:9px;
  }
  .wallet-row .n{font-size:14px;font-weight:600}
  .wallet-row .s{font-size:11.5px;color:var(--muted)}
  .wallet-row b{color:var(--brand);font-size:18px}
  .hist{
    display:flex;justify-content:space-between;gap:10px;
    padding:12px 0;border-bottom:1px dashed var(--line);
  }
  .hist:last-child{border:0}
  .hist .t{font-size:13.5px;font-weight:600}
  .hist .d{font-size:11.5px;color:var(--muted);margin-top:2px}
  .hist .v{font-weight:800;white-space:nowrap;font-size:15px}
  .v-plus{color:var(--green)}
  .v-minus{color:var(--brand)}
  .empty{text-align:center;padding:34px 16px;color:var(--muted);font-size:13.5px}
  .warnbox{
    background:#FFF8E1;border:1px solid #FFE082;border-radius:12px;
    padding:12px 14px;font-size:12.5px;color:#7A5C00;line-height:1.6;margin-bottom:14px;
  }
</style>
@endpush

@section('body')
<div class="wallet-top">
  <div class="brandbar" style="margin-bottom:14px">
    <img src="{{ asset('brand/logo-192.png') }}" alt="RoaMembers">
    <div><span class="name">RoaMembers</span></div>
  </div>
  <div class="who">สวัสดี คุณ{{ $customer->name }} 👋</div>
  <div class="pts">{{ number_format($total) }}</div>
  <div class="who">แต้มสะสมทั้งหมด</div>
</div>

<div class="sheet">
  <div class="grabber"></div>

  @if($expiring->isNotEmpty())
    <div class="warnbox">
      <b>แต้มใกล้หมดอายุ</b><br>
      {{ number_format($expiring->sum('points_left')) }} แต้ม จะหมดอายุ
      {{ $expiring->first()->expires_at->format('j M Y') }} — รีบใช้ก่อนหมดนะครับ
    </div>
  @endif

  <div class="tabbar">
    <button class="on" data-tab="wallets">แต้มของฉัน</button>
    <button data-tab="history">ประวัติ</button>
    <button data-tab="me">บัญชีฉัน</button>
  </div>

  {{-- แต้มแยกตามร้าน --}}
  <div class="pane on" id="pane-wallets">
    @forelse($wallets as $w)
      <div class="wallet-row">
        <div>
          <div class="n">{{ $w->issuer->name ?? 'ร้านค้า' }}</div>
          <div class="s">สะสมมาแล้ว {{ number_format($w->lifetime_earned) }} แต้ม</div>
        </div>
        <b>{{ number_format($w->balance) }}</b>
      </div>
    @empty
      <div class="empty">
        ยังไม่มีแต้มสะสม<br>สแกน QR บนซองสินค้าเพื่อเริ่มเก็บแต้ม
      </div>
    @endforelse

    <a href="{{ route('scan.form') }}" class="btn btn-main" style="margin-top:14px">
      สแกนรับแต้มเพิ่ม
    </a>
  </div>

  {{-- ประวัติ --}}
  <div class="pane" id="pane-history">
    @php
      $timeline = collect();
      foreach ($scans as $s) {
          $timeline->push([
              'at'    => $s->scanned_at,
              'title' => 'รับแต้มจากการสแกน',
              'desc'  => $s->qrcode?->product?->name ?? 'สินค้า',
              'val'   => '+' . number_format($s->points_awarded ?? 0),
              'cls'   => 'v-plus',
          ]);
      }
      foreach ($redemptions as $r) {
          $timeline->push([
              'at'    => $r->redeemed_at,
              'title' => $r->reward_name,
              'desc'  => 'ที่ ' . ($r->shop->name ?? 'ร้านค้า'),
              'val'   => '-' . number_format($r->points_used),
              'cls'   => 'v-minus',
          ]);
      }
      $timeline = $timeline->filter(fn ($i) => $i['at'])->sortByDesc('at')->take(30);
    @endphp

    @forelse($timeline as $item)
      <div class="hist">
        <div>
          <div class="t">{{ $item['title'] }}</div>
          <div class="d">{{ $item['desc'] }} · {{ $item['at']->format('j M Y · H:i') }}</div>
        </div>
        <div class="v {{ $item['cls'] }}">{{ $item['val'] }}</div>
      </div>
    @empty
      <div class="empty">ยังไม่มีประวัติการใช้งาน</div>
    @endforelse

    @if($timeline->isNotEmpty())
      <a href="{{ route('scan.statement') }}" class="btn btn-ghost" style="margin-top:16px">
        ดาวน์โหลดประวัติ (PDF)
      </a>
    @endif
  </div>

  {{-- บัญชีฉัน --}}
  <div class="pane" id="pane-me">
    <div class="wallet-row">
      <div><div class="n">ชื่อ</div><div class="s">{{ $customer->name }}</div></div>
    </div>
    <div class="wallet-row">
      <div><div class="n">เบอร์โทร</div><div class="s">{{ $customer->phone }}</div></div>
    </div>
    <div class="wallet-row">
      <div>
        <div class="n">LINE</div>
        <div class="s">{{ $customer->line_user_id ? 'ผูกแล้ว' : 'ยังไม่ได้ผูก' }}</div>
      </div>
      @unless($customer->line_user_id)
        <a href="{{ route('social.redirect', 'line') }}"
           style="font-size:12.5px;color:#06C755;font-weight:700">ผูกเลย</a>
      @endunless
    </div>

    <p class="hint" style="margin-top:14px">
      แก้ไขข้อมูลส่วนตัวเพิ่มเติมได้ที่หน้าจัดการบัญชี
      หรือแจ้งร้านค้าที่ร่วมรายการ
    </p>
  </div>
</div>
@endsection

@push('scripts')
<script>
/* สลับแท็บ */
document.querySelectorAll('.tabbar button').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.tabbar button').forEach(b => b.classList.remove('on'));
    document.querySelectorAll('.pane').forEach(p => p.classList.remove('on'));
    btn.classList.add('on');
    document.getElementById('pane-' + btn.dataset.tab).classList.add('on');
  });
});
</script>
@endpush
