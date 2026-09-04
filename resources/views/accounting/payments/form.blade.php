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
      <div class="field"><label>จำนวนเงิน *</label><input type="number" name="amount" id="payAmount" value="{{ old('amount') }}" step="0.01" min="0.01" required></div>
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
    <h3>👤 ผู้รับเงิน + หัก ณ ที่จ่าย</h3>
    <div class="body">
      <div class="field"><label>ชื่อผู้รับเงิน *</label><input type="text" name="payee_name" value="{{ old('payee_name') }}" required></div>
      <div class="field"><label>เลขผู้เสียภาษี</label><input type="text" name="payee_tax_id" value="{{ old('payee_tax_id') }}" maxlength="20"></div>
      <hr style="margin:16px 0;border:none;border-top:1px solid var(--line)">
      <div class="field"><label>ประเภทเงินได้</label><input type="text" name="income_type" value="{{ old('income_type') }}" placeholder="เช่น บริการ, ค่าเช่า, ค่าขนส่ง"></div>
      <div class="field"><label>อัตราหัก ณ ที่จ่าย (%)</label>
        <select name="wht_rate" id="whtRate">
          <option value="">ไม่หัก</option>
          @foreach($whtRates as $rate => $label)
            <option value="{{ $rate }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div id="whtPreview" style="display:none;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px;margin-top:8px">
        <div style="font-size:12px;color:#92400e">
          <div>ยอดก่อนหัก: <b id="whtBase">0</b> บาท</div>
          <div>หัก ณ ที่จ่าย (<span id="whtPct">0</span>%): <b id="whtAmt">0</b> บาท</div>
          <div style="font-size:14px;margin-top:4px">จ่ายสุทธิ: <b id="whtNet" style="color:#059669">0</b> บาท</div>
        </div>
      </div>
    </div>
  </div>
</div>
<div style="text-align:right">
  <a href="{{ route('accounting.payments') }}" class="btn">❌ ยกเลิก</a>
  <button type="submit" class="btn btn-ship" style="padding:10px 24px;font-size:14px">💾 สร้างบิลจ่าย</button>
</div>
</form>

@push('scripts')
<script>
function calcWht() {
  var amt = parseFloat(document.getElementById('payAmount').value) || 0;
  var rate = parseFloat(document.getElementById('whtRate').value) || 0;
  var wht = amt * rate / 100;
  var net = amt - wht;
  document.getElementById('whtPreview').style.display = rate > 0 ? 'block' : 'none';
  document.getElementById('whtBase').textContent = amt.toLocaleString(undefined,{minimumFractionDigits:2});
  document.getElementById('whtPct').textContent = rate;
  document.getElementById('whtAmt').textContent = wht.toLocaleString(undefined,{minimumFractionDigits:2});
  document.getElementById('whtNet').textContent = net.toLocaleString(undefined,{minimumFractionDigits:2});
}
document.getElementById('payAmount').addEventListener('input', calcWht);
document.getElementById('whtRate').addEventListener('change', calcWht);
</script>
@endpush
@endsection
