@extends('layouts.app')
@section('title', '📋 ' . $quotation->doc_no)
@section('crumb', 'บัญชี · ใบเสนอราคา · ' . $quotation->doc_no)

@section('content')
<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <a href="{{ route('accounting.quotations') }}" class="btn">⬅️ กลับ</a>
  <button class="btn btn-blue" onclick="window.print()">🖨️ พิมพ์</button>
  @if($quotation->status === 'draft')
    <form method="POST" action="{{ route('accounting.quotations.send', $quotation) }}" style="display:inline">
      @csrf @method('PATCH')<button class="btn btn-p">📤 ส่งให้ลูกค้า</button>
    </form>
  @endif
  @if(in_array($quotation->status, ['sent','draft']))
    <form method="POST" action="{{ route('accounting.quotations.accept', $quotation) }}" style="display:inline">
      @csrf @method('PATCH')<button class="btn btn-ok">✅ ลูกค้าตกลง</button>
    </form>
  @endif
  @if(in_array($quotation->status, ['accepted','sent']))
    <form method="POST" action="{{ route('accounting.quotations.convert', $quotation) }}" style="display:inline">
      @csrf @method('PATCH')<button class="btn btn-approve">🔄 แปลงเป็นบิลเรียกเก็บ</button>
    </form>
  @endif
</div>

<div class="card"><div style="padding:24px">
  <div style="text-align:center;border-bottom:3px double var(--line);padding-bottom:16px;margin-bottom:20px">
    <div style="font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);font-weight:700">ใบเสนอราคา / Quotation</div>
    <h2 style="margin-top:6px">{{ $quotation->doc_no }}</h2>
    <span class="badge {{ $quotation->statusBadge() }}">{{ $quotation->statusLabel() }}</span>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
    <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
      <div style="font-size:11px;color:var(--muted);font-weight:700">ลูกค้า</div>
      <div style="font-size:14px;font-weight:700;margin-top:4px">{{ $quotation->customer_name }}</div>
      @if($quotation->customer_address)<div style="font-size:12px">{{ $quotation->customer_address }}</div>@endif
      @if($quotation->customer_tax_id)<div style="font-size:12px">Tax ID: {{ $quotation->customer_tax_id }}</div>@endif
    </div>
    <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
      <div style="font-size:11px;color:var(--muted);font-weight:700">วันที่</div>
      <div style="font-size:13px;margin-top:4px">ออก: {{ $quotation->issue_date->format('d/m/Y') }}</div>
      <div style="font-size:13px">ใช้ได้ถึง: {{ $quotation->valid_until->format('d/m/Y') }}</div>
    </div>
  </div>

  <table>
    <thead><tr><th>#</th><th>รายละเอียด</th><th class="num">จำนวน</th><th class="num">ราคา/หน่วย</th><th class="num">ยอดรวม</th></tr></thead>
    <tbody>
    @foreach($quotation->items as $i => $item)
      <tr><td>{{ $i+1 }}</td><td>{{ $item->description }}</td><td class="num">{{ number_format($item->qty,2) }}</td><td class="num">{{ number_format($item->unit_price,2) }}</td><td class="num"><b>{{ number_format($item->line_total,2) }}</b></td></tr>
    @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="4" style="text-align:right">ยอดก่อน VAT:</td><td class="num">{{ number_format($quotation->subtotal,2) }}</td></tr>
      <tr><td colspan="4" style="text-align:right">VAT ({{ $quotation->vat_rate }}%):</td><td class="num">{{ number_format($quotation->vat_amount,2) }}</td></tr>
      <tr style="background:#f0fdf4"><td colspan="4" style="text-align:right"><b>รวมทั้งหมด:</b></td><td class="num"><b style="font-size:16px">{{ number_format($quotation->total,2) }}</b></td></tr>
    </tfoot>
  </table>

  @if($quotation->terms)
  <div style="margin-top:16px;padding:12px;background:#eff6ff;border-radius:8px;font-size:13px"><b>📝 เงื่อนไข:</b> {{ $quotation->terms }}</div>
  @endif
  @if($quotation->convertedInvoice)
  <div style="margin-top:16px;padding:12px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;font-size:13px">
    🔄 แปลงเป็นบิลเรียกเก็บแล้ว: <a href="{{ route('accounting.invoices.show', $quotation->convertedInvoice) }}"><code>{{ $quotation->convertedInvoice->invoice_no }}</code></a>
  </div>
  @endif
</div></div>
@endsection
