@extends('layouts.app')
@section('title', '💸 ' . $payment->payment_no)
@section('crumb', 'บัญชี · บิลจ่าย · ' . $payment->payment_no)

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('accounting.payments') }}" class="btn">⬅️ กลับ</a>
  <button class="btn btn-blue" onclick="window.print()">🖨️ พิมพ์</button>
</div>
<div class="card">
  <div style="padding:24px">
    <div style="text-align:center;margin-bottom:20px">
      <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);font-weight:700">PAYMENT VOUCHER / ใบสำคัญจ่าย</div>
      <h2 style="margin-top:4px">{{ $payment->payment_no }}</h2>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
      <div><div style="font-size:11px;color:var(--muted)">วันที่</div><div style="font-weight:700">{{ $payment->payment_date->format('d/m/Y') }}</div></div>
      <div><div style="font-size:11px;color:var(--muted)">วิธีจ่าย</div><div style="font-weight:700">{{ $payment->methodLabel() }}</div></div>
    </div>
    <div style="background:#f8fafc;border-radius:10px;padding:14px 18px;margin-bottom:20px">
      <div style="font-size:11px;color:var(--muted)">จ่ายให้</div>
      <div style="font-size:16px;font-weight:700;margin-top:4px">{{ $payment->payee_name }}</div>
      @if($payment->payee_tax_id)<div style="font-size:12px;color:var(--muted)">เลขผู้เสียภาษี: {{ $payment->payee_tax_id }}</div>@endif
    </div>
    <div style="text-align:center;padding:20px;background:#fef2f2;border-radius:12px;border:2px solid #fca5a5">
      <div style="font-size:12px;color:#b91c1c;font-weight:700">จำนวนเงินที่จ่าย</div>
      <div style="font-size:32px;font-weight:800;color:#dc2626">{{ number_format($payment->amount, 2) }}</div>
      <div style="font-size:12px;color:#6b7280">บาท</div>
    </div>
    @if($payment->description)<div style="margin-top:16px;font-size:12px;color:var(--muted)">รายละเอียด: {{ $payment->description }}</div>@endif
    @if($payment->bank_ref)<div style="margin-top:8px;font-size:12px;color:var(--muted)">เลขอ้างอิง: {{ $payment->bank_ref }}</div>@endif
  </div>
</div>

@if($payment->withholdingTax)
<div class="card">
  <h3>📋 ใบหัก ณ ที่จ่าย <a href="{{ route('accounting.wht.show', $payment->withholdingTax) }}" class="btn btn-sm btn-view">👁️ ดู</a></h3>
  <div class="body">
    <table style="width:auto">
      <tr><td style="padding:4px 16px 4px 0;color:var(--muted)">เลขที่</td><td style="font-weight:700">{{ $payment->withholdingTax->wht_no }}</td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:var(--muted)">อัตราหัก</td><td style="font-weight:700">{{ $payment->withholdingTax->wht_rate }}%</td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:var(--muted)">จำนวนหัก</td><td style="font-weight:700;color:var(--bad-dark)">{{ number_format($payment->withholdingTax->wht_amount, 2) }}</td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:var(--muted)">จ่ายสุทธิ</td><td style="font-weight:700;color:var(--ok-dark)">{{ number_format($payment->withholdingTax->net_amount, 2) }}</td></tr>
    </table>
  </div>
</div>
@endif
@endsection
