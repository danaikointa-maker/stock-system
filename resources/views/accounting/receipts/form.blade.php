@extends('layouts.app')
@section('title', '💰 สร้างบิลรับ')
@section('crumb', 'บัญชี · สร้างบิลรับ')

@section('content')
<form method="POST" action="{{ route('accounting.receipts.store') }}">
@csrf
<div class="grid g2">
  <div class="card">
    <h3>📋 ข้อมูลบิลรับ</h3>
    <div class="body">
      <div class="field"><label>เลขที่</label><input type="text" value="{{ $docNo }}" readonly style="background:#f8fafc"></div>
      <div class="field"><label>วันที่ *</label><input type="date" name="receipt_date" value="{{ old('receipt_date', now()->toDateString()) }}" required></div>
      <div class="field"><label>จำนวนเงิน *</label><input type="number" name="amount" value="{{ old('amount', $invoice?->balance) }}" step="0.01" min="0.01" required></div>
      <div class="field"><label>วิธีรับเงิน</label>
        <select name="method" required>
          <option value="bank_transfer">โอนเงิน</option>
          <option value="promptpay">พร้อมเพย์</option>
          <option value="cash">เงินสด</option>
          <option value="cheque">เช็ค</option>
          <option value="credit_card">บัตรเครดิต</option>
        </select>
      </div>
      <div class="field"><label>เลขอ้างอิง (ธนาคาร/เช็ค)</label><input type="text" name="bank_ref" value="{{ old('bank_ref') }}"></div>
      <div class="field"><label>หมายเหตุ</label><textarea name="notes" rows="2">{{ old('notes') }}</textarea></div>
    </div>
  </div>
  <div class="card">
    <h3>👤 ผู้จ่ายเงิน</h3>
    <div class="body">
      <div class="field"><label>ชื่อผู้จ่าย *</label><input type="text" name="payer_name" value="{{ old('payer_name', $invoice?->customer_name) }}" required></div>
      <div class="field"><label>เลขผู้เสียภาษี</label><input type="text" name="payer_tax_id" value="{{ old('payer_tax_id') }}" maxlength="20"></div>
      <div class="field"><label>บิลเรียกเก็บ (ถ้ามี)</label>
        <select name="invoice_id">
          <option value="">— ไม่ระบุ —</option>
          @foreach($invoices as $inv)
            <option value="{{ $inv->id }}" {{ $invoice?->id == $inv->id ? 'selected' : '' }}>
              {{ $inv->invoice_no }} — {{ $inv->customer_name }} (ค้าง {{ number_format($inv->balance, 2) }})
            </option>
          @endforeach
        </select>
      </div>
    </div>
  </div>
</div>
<div style="text-align:right">
  <a href="{{ route('accounting.receipts') }}" class="btn">❌ ยกเลิก</a>
  <button type="submit" class="btn btn-ok" style="padding:10px 24px;font-size:14px">💾 สร้างบิลรับ</button>
</div>
</form>
@endsection
