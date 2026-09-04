@extends('layouts.app')
@section('title', '↩️ ' . $credit->doc_no)
@section('crumb', 'บัญชี · ใบลดหนี้ · ' . $credit->doc_no)

@section('content')
<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <a href="{{ route('accounting.credit') }}" class="btn">⬅️ กลับ</a>
  <button class="btn btn-blue" onclick="window.print()">🖨️ พิมพ์</button>
  @if($credit->status === 'draft')
    <form method="POST" action="{{ route('accounting.credit.confirm', $credit) }}" style="display:inline">
      @csrf @method('PATCH')
      <button class="btn btn-ok" onclick="return confirm('✅ ยืนยันใบลดหนี้? จะตัดสต๊อกกลับ + บันทึกลงบัญชีทันที (แก้ไขไม่ได้อีก)')">✅ ยืนยัน (ตัดสต๊อก+บัญชี)</button>
    </form>
  @endif
</div>

<div class="card">
  <div style="padding:24px">
    <div style="text-align:center;margin-bottom:20px;border-bottom:3px double var(--line);padding-bottom:16px">
      <div style="font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);font-weight:700">ใบลดหนี้ / Credit Note</div>
      <h2 style="margin-top:6px">{{ $credit->doc_no }}</h2>
      <span style="font-size:14px;font-weight:700">{{ $credit->type_label }}</span>
      <span class="badge {{ $credit->status === 'confirmed' ? 'ok' : ($credit->status === 'draft' ? 'warn' : 'bad') }}">
        {{ $credit->status === 'confirmed' ? '✅ ยืนยันแล้ว' : ($credit->status === 'draft' ? '📝 ร่าง' : '❌ ยกเลิก') }}
      </span>
      @if($credit->posted_to_accounting)
        <span class="badge ok">✅ บันทึกลงบัญชีแล้ว</span>
      @endif
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
      <div style="background:#fef2f2;border-radius:10px;padding:14px 18px">
        <div style="font-size:11px;color:var(--muted);font-weight:700">ลูกค้า</div>
        <div style="font-size:14px;font-weight:700;margin-top:4px">{{ $credit->customer_name }}</div>
      </div>
      <div style="background:#fffbeb;border-radius:10px;padding:14px 18px">
        <div style="font-size:11px;color:var(--muted);font-weight:700">เหตุผล</div>
        <div style="font-size:14px;font-weight:700;margin-top:4px">{{ $credit->reason }}</div>
      </div>
    </div>

    @if($credit->deliveryNote)
    <div style="background:#f0f9ff;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">
      📦 อ้างอิงใบส่งของ: <a href="{{ route('accounting.delivery.show', $credit->deliveryNote) }}"><code>{{ $credit->deliveryNote->doc_no }}</code></a>
    </div>
    @endif

    <table>
      <thead><tr><th>#</th><th>สินค้า</th><th>ล็อต</th><th class="num">จำนวน</th><th class="num">ต้นทุน</th><th class="num">ราคา</th><th class="num">ยอดรวม</th></tr></thead>
      <tbody>
      @foreach($credit->items as $i => $item)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $item->product->name ?? '-' }}</td>
          <td>{{ $item->lot->lot_no ?? '-' }}</td>
          <td class="num">{{ number_format($item->qty) }}</td>
          <td class="num">{{ number_format($item->unit_cost, 2) }}</td>
          <td class="num">{{ number_format($item->unit_price, 2) }}</td>
          <td class="num"><b>{{ number_format($item->line_total, 2) }}</b></td>
        </tr>
      @endforeach
      </tbody>
      <tfoot>
        <tr><td colspan="6" style="text-align:right">ยอดก่อน VAT:</td><td class="num">{{ number_format($credit->subtotal, 2) }}</td></tr>
        <tr><td colspan="6" style="text-align:right">VAT:</td><td class="num">{{ number_format($credit->vat_amount, 2) }}</td></tr>
        <tr style="background:#fef2f2"><td colspan="6" style="text-align:right"><b>ยอดลดหนี้รวม:</b></td><td class="num"><b style="font-size:16px;color:var(--bad-dark)">{{ number_format($credit->total_amount, 2) }}</b></td></tr>
      </tfoot>
    </table>

    <div style="margin-top:16px;font-size:12px;color:var(--muted)">
      📅 สร้าง: {{ $credit->created_at->format('d/m/Y H:i') }} · 👤 {{ $credit->creator->name ?? '-' }}
    </div>
  </div>
</div>
@endsection
