@extends('layouts.app')
@section('title', '📄 ' . $invoice->invoice_no)
@section('crumb', 'บัญชี · บิลเรียกเก็บ · ' . $invoice->invoice_no)

@section('content')

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <a href="{{ route('accounting.invoices') }}" class="btn">⬅️ กลับ</a>
  @if($invoice->status !== 'void' && $invoice->status !== 'paid')
    <a href="{{ route('accounting.receipts.create', ['invoice' => $invoice->id]) }}" class="btn btn-ok">💰 รับเงิน</a>
    <a href="{{ route('accounting.tax-invoices.create', ['invoice' => $invoice->id]) }}" class="btn btn-view">🧾 ออกใบกำกับภาษี</a>
  @endif
  @if($invoice->status !== 'void')
    <form method="POST" action="{{ route('accounting.invoices.void', $invoice) }}" style="display:inline" onsubmit="return confirm('ยกเลิกบิลนี้?')">
      @csrf @method('DELETE')
      <button class="btn btn-d">🚫 ยกเลิกบิล</button>
    </form>
  @endif
  <button class="btn btn-blue" onclick="window.print()">🖨️ พิมพ์</button>
</div>

<div class="card" id="printArea">
  <div style="padding:24px">
    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px">
      <div>
        <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);font-weight:700">INVOICE / บิลเรียกเก็บ</div>
        <h2 style="font-size:24px;margin-top:4px">{{ $invoice->invoice_no }}</h2>
        <div style="font-size:13px;color:var(--muted)">{{ $invoice->node->name ?? '' }}</div>
      </div>
      <div style="text-align:right">
        <span class="badge {{ $invoice->statusBadge() }}" style="font-size:13px;padding:5px 14px">{{ $invoice->statusLabel() }}</span>
        <div style="margin-top:8px;font-size:12px;color:var(--muted)">
          วันที่: {{ $invoice->invoice_date->format('d/m/Y') }}<br>
          ครบกำหนด: {{ $invoice->due_date->format('d/m/Y') }}
        </div>
      </div>
    </div>

    {{-- Customer --}}
    <div style="background:#f8fafc;border-radius:10px;padding:14px 18px;margin-bottom:20px">
      <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase">เรียน / Bill To</div>
      <div style="font-size:15px;font-weight:700;margin-top:4px">{{ $invoice->customer_name }}</div>
      @if($invoice->customer_address)<div style="font-size:12px;color:var(--muted);margin-top:2px">{{ $invoice->customer_address }}</div>@endif
      @if($invoice->customer_tax_id)<div style="font-size:12px;color:var(--muted)">เลขผู้เสียภาษี: {{ $invoice->customer_tax_id }}</div>@endif
    </div>

    {{-- Items --}}
    <table>
      <thead><tr><th>#</th><th>รายการ</th><th class="num">จำนวน</th><th class="num">ราคา/หน่วย</th><th class="num">จำนวนเงิน</th></tr></thead>
      <tbody>
      @foreach($invoice->items as $i => $item)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $item->description }}</td>
          <td class="num">{{ number_format($item->qty, 2) }}</td>
          <td class="num">{{ number_format($item->unit_price, 2) }}</td>
          <td class="num"><b>{{ number_format($item->amount, 2) }}</b></td>
        </tr>
      @endforeach
      </tbody>
      <tfoot>
        <tr><td colspan="4" class="num">รวมก่อน VAT</td><td class="num"><b>{{ number_format($invoice->subtotal, 2) }}</b></td></tr>
        <tr><td colspan="4" class="num">VAT {{ $invoice->vat_rate }}%</td><td class="num">{{ number_format($invoice->vat_amount, 2) }}</td></tr>
        <tr style="background:#f0fdf4;font-size:16px"><td colspan="4" class="num"><b>ยอดรวมทั้งสิ้น</b></td><td class="num" style="color:var(--ok-dark)"><b>{{ number_format($invoice->total, 2) }}</b></td></tr>
        @if($invoice->paid_amount > 0)
          <tr><td colspan="4" class="num">ชำระแล้ว</td><td class="num" style="color:var(--ok)">{{ number_format($invoice->paid_amount, 2) }}</td></tr>
          <tr style="background:#fef3c7"><td colspan="4" class="num"><b>ค้างชำระ</b></td><td class="num" style="color:var(--bad-dark)"><b>{{ number_format($invoice->balance, 2) }}</b></td></tr>
        @endif
      </tfoot>
    </table>

    @if($invoice->notes)
      <div style="margin-top:16px;padding:12px;background:#f8fafc;border-radius:8px;font-size:12px;color:var(--muted)">
        <b>หมายเหตุ:</b> {{ $invoice->notes }}
      </div>
    @endif
  </div>
