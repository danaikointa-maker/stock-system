@extends('layouts.app')
@section('title', 'สมัครสมาชิกร้านใหม่')

@push('head')
<style>
  .pkg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
  .pkg{
    border:2px solid var(--line);border-radius:14px;padding:16px;cursor:pointer;
    transition:border-color .15s,background .15s;position:relative;
  }
  .pkg:has(input:checked){border-color:var(--brand);background:#FFF6F2}
  .pkg input{position:absolute;opacity:0}
  .pkg .nm{font-size:15px;font-weight:800;margin-bottom:3px}
  .pkg .tag{font-size:11.5px;color:var(--muted);margin-bottom:10px;min-height:16px}
  .pkg .price{font-size:26px;font-weight:800;color:var(--brand);line-height:1.2}
  .pkg .price small{font-size:12px;color:var(--muted);font-weight:400}
  .pkg .spec{font-size:12px;color:var(--muted);margin-top:9px;line-height:1.8;
             border-top:1px dashed var(--line);padding-top:9px}
  .pkg .spec b{color:var(--ink)}
  .calc-box{
    background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff;
    border-radius:14px;padding:17px 19px;margin-top:16px;
  }
  .calc-box .row{display:flex;justify-content:space-between;padding:5px 0;font-size:13.5px}
  .calc-box .row.big{font-size:17px;font-weight:800;border-top:1px solid rgba(255,255,255,.3);
                     margin-top:7px;padding-top:11px}
</style>
@endpush

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h1 style="margin:0">สมัครสมาชิกร้านใหม่</h1>
  <a href="{{ route('subscriptions.index') }}" class="btn btn-sm">กลับ</a>
</div>

@if($errors->any())
  <div class="alert a-bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

@if($shops->isEmpty())
  <div class="alert a-info">
    ไม่มีร้านที่สมัครได้<br>
    <small>ร้านต้องอยู่ในสายงานของคุณ เป็นระดับร้านค้า และยังไม่มีสมาชิกที่ใช้งานอยู่<br>
    ถ้ายังไม่มีร้าน ให้ไปสร้างที่เมนู "หน่วยงานในสังกัด" ก่อน</small>
  </div>
@else
<form method="POST" action="{{ route('subscriptions.store') }}" id="subForm">
  @csrf

  <div class="card" style="margin-bottom:16px">
    <div class="body">
      <h3 style="margin:0 0 13px;font-size:14px">1. เลือกร้านค้า</h3>
      <div class="field" style="margin-bottom:0">
        <select class="input" name="shop_node_id" required>
          <option value="">— เลือกร้าน —</option>
          @foreach($shops as $shop)
            <option value="{{ $shop->id }}" @selected(old('shop_node_id')==$shop->id)>
              {{ $shop->code }} · {{ $shop->name }}
            </option>
          @endforeach
        </select>
        <p class="hint">แสดงเฉพาะร้านในสายงานคุณที่ยังไม่มีสมาชิก</p>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom:16px">
    <div class="body">
      <h3 style="margin:0 0 4px;font-size:14px">2. เลือกแพ็กเกจ</h3>
      <p class="hint" style="margin-bottom:14px">ระบบคำนวณวันหมดอายุและคอมมิชชั่นให้อัตโนมัติ</p>

      <div class="pkg-grid">
        @foreach($packages as $p)
          <label class="pkg">
            <input type="radio" name="package_id" value="{{ $p->id }}" required
                   data-months="{{ $p->duration_months }}"
                   data-price="{{ $p->price }}"
                   data-limit="{{ $p->monthly_point_limit }}"
                   data-comm="{{ $p->agent_commission_pct }}"
                   @checked(old('package_id')==$p->id)>
            <div class="nm">{{ $p->name }}</div>
            <div class="tag">{{ $p->tagline }}</div>
            <div class="price">
              {{ number_format($p->price, 0) }} <small>บาท</small>
            </div>
            <div class="spec">
              อายุ <b>{{ $p->duration_months }} เดือน</b><br>
              รับแลก <b>{{ number_format($p->monthly_point_limit) }} แต้ม/เดือน</b><br>
              คอมฯ ตัวแทน <b>{{ number_format($p->agent_commission_pct, 0) }}%</b>
              @if($p->allow_rollover)<br>ยกยอดแต้มข้ามเดือนได้@endif
              @if($p->allow_cash_redeem)<br>แลกเป็นเงินสดได้@endif
            </div>
          </label>
        @endforeach
      </div>

      <div class="calc-box" id="calcBox" style="display:none">
        <div class="row"><span>วันเริ่มสมาชิก</span><span id="cStart">—</span></div>
        <div class="row"><span>วันหมดอายุ</span><span id="cEnd">—</span></div>
        <div class="row"><span>วงเงินรับแลก</span><span id="cLimit">—</span></div>
        <div class="row"><span>คอมมิชชั่นตัวแทน</span><span id="cComm">—</span></div>
        <div class="row big"><span>ร้านต้องชำระ</span><span id="cPrice">—</span></div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom:16px">
    <div class="body">
      <h3 style="margin:0 0 13px;font-size:14px">3. รายละเอียดเพิ่มเติม</h3>
      <div class="grid g2">
        <div class="field">
          <label for="starts_on">วันเริ่มสมาชิก</label>
          <input class="input" type="date" id="starts_on" name="starts_on"
                 value="{{ old('starts_on', now()->toDateString()) }}">
        </div>
        <div class="field">
          <label>ต่ออายุอัตโนมัติ</label>
          <label style="display:flex;align-items:center;gap:9px;padding:14px 0;font-size:13.5px">
            <input type="hidden" name="auto_renew" value="0">
            <input type="checkbox" name="auto_renew" value="1" style="width:17px;height:17px;accent-color:var(--brand)"
                   @checked(old('auto_renew'))>
            เตือนให้ต่ออายุเมื่อใกล้หมด
          </label>
        </div>
      </div>
      <div class="field" style="margin-bottom:0">
        <label for="note">หมายเหตุ</label>
        <input class="input" type="text" id="note" name="note" maxlength="1000" value="{{ old('note') }}">
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-p" style="padding:14px 32px;font-size:15px">
    สร้างใบสมัคร
  </button>
</form>
@endif
@endsection

@push('scripts')
<script>
/* คำนวณสรุปให้ตัวแทนเห็นทันทีที่เลือกแพ็กเกจ */
(function () {
  var box = document.getElementById('calcBox');
  var startEl = document.getElementById('starts_on');
  if (!box) return;

  function fmt(d) {
    return d.toLocaleDateString('th-TH', { day:'numeric', month:'short', year:'numeric' });
  }

  function update() {
    var sel = document.querySelector('input[name=package_id]:checked');
    if (!sel) { box.style.display = 'none'; return; }

    var months = parseInt(sel.dataset.months, 10);
    var price  = parseFloat(sel.dataset.price);
    var limit  = parseInt(sel.dataset.limit, 10);
    var pct    = parseFloat(sel.dataset.comm);

    var start = startEl && startEl.value ? new Date(startEl.value) : new Date();
    var end = new Date(start);
    end.setMonth(end.getMonth() + months);
    end.setDate(end.getDate() - 1);

    document.getElementById('cStart').textContent = fmt(start);
    document.getElementById('cEnd').textContent   = fmt(end);
    document.getElementById('cLimit').textContent = limit.toLocaleString() + ' แต้ม/เดือน';
    document.getElementById('cComm').textContent  =
      (price * pct / 100).toLocaleString('th-TH', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' บาท';
    document.getElementById('cPrice').textContent =
      price.toLocaleString('th-TH', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' บาท';

    box.style.display = '';
  }

  document.querySelectorAll('input[name=package_id]').forEach(function (r) {
    r.addEventListener('change', update);
  });
  if (startEl) startEl.addEventListener('change', update);
  update();
})();
</script>
@endpush
