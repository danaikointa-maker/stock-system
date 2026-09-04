@extends('layouts.app')
@section('title', 'รับแลกแต้ม')

@push('head')
<style>
  .desk-grid{display:grid;grid-template-columns:1fr 340px;gap:18px}
  @media(max-width:900px){.desk-grid{grid-template-columns:1fr}}

  .quota-card{
    background:linear-gradient(135deg,#F04800,#C23800);color:#fff;
    border-radius:16px;padding:16px 18px;margin-bottom:16px;
  }
  .quota-card.low{background:linear-gradient(135deg,#E65100,#BF360C)}
  .quota-card.out{background:linear-gradient(135deg,#616161,#424242)}
  .quota-card .l{font-size:12px;opacity:.9}
  .quota-card .v{font-size:32px;font-weight:800;line-height:1.2}
  .quota-bar{height:7px;background:rgba(255,255,255,.28);border-radius:99px;margin-top:9px;overflow:hidden}
  .quota-bar i{display:block;height:100%;background:#FFC400;border-radius:99px}

  .searchbox{display:flex;gap:9px;margin-bottom:16px}
  .searchbox input{
    flex:1;padding:14px 16px;font-size:17px;font-family:inherit;
    border:2px solid var(--line);border-radius:12px;outline:none;
  }
  .searchbox input:focus{border-color:var(--brand)}
  .searchbox button{
    padding:14px 24px;border:none;border-radius:12px;background:var(--brand);
    color:#fff;font-family:inherit;font-weight:800;font-size:15px;cursor:pointer;
  }

  .cust-card{
    border:2px solid #FFD97A;background:#FFFCF2;border-radius:14px;
    padding:14px 16px;margin-bottom:14px;
  }
  .cust-card .nm{font-size:17px;font-weight:800}
  .cust-card .ph{font-size:13px;color:var(--muted)}

  .wallet-pick{
    display:flex;justify-content:space-between;align-items:center;gap:12px;
    padding:13px 15px;border:2px solid var(--line);border-radius:13px;
    margin-bottom:9px;cursor:pointer;transition:border-color .15s,background .15s;
  }
  .wallet-pick:hover{border-color:var(--brand)}
  .wallet-pick.sel{border-color:var(--brand);background:#FFF4EE}
  .wallet-pick input{width:19px;height:19px;accent-color:var(--brand)}
  .wallet-pick .n{font-size:14px;font-weight:600}
  .wallet-pick .b{font-size:20px;font-weight:800;color:var(--brand)}

  .typegrid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:14px}
  @media(max-width:600px){.typegrid{grid-template-columns:repeat(2,1fr)}}
  .typegrid label{
    border:2px solid var(--line);border-radius:12px;padding:12px 6px;
    text-align:center;cursor:pointer;font-size:13px;font-weight:700;
    display:flex;flex-direction:column;gap:5px;align-items:center;
  }
  .typegrid label:has(input:checked){border-color:var(--brand);background:#FFF4EE;color:var(--brand)}
  .typegrid input{display:none}
  .typegrid .ic{font-size:22px}

  .calc{
    background:#F7F7F2;border-radius:12px;padding:14px 16px;margin:14px 0;
    display:flex;justify-content:space-between;align-items:center;
  }
  .calc .l{font-size:13px;color:var(--muted)}
  .calc .v{font-size:24px;font-weight:800;color:var(--green)}

  .stock-row{
    display:flex;gap:10px;align-items:center;padding:10px 12px;
    border:1.5px solid var(--line);border-radius:11px;margin-bottom:8px;font-size:13px;
  }
  .stock-row .g{flex:1}
  .stock-row .lot{font-size:11px;color:var(--muted)}
  .stock-row input[type=number]{
    width:70px;padding:7px 9px;border:1.5px solid var(--line);
    border-radius:8px;font-family:inherit;text-align:center;
  }
  .exp-warn{color:#E65100;font-weight:700}

  .recent-row{
    display:flex;justify-content:space-between;padding:10px 0;
    border-bottom:1px dashed var(--line);font-size:12.5px;
  }
  .recent-row:last-child{border:0}
  .recent-row .t{font-weight:600}
  .recent-row .d{color:var(--muted);font-size:11px}
  .recent-row .p{font-weight:800;color:var(--brand);white-space:nowrap}
</style>
@endpush

@section('content')
<h1 style="margin:0 0 4px">รับแลกแต้ม</h1>
<p style="margin:0 0 18px;color:var(--muted);font-size:13.5px">
  {{ $shop->name }} · ค้นหาลูกค้าด้วยเบอร์โทรเพื่อเริ่มรายการ
</p>

@if(session('status'))
  <div class="alert a-ok">{{ session('status') }}</div>
@endif

@if($errors->any())
  <div class="alert a-bad">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
  </div>
@endif

{{-- สถานะวงเงินร้าน --}}
@php
  $total = $allowance ? ($allowance->limit_points + $allowance->rollover_points + $allowance->topup_points) : 0;
  $left = $allowance->remaining_points ?? 0;
  $pct = $total > 0 ? round($left * 100 / $total) : 0;
  $cls = ! $allowance ? 'out' : ($left <= 0 ? 'out' : ($pct <= 20 ? 'low' : ''));
@endphp

<div class="quota-card {{ $cls }}">
  <div class="l">วงเงินรับแลกเดือนนี้ ({{ now()->format('m/Y') }})</div>
  @if($allowance)
    <div class="v">{{ number_format($left) }} <span style="font-size:15px;font-weight:400">/ {{ number_format($total) }} แต้ม</span></div>
    <div class="quota-bar"><i style="width:{{ max(0,$pct) }}%"></i></div>
    @if($left <= 0)
      <div class="l" style="margin-top:8px">วงเงินหมดแล้ว — รอรีเซตเดือนหน้า หรือซื้อวงเงินเพิ่ม</div>
    @elseif($pct <= 20)
      <div class="l" style="margin-top:8px">วงเงินใกล้หมด เหลือ {{ $pct }}%</div>
    @endif
  @else
    <div class="v" style="font-size:19px">ยังไม่พร้อมรับแลก</div>
    <div class="l" style="margin-top:6px">ร้านนี้ยังไม่ได้สมัครแพ็กเกจ หรือสมาชิกหมดอายุ</div>
  @endif
</div>

<div class="desk-grid">
  <div>
    {{-- ค้นหาลูกค้า --}}
    <form method="GET" action="{{ route('redeem.desk') }}" class="searchbox">
      <input type="tel" name="phone" inputmode="numeric" maxlength="10"
             value="{{ request('phone') }}" placeholder="เบอร์โทรลูกค้า 08X-XXX-XXXX" autofocus>
      <button type="submit">ค้นหา</button>
    </form>

    @if($searched && ! $customer)
      <div class="alert a-bad">ไม่พบลูกค้าเบอร์นี้ในระบบ — ลูกค้าต้องสแกน QR สะสมแต้มก่อน</div>
    @endif

    @if($customer)
      <div class="cust-card">
        <div class="nm">{{ $customer->name }}</div>
        <div class="ph">{{ $customer->phone }}
          @if($customer->status !== 'active')
            <span class="badge b-red" style="margin-left:6px">ถูกระงับ</span>
          @endif
        </div>
      </div>

      @if($customer->status !== 'active')
        <div class="alert a-bad">บัญชีลูกค้าถูกระงับ ไม่สามารถแลกแต้มได้</div>
      @elseif($wallets->isEmpty())
        <div class="alert a-info">ลูกค้ายังไม่มีแต้มคงเหลือ</div>
      @elseif(! $allowance || $left <= 0)
        <div class="alert a-bad">ร้านยังไม่พร้อมรับแลก — ตรวจสอบวงเงินหรือสถานะสมาชิก</div>
      @else
        <form method="POST" action="{{ route('redeem.store') }}" id="redeemForm">
          @csrf
          <input type="hidden" name="customer_id" value="{{ $customer->id }}">

          <div class="card" style="margin-bottom:14px">
            <div class="body">
              <h3 style="margin:0 0 11px;font-size:14px">1. เลือกแต้มที่จะใช้</h3>
              @foreach($wallets as $i => $w)
                <label class="wallet-pick {{ $i === 0 ? 'sel' : '' }}">
                  <input type="radio" name="wallet_id" value="{{ $w->id }}"
                         data-balance="{{ $w->balance }}" {{ $i === 0 ? 'checked' : '' }}>
                  <div style="flex:1">
                    <div class="n">{{ $w->issuer->name ?? 'ร้านค้า' }}</div>
                  </div>
                  <div class="b">{{ number_format($w->balance) }}</div>
                </label>
              @endforeach
            </div>
          </div>

          <div class="card" style="margin-bottom:14px">
            <div class="body">
              <h3 style="margin:0 0 11px;font-size:14px">2. แลกเป็นอะไร</h3>
              <div class="typegrid">
                @foreach([
                  ['goods','🛍️','สินค้า'],
                  ['service','🔧','บริการ'],
                  ['discount','🎫','ส่วนลด'],
                  ['cash','💵','เงินสด'],
                ] as [$val,$ic,$lbl])
                  <label>
                    <input type="radio" name="redeem_type" value="{{ $val }}"
                           {{ old('redeem_type', 'service') === $val ? 'checked' : '' }}>
                    <span class="ic">{{ $ic }}</span>
                    <span>{{ $lbl }}</span>
                  </label>
                @endforeach
              </div>

              <div class="field">
                <label for="reward_name">รายละเอียด</label>
                <input class="input" type="text" id="reward_name" name="reward_name"
                       value="{{ old('reward_name') }}" maxlength="200"
                       placeholder="เช่น ล้างรถ 1 ครั้ง / ส่วนลด 50 บาท" required>
              </div>

              <div class="field" style="margin-bottom:0">
                <label for="points">จำนวนแต้มที่ใช้</label>
                <input class="input" type="number" id="points" name="points"
                       value="{{ old('points') }}" min="1" step="1" required
                       placeholder="กรอกจำนวนแต้ม">
                <p class="hint" id="pointHint"></p>
              </div>

              <div class="calc">
                <span class="l">มูลค่าที่ร้านจะได้รับคืน</span>
                <span class="v" id="cashValue">0.00 บาท</span>
              </div>
            </div>
          </div>

          {{-- เลือกสินค้า (แสดงเฉพาะเมื่อเลือกแลกสินค้า) --}}
          <div class="card" id="goodsBlock" style="margin-bottom:14px;display:none">
            <div class="body">
              <h3 style="margin:0 0 11px;font-size:14px">3. เลือกสินค้าที่จ่ายออก</h3>
              <p class="hint" style="margin-bottom:11px">
                ระบุจำนวนที่จ่าย ระบบจะตัดสต๊อกและบันทึกเลขล็อตไว้ตรวจสอบย้อนหลัง
              </p>

              @forelse($stock as $i => $s)
                <div class="stock-row">
                  <div class="g">
                    <div>{{ $s->product_name }}</div>
                    <div class="lot">
                      {{ $s->sku }}
                      @if($s->lot_no)
                        · ล็อต {{ $s->lot_no }}
                        @if($s->expiry_date)
                          @php $days = now()->diffInDays(\Carbon\Carbon::parse($s->expiry_date), false); @endphp
                          · หมดอายุ {{ \Carbon\Carbon::parse($s->expiry_date)->format('j M y') }}
                          @if($days <= 30)<span class="exp-warn">(เหลือ {{ $days }} วัน)</span>@endif
                        @endif
                      @endif
                      · คงเหลือ {{ number_format($s->qty_available) }}
                    </div>
                  </div>
                  <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $s->product_id }}" disabled>
                  <input type="hidden" name="items[{{ $i }}][lot_id]" value="{{ $s->lot_id }}" disabled>
                  <input type="number" name="items[{{ $i }}][qty]" min="0" max="{{ $s->qty_available }}"
                         value="0" class="qtyInput" disabled>
                </div>
              @empty
                <div class="empty">ร้านนี้ยังไม่มีสินค้าในสต๊อก</div>
              @endforelse
            </div>
          </div>

          <button type="submit" class="btn btn-p" style="width:100%;padding:15px;font-size:16px">⭐
            ยืนยันการแลกแต้ม
          </button>
        </form>
      @endif
    @endif
  </div>

  {{-- แถบข้าง --}}
  <div>
    <div class="card">
      <div class="body">
        <h3 style="margin:0 0 11px;font-size:14px">รายการล่าสุด</h3>
        @forelse($recent as $r)
          <div class="recent-row">
            <div>
              <div class="t">{{ $r->reward_name }}</div>
              <div class="d">
                {{ $r->customer_name ?? '—' }} ·
                {{ \Carbon\Carbon::parse($r->redeemed_at)->format('j M H:i') }}
              </div>
            </div>
            <div class="p">-{{ number_format($r->points_used) }}</div>
          </div>
        @empty
          <div class="empty" style="padding:20px 0">ยังไม่มีรายการ</div>
        @endforelse

        <a href="{{ route('redeem.history') }}" class="btn btn-sm" style="width:100%;margin-top:12px;justify-content:center">📋
          ดูประวัติทั้งหมด
        </a>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var pointValue = {{ $pointValue }};
  var pointsEl = document.getElementById('points');
  var cashEl = document.getElementById('cashValue');
  var hintEl = document.getElementById('pointHint');
  var goodsBlock = document.getElementById('goodsBlock');
  var form = document.getElementById('redeemForm');

  if (!form) return;

  /* คำนวณมูลค่าเงินตามแต้มที่กรอก */
  function updateCash() {
    var pts = parseInt(pointsEl.value || '0', 10);
    cashEl.textContent = (pts * pointValue).toLocaleString('th-TH', {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    }) + ' บาท';

    var sel = document.querySelector('input[name=wallet_id]:checked');
    var bal = sel ? parseInt(sel.dataset.balance, 10) : 0;

    if (pts > bal) {
      hintEl.textContent = 'แต้มไม่พอ ลูกค้ามี ' + bal.toLocaleString() + ' แต้ม';
      hintEl.style.color = '#D32F2F';
    } else {
      hintEl.textContent = 'ลูกค้ามี ' + bal.toLocaleString() + ' แต้ม';
      hintEl.style.color = '';
    }
  }

  pointsEl.addEventListener('input', updateCash);

  /* เลือกกระเป๋าแต้ม */
  document.querySelectorAll('input[name=wallet_id]').forEach(function (r) {
    r.addEventListener('change', function () {
      document.querySelectorAll('.wallet-pick').forEach(w => w.classList.remove('sel'));
      r.closest('.wallet-pick').classList.add('sel');
      updateCash();
    });
  });

  /*
   * แสดงช่องเลือกสินค้าเฉพาะตอนแลกสินค้า
   * ปิด input ที่ซ่อนอยู่ด้วย disabled เพื่อไม่ให้ถูกส่งไปกับฟอร์ม
   * (ระบบบังคับว่าแลกบริการ/ส่วนลด/เงินสด ต้องไม่มีรายการสินค้า)
   */
  function toggleGoods() {
    var type = document.querySelector('input[name=redeem_type]:checked');
    var isGoods = type && type.value === 'goods';

    goodsBlock.style.display = isGoods ? '' : 'none';

    goodsBlock.querySelectorAll('input').forEach(function (el) {
      el.disabled = !isGoods;
    });
  }

  document.querySelectorAll('input[name=redeem_type]').forEach(function (r) {
    r.addEventListener('change', toggleGoods);
  });

  /* ตัดรายการสินค้าที่จำนวนเป็น 0 ออกก่อนส่ง */
  form.addEventListener('submit', function () {
    goodsBlock.querySelectorAll('.qtyInput').forEach(function (q) {
      if (parseInt(q.value || '0', 10) <= 0) {
        var row = q.closest('.stock-row');
        row.querySelectorAll('input').forEach(el => el.disabled = true);
      }
    });
  });

  toggleGoods();
  updateCash();
})();
</script>
@endpush