</div>

{{-- ประวัติการชำระเงิน --}}
@if($invoice->receipts->isNotEmpty())
<div class="card">
  <h3>💰 ประวัติการชำระเงิน</h3>
  <table>
    <thead><tr><th>เลขที่บิลรับ</th><th>วันที่</th><th>ผู้จ่าย</th><th>วิธี</th><th class="num">จำนวน</th></tr></thead>
    <tbody>
    @foreach($invoice->receipts as $rcp)
      <tr>
        <td><a href="{{ route('accounting.receipts.show', $rcp) }}"><code>{{ $rcp->receipt_no }}</code></a></td>
        <td>{{ $rcp->receipt_date->format('d/m/Y') }}</td>
        <td>{{ $rcp->payer_name }}</td>
        <td>{{ $rcp->methodLabel() }}</td>
        <td class="num"><b>{{ number_format($rcp->amount, 2) }}</b></td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- ใบลดหนี้/คืนสินค้า ที่เชื่อมกับบิลนี้ --}}
@if($invoice->creditNotes && $invoice->creditNotes->isNotEmpty())
<div class="card">
  <h3>↩️ ใบลดหนี้ / คืนสินค้า</h3>
  <table>
    <thead><tr><th>เลขที่</th><th>ประเภท</th><th>เหตุผล</th><th>วันที่</th><th>สถานะ</th><th class="num">ยอดลด</th></tr></thead>
    <tbody>
    @foreach($invoice->creditNotes as $cn)
      <tr>
        <td><a href="{{ route('accounting.credit.show', $cn) }}"><code>{{ $cn->doc_no }}</code></a></td>
        <td>{{ $cn->type_label }}</td>
        <td>{{ $cn->reason }}</td>
        <td>{{ $cn->created_at->format('d/m/Y') }}</td>
        <td><span class="badge {{ $cn->status === 'confirmed' ? 'ok' : ($cn->status === 'draft' ? 'warn' : 'bad') }}">{{ $cn->status }}</span></td>
        <td class="num"><b style="color:var(--bad-dark)">{{ number_format($cn->total_amount, 2) }}</b></td>
      </tr>
    @endforeach
    </tbody>
    <tfoot>
      <tr style="background:#fef2f2">
        <td colspan="5" class="num"><b>รวมใบลดหนี้</b></td>
        <td class="num"><b style="color:var(--bad-dark)">{{ number_format($invoice->creditNotes->sum('total_amount'), 2) }}</b></td>
      </tr>
    </tfoot>
  </table>
  <div style="padding:12px;background:#fffbeb;border-radius:0 0 12px 12px;font-size:12px;color:#92400e">
    <b>ℹ️ หมายเหตุ:</b> ใบลดหนี้เป็นเอกสารแยกต่างหาก ไม่ได้หักออกจากยอดคงค้างของบิลโดยตรง
    หากต้องการปรับลบลูกหนี้ ให้สร้างใบเสร็จรับเงินคืน (Refund Receipt) แยกต่างหาก
  </div>
</div>
@endif

@endsection
