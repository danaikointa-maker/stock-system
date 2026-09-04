@extends('layouts.app')
@section('title', '🛒 ' . $po->po_no)
@section('crumb', 'บัญชี · ใบสั่งซื้อ · ' . $po->po_no)

@section('content')
<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <a href="{{ route('accounting.po') }}" class="btn">⬅️ กลับ</a>
  <button class="btn btn-blue" onclick="window.print()">🖨️ พิมพ์</button>
  @if($po->status === 'draft')
    <form method="POST" action="{{ route('accounting.po.approve', $po) }}" style="display:inline">
      @csrf @method('PATCH')
      <button class="btn btn-ok" onclick="return confirm('✅ อนุมัติใบสั่งซื้อ?')">✅ อนุมัติ</button>
    </form>
  @endif
</div>

<div class="card"><div style="padding:24px">
  <div style="text-align:center;border-bottom:3px double var(--line);padding-bottom:16px;margin-bottom:20px">
    <div style="font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);font-weight:700">ใบสั่งซื้อ / Purchase Order</div>
    <h2 style="margin-top:6px">{{ $po->po_no }}</h2>
    <span class="badge {{ $po->statusBadge() }}">{{ $po->statusLabel() }}</span>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px">
    <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
      <div style="font-size:11px;color:var(--muted);font-weight:700">ผู้ขาย</div>
      <div style="font-size:14px;font-weight:700;margin-top:4px">{{ $po->vendor_name }}</div>
      @if($po->vendor_tax_id)<div style="font-size:12px">Tax: {{ $po->vendor_tax_id }}</div>@endif
    </div>
    <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
      <div style="font-size:11px;color:var(--muted);font-weight:700">วันที่</div>
      <div style="font-size:13px;margin-top:4px">สั่งซื้อ: {{ $po->order_date->format('d/m/Y') }}</div>
      @if($po->expected_date)<div style="font-size:13px">คาดรับ: {{ $po->expected_date->format('d/m/Y') }}</div>@endif
    </div>
    <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
      <div style="font-size:11px;color:var(--muted);font-weight:700">ภาษี</div>
      <div style="font-size:13px;margin-top:4px">VAT: {{ $po->vat_rate }}%</div>
      @if($po->wht_rate > 0)<div style="font-size:13px">WHT: {{ $po->wht_rate }}%</div>@endif
    </div>
  </div>

  <table>
    <thead><tr><th>#</th><th>สินค้า/รายละเอียด</th><th class="num">จำนวน</th><th class="num">ราคา/หน่วย</th><th class="num">ยอดรวม</th><th class="num">รับแล้ว</th></tr></thead>
    <tbody>
    @foreach($po->items as $i => $item)
      <tr>
        <td>{{ $i+1 }}</td>
        <td>{{ $item->description }}</td>
        <td class="num">{{ number_format($item->qty,2) }}</td>
        <td class="num">{{ number_format($item->unit_price,2) }}</td>
        <td class="num"><b>{{ number_format($item->line_total,2) }}</b></td>
        <td class="num">{{ number_format($item->received_qty,2) }}</td>
      </tr>
    @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="4" style="text-align:right">ยอดก่อน VAT:</td><td class="num">{{ number_format($po->subtotal,2) }}</td><td></td></tr>
      <tr><td colspan="4" style="text-align:right">VAT:</td><td class="num">{{ number_format($po->vat_amount,2) }}</td><td></td></tr>
      @if($po->wht_amount > 0)<tr><td colspan="4" style="text-align:right;color:var(--bad-dark)">หัก ณ ที่จ่าย:</td><td class="num" style="color:var(--bad-dark)">-{{ number_format($po->wht_amount,2) }}</td><td></td></tr>@endif
      <tr style="background:#f0fdf4"><td colspan="4" style="text-align:right"><b>จ่ายสุทธิ:</b></td><td class="num"><b style="font-size:16px">{{ number_format($po->net_total,2) }}</b></td><td></td></tr>
    </tfoot>
  </table>
</div></div>
@endsection
