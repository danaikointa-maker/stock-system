@extends('layouts.app')
@section('title', '💰 ' . $receipt->receipt_no)
@section('crumb', 'บัญชี · บิลรับ · ' . $receipt->receipt_no)

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('accounting.receipts') }}" class="btn">⬅️ กลับ</a>
  <button class="btn btn-blue" onclick="window.print()">🖨️ พิมพ์</button>
</div>
<div class="card">
  <div style="padding:24px">
    <div style="text-align:center;margin-bottom:20px">
      <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);font-weight:700">RECEIPT / ใบเสร็จรับเงิน</div>
      <h2 style="margin-top:4px">{{ $receipt->receipt_no }}</h2>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
      <div>
        <div style="font-size:11px;color:var(--muted)">วันที่</div>
        <div style="font-weight:700">{{ $receipt->receipt_date->format('d/m/Y') }}</div>
      </div>
      <div>
        <div style="font-size:11px;color:var(--muted)">วิธีรับเงิน</div>
        <div style="font-weight:700">{{ $receipt->methodLabel() }}</div>
      </div>
    </div>
    <div style="background:#f8fafc;border-radius:10px;padding:14px 18px;margin-bottom:20px">
      <div style="font-size:11px;color:var(--muted)">รับจาก</div>
      <div style="font-size:16px;font-weight:700;margin-top:4px">{{ $receipt->payer_name }}</div>
      @if($receipt->payer_tax_id)<div style="font-size:12px;color:var(--muted)">เลขผู้เสียภาษี: {{ $receipt->payer_tax_id }}</div>@endif
    </div>
    <div style="text-align:center;padding:20px;background:#f0fdf4;border-radius:12px;border:2px solid #86efac">
      <div style="font-size:12px;color:#15803d;font-weight:700">จำนวนเงินที่ได้รับ</div>
      <div style="font-size:32px;font-weight:800;color:#059669">{{ number_format($receipt->amount, 2) }}</div>
      <div style="font-size:12px;color:#6b7280">บาท</div>
    </div>
    @if($receipt->invoice)
      <div style="margin-top:16px;font-size:12px;color:var(--muted)">
        อ้างอิงบิลเรียกเก็บ: <a href="{{ route('accounting.invoices.show', $receipt->invoice) }}"><code>{{ $receipt->invoice->invoice_no }}</code></a>
      </div>
    @endif
    @if($receipt->bank_ref)<div style="margin-top:8px;font-size:12px;color:var(--muted)">เลขอ้างอิง: {{ $receipt->bank_ref }}</div>@endif
    @if($receipt->notes)<div style="margin-top:8px;font-size:12px;color:var(--muted)">หมายเหตุ: {{ $receipt->notes }}</div>@endif
  </div>
</div>
@endsection
