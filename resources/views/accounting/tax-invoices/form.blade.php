@extends('layouts.app')
@section('title', '🧾 สร้างใบกำกับภาษี')
@section('crumb', 'บัญชี · สร้างใบกำกับภาษี')

@section('content')
<form method="POST" action="{{ route('accounting.tax-invoices.store') }}">
@csrf
<div class="grid g2">
  <div class="card">
    <h3>📋 ข้อมูลใบกำกับภาษี</h3>
    <div class="body">
      <div class="field"><label>เลขที่</label><input type="text" value="{{ $docNo }}" readonly style="background:#f8fafc"></div>
      <div class="field"><label>วันที่ *</label><input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required></div>
      <div class="field"><label>ประเภท</label>
        <select name="type" required>
          <option value="full">ใบกำกับภาษีเต็มรูป</option>
          <option value="simplified">ใบกำกับภาษีอย่างย่อ</option>
          <option value="revised">ใบกำกับภาษีแก้ไข</option>
        </select>
      </div>
      <div class="field"><label>อัตรา VAT (%)</label><input type="number" name="vat_rate" value="{{ old('vat_rate', $invoice?->vat_rate ?? 7) }}" step="0.01" required></div>
      @if(!$invoice)
        <div class="field"><label>ยอดก่อน VAT</label><input type="number" name="subtotal" value="{{ old('subtotal') }}" step="0.01" required></div>
      @endif
      <div class="field"><label>หมายเหตุ</label><textarea name="notes" rows="2">{{ old('notes') }}</textarea></div>
      @if($invoice)
        <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
      @else
        <div class="field"><label>อ้างอิงบิลเรียกเก็บ (ถ้ามี)</label>
          <select name="invoice_id">
            <option value="">— ไม่ระบุ —</option>
            @foreach($invoices as $inv)
              <option value="{{ $inv->id }}">{{ $inv->invoice_no }} — {{ $inv->customer_name }}</option>
            @endforeach
          </select>
        </div>
      @endif
    </div>
  </div>
  <div class="card">
    <h3>👤 ข้อมูลผู้ซื้อ</h3>
    <div class="body">
      <div class="field"><label>ชื่อผู้ซื้อ *</label><input type="text" name="buyer_name" value="{{ old('buyer_name', $invoice?->customer_name) }}" required></div>
      <div class="field"><label>ที่อยู่</label><textarea name="buyer_address" rows="2">{{ old('buyer_address', $invoice?->customer_address) }}</textarea></div>
      <div class="field"><label>เลขผู้เสียภาษี</label><input type="text" name="buyer_tax_id" value="{{ old('buyer_tax_id', $invoice?->customer_tax_id) }}" maxlength="20"></div>
      <div class="field"><label>สาขา</label><input type="text" name="buyer_branch" value="{{ old('buyer_branch') }}" placeholder="สำนักงานใหญ่"></div>
    </div>
  </div>
</div>
<div style="text-align:right">
  <a href="{{ route('accounting.tax-invoices') }}" class="btn">❌ ยกเลิก</a>
  <button type="submit" class="btn btn-view" style="padding:10px 24px;font-size:14px">💾 สร้างใบกำกับภาษี</button>
</div>
</form>
@endsection
