@extends('layouts.app')
@section('title', '💸 สร้างบิลจ่าย')
@section('crumb', 'บัญชี · สร้างบิลจ่าย')

@section('content')
<form method="POST" action="{{ route('accounting.payments.store') }}">
@csrf
<div class="grid g2">
  <div class="card">
    <h3>📋 ข้อมูลบิลจ่าย</h3>
    <div class="body">
      <div class="field"><label>เลขที่</label><input type="text" value="{{ $docNo }}" readonly style="background:#f8fafc"></div>
      <div class="field"><label>วันที่ *</label><input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required></div>
      <div class="field"><label>วิธีจ่ายเงิน</label>
        <select name="method" required>
          <option value="bank_transfer">โอนเงิน</option>
          <option value="promptpay">พร้อมเพย์</option>
          <option value="cash">เงินสด</option>
          <option value="cheque">เช็ค</option>
        </select>
      </div>
      <div class="field"><label>เลขอ้างอิง</label><input type="text" name="bank_ref" value="{{ old('bank_ref') }}"></div>
      <div class="field"><label>รายละเอียด</label><textarea name="description" rows="2">{{ old('description') }}</textarea></div>
    </div>
  </div>
  <div class="card">
    <h3>👤 ผู้รับเงิน</h3>
    <div class="body">
      <div class="field"><label>ชื่อผู้รับเงิน *</label><input type="text" name="payee_name" value="{{ old('payee_name') }}" required></div>
      <div class="field"><label>เลขผู้เสียภาษี</label><input type="text" name="payee_tax_id" value="{{ old('payee_tax_id') }}" maxlength="20"></div>
    </div>
  </div>
</div>

<div class="card">
  <h3>💰 คำนวณยอดเงิน (VAT + หัก ณ ที่จ่าย)</h3>
  <div class="body">
    <div class="grid g3">
      <div class="field">
        <label>ยอดก่อน VAT (บาท) *</label>
        <input type="number" name="income_amount" id="incomeAmount" value="{{ old('income_amount') }}" step="0.01" min="0.01" required oninput="calcAll()">
        <small style="color:var(--muted)">จำนวนเงินค่าสินค้า/บริการ ก่อน VAT</small>
      </div>
      <div class="field">
        <label>อัตรา VAT (%)</label>
        <input type="number" name="vat_rate" id="vatRate" value="{{ old('vat_rate', 7) }}" step="0.01" min="0" max="100" oninput="calcAll()">
      </div>
      <div class="field">
        <label>ประเภทเงินได้</label>
        <input type="text" name="income_type" value="{{ old('income_type') }}" placeholder="เช่น บริการ, ค่าเช่า, ค่าขนส่ง">
      </div>
    </div>
    <div class="grid g3" style="margin-top:12px">
      <div class="field">
        <label>อัตราหัก ณ ที่จ่าย (%)</label>
        <select name="wht_rate" id="whtRate" onchange="calcAll()">
          <option value="0">ไม่หัก</option>
          @foreach($whtRates as $rate => $label)
            <option value="{{ $rate }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="field"></div>
      <div class="field"></div>
    </div>

    {{-- สรุปการคำนวณ --}}
    <div style="margin-top:16px;background:#f8fafc;border:2px solid var(--line);border-radius:12px;padding:16px">
      <table style="width:100%;max-width:500px">
        <tr><td style="padding:6px 0">ยอดก่อน VAT</td><td class="num" id="dispSubtotal">0.00</td></tr>
        <tr><td style="padding:6px 0">+ VAT (<span id="dispVatPct">7</span>%)</td><td class="num" id="dispVat">0.00</td></tr>
        <tr style="border-top:2px solid var(--line)"><td style="padding:6px 0"><b>ยอดรวม (จ่ายเต็ม)</b></td><td class="num"><b id="dispTotal">0.00</b></td></tr>
        <tr style="color:var(--bad-dark)"><td style="padding:6px 0">- หัก ณ ที่จ่าย (<span id="dispWhtPct">0</span>%)</td><td class="num" id="dispWht">0.00</td></tr>
        <tr style="background:#f0fdf4"><td style="padding:10px 0;font-size:16px"><b>💵 จ่ายสุทธิ</b></td><td class="num" style="font-size:16px"><b id="dispNet" style="color:var(--ok-dark)">0.00</b></td></tr>
      </table>
    </div>

    {{-- Hidden fields --}}
    <input type="hidden" name="amount" id="hiddenAmount">
    <input type="hidden" name="wht_amount" id="hiddenWhtAmount">
    <input type="hidden" name="net_amount" id="hiddenNetAmount">
  </div>
</div>

<div style="text-align:right">
  <a href="{{ route('accounting.payments') }}" class="btn">❌ ยกเลิก</a>
  <button type="submit" class="btn btn-ship" style="padding:10px 24px;font-size:14px">💾 สร้างบิลจ่าย</button>
</div>
</form>

@push('scripts')
<script>
function calcAll() {
  var income = parseFloat(document.getElementById('incomeAmount').value) || 0;
  var vatPct = parseFloat(document.getElementById('vatRate').value) || 0;
  var whtPct = parseFloat(document.getElementById('whtRate').value) || 0;

  // คำนวณ VAT จากยอดก่อน VAT
  var vatAmount = income * vatPct / 100;
  var total = income + vatAmount;

  // คำนวณ WHT จากยอดก่อน VAT (ไม่ใช่ยอดรวม!)
  var whtAmount = income * whtPct / 100;
  var netAmount = total - whtAmount;

  // แสดงผล
  document.getElementById('dispSubtotal').textContent = income.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
  document.getElementById('dispVatPct').textContent = vatPct;
  document.getElementById('dispVat').textContent = vatAmount.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
  document.getElementById('dispTotal').textContent = total.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
  document.getElementById('dispWhtPct').textContent = whtPct;
  document.getElementById('dispWht').textContent = whtAmount.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
  document.getElementById('dispNet').textContent = netAmount.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});

  // Hidden fields
  document.getElementById('hiddenAmount').value = total.toFixed(2);
  document.getElementById('hiddenWhtAmount').value = whtAmount.toFixed(2);
  document.getElementById('hiddenNetAmount').value = netAmount.toFixed(2);
}
calcAll();
</script>
@endpush
@endsection
