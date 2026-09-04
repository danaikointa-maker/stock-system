@extends('layouts.app')
@section('title', '🧾 ' . $taxInvoice->tax_invoice_no)
@section('crumb', 'บัญชี · ใบกำกับภาษี · ' . $taxInvoice->tax_invoice_no)

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('accounting.tax-invoices') }}" class="btn">⬅️ กลับ</a>
  <button class="btn btn-blue" onclick="window.print()">🖨️ พิมพ์</button>
</div>
<div class="card">
  <div style="padding:24px">
    <div style="text-align:center;margin-bottom:24px;border-bottom:3px double var(--line);padding-bottom:16px">
      <div style="font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);font-weight:700">{{ $taxInvoice->typeLabel() }}</div>
      <div style="font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)">TAX INVOICE</div>
      <h2 style="margin-top:8px">{{ $taxInvoice->tax_invoice_no }}</h2>
      <div style="font-size:13px;color:var(--muted)">วันที่: {{ $taxInvoice->issue_date->format('d/m/Y') }}</div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
      <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
        <div style="font-size:11px;color:var(--muted);font-weight:700">ผู้ขาย / SELLER</div>
        <div style="font-size:14px;font-weight:700;margin-top:4px">{{ $taxInvoice->node->name ?? '' }}</div>
      </div>
      <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
        <div style="font-size:11px;color:var(--muted);font-weight:700">ผู้ซื้อ / BUYER</div>
        <div style="font-size:14px;font-weight:700;margin-top:4px">{{ $taxInvoice->buyer_name }}</div>
        @if($taxInvoice->buyer_address)<div style="font-size:12px;color:var(--muted)">{{ $taxInvoice->buyer_address }}</div>@endif
        @if($taxInvoice->buyer_tax_id)<div style="font-size:12px;color:var(--muted)">เลขผู้เสียภาษี: {{ $taxInvoice->buyer_tax_id }}</div>@endif
        @if($taxInvoice->buyer_branch)<div style="font-size:12px;color:var(--muted)">สาขา: {{ $taxInvoice->buyer_branch }}</div>@endif
      </div>
    </div>

    @if($taxInvoice->invoice && $taxInvoice->invoice->items->isNotEmpty())
    <table>
      <thead><tr><th>#</th><th>รายการ</th><th class="num">จำนวน</th><th class="num">ราคา/หน่วย</th><th class="num">จำนวนเงิน</th></tr></thead>
      <tbody>
      @foreach($taxInvoice->invoice->items as $i => $item)
        <tr><td>{{ $i+1 }}</td><td>{{ $item->description }}</td><td class="num">{{ number_format($item->qty,2) }}</td><td class="num">{{ number_format($item->unit_price,2) }}</td><td class="num">{{ number_format($item->amount,2) }}</td></tr>
      @endforeach
      </tbody>
    </table>
    @endif

    <div style="margin-top:20px;display:grid;grid-template-columns:1fr 300px">
      <div></div>
      <div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line)"><span>รวมก่อน VAT</span><b>{{ number_format($taxInvoice->subtotal,2) }}</b></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line)"><span>VAT {{ $taxInvoice->vat_rate }}%</span><b>{{ number_format($taxInvoice->vat_amount,2) }}</b></div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;background:#f0fdf4;border-radius:8px;padding:10px;margin-top:4px"><span style="font-size:15px"><b>ยอดรวมทั้งสิ้น</b></span><b style="font-size:18px;color:var(--ok-dark)">{{ number_format($taxInvoice->total,2) }}</b></div>
      </div>
    </div>
  </div>
</div>
@endsection
