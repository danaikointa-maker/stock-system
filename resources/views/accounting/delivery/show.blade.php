@extends('layouts.app')
@section('title', '🚚 ' . $delivery->doc_no)
@section('crumb', 'บัญชี · ใบส่งของ · ' . $delivery->doc_no)

@section('content')
<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <a href="{{ route('accounting.delivery') }}" class="btn">⬅️ กลับ</a>
  <button class="btn btn-blue" onclick="window.print()">🖨️ พิมพ์</button>
  @if($delivery->status === 'ready')
    <form method="POST" action="{{ route('accounting.delivery.ship', $delivery) }}" style="display:inline">
      @csrf @method('PATCH')
      <button class="btn btn-p" onclick="return confirm('🚚 ยืนยันส่งของ? จะตัดสต๊อก + บันทึกบัญชีทันที')">🚚 ส่งของ (ตัดสต๊อก+บัญชี)</button>
    </form>
  @endif
  @if($delivery->status === 'shipped')
    <form method="POST" action="{{ route('accounting.delivery.deliver', $delivery) }}" style="display:inline">
      @csrf @method('PATCH')
      <button class="btn btn-ok" onclick="return confirm('✅ ยืนยันส่งถึงปลายทาง?')">✅ ยืนยันถึงปลายทาง</button>
    </form>
    <a href="{{ route('accounting.credit.create', ['deliveryNote' => $delivery->id]) }}" class="btn" style="background:#fef2f2;color:#b91c1c">↩️ ทำใบคืนสินค้า</a>
  @endif
  @if($delivery->status === 'delivered')
    <a href="{{ route('accounting.credit.create', ['deliveryNote' => $delivery->id]) }}" class="btn" style="background:#fef2f2;color:#b91c1c">↩️ ทำใบคืนสินค้า</a>
  @endif
</div>

<div class="card">
  <div style="padding:24px">
    {{-- Header --}}
    <div style="text-align:center;margin-bottom:20px;border-bottom:3px double var(--line);padding-bottom:16px">
      <div style="font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);font-weight:700">ใบส่งของ / Delivery Note</div>
      <h2 style="margin-top:6px">{{ $delivery->doc_no }}</h2>
      @php
        $statusColor = match($delivery->status) {
          'draft' => '#f59e0b', 'ready' => '#3b82f6', 'shipped' => '#8b5cf6',
          'delivered' => '#10b981', 'returned' => '#ef4444', default => '#6b7280'
        };
      @endphp
      <span class="badge" style="background:{{ $statusColor }}22;color:{{ $statusColor }};font-size:13px;padding:4px 16px;border-radius:20px">
        {{ $delivery->status_label }}
      </span>
    </div>

    {{-- Info Grid --}}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px">
      <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
        <div style="font-size:11px;color:var(--muted);font-weight:700">ลูกค้า</div>
        <div style="font-size:14px;font-weight:700;margin-top:4px">{{ $delivery->customer_name }}</div>
        @if($delivery->recipient_name)<div style="font-size:12px;color:var(--muted)">ผู้รับ: {{ $delivery->recipient_name }}</div>@endif
        @if($delivery->recipient_phone)<div style="font-size:12px;color:var(--muted)">📱 {{ $delivery->recipient_phone }}</div>@endif
      </div>
      <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
        <div style="font-size:11px;color:var(--muted);font-weight:700">ที่อยู่จัดส่ง</div>
        <div style="font-size:13px;margin-top:4px">{{ $delivery->delivery_address ?? '-' }}</div>
      </div>
      <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
        <div style="font-size:11px;color:var(--muted);font-weight:700">ขนส่ง</div>
        <div style="font-size:13px;margin-top:4px">{{ $delivery->carrier ?? '-' }}</div>
        @if($delivery->tracking_no)<div style="font-size:12px;color:var(--muted)">Tracking: {{ $delivery->tracking_no }}</div>@endif
      </div>
    </div>

    {{-- Dates --}}
    <div style="display:flex;gap:24px;margin-bottom:20px;font-size:12px;color:var(--muted)">
      <div>📅 สร้าง: {{ $delivery->created_at->format('d/m/Y H:i') }}</div>
      @if($delivery->shipped_at)<div>🚚 ส่ง: {{ $delivery->shipped_at->format('d/m/Y H:i') }}</div>@endif
      @if($delivery->delivered_at)<div>✅ ถึง: {{ $delivery->delivered_at->format('d/m/Y H:i') }}</div>@endif
      <div>👤 โดย: {{ $delivery->creator->name ?? '-' }}</div>
    </div>

    {{-- Items --}}
    <table>
      <thead><tr>
        <th>#</th>
        <th>สินค้า</th>
        <th>ล็อต</th>
        <th class="num">จำนวน</th>
        <th class="num">ต้นทุน</th>
        <th class="num">ราคาขาย</th>
        <th class="num">ยอดรวม</th>
        <th class="num">คืน</th>
      </tr></thead>
      <tbody>
      @foreach($delivery->items as $i => $item)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $item->product->name ?? '-' }}</td>
          <td>{{ $item->lot->lot_no ?? '-' }}</td>
          <td class="num">{{ number_format($item->qty) }}</td>
          <td class="num">{{ number_format($item->unit_cost, 2) }}</td>
          <td class="num">{{ number_format($item->unit_price, 2) }}</td>
          <td class="num"><b>{{ number_format($item->line_total, 2) }}</b></td>
          <td class="num">
            @if($item->returned_qty > 0)
              <span class="badge bad">{{ number_format($item->returned_qty) }}</span>
            @else
              -
            @endif
          </td>
        </tr>
      @endforeach
      </tbody>
      <tfoot>
        <tr style="background:#f0fdf4">
          <td colspan="3" style="text-align:right"><b>รวม</b></td>
          <td class="num"><b>{{ number_format($delivery->total_qty) }}</b></td>
          <td colspan="2"></td>
          <td class="num"><b style="font-size:16px;color:var(--ok-dark)">{{ number_format($delivery->total_amount, 2) }}</b></td>
          <td></td>
        </tr>
      </tfoot>
    </table>

    @if($delivery->note)
    <div style="margin-top:16px;padding:12px;background:#fffbeb;border-radius:8px;border:1px solid #fde68a">
      <b>📝 หมายเหตุ:</b> {{ $delivery->note }}
    </div>
    @endif

    {{-- Credit Notes --}}
    @if($delivery->creditNotes->count())
    <div style="margin-top:20px">
      <h4 style="font-size:13px;font-weight:700;margin-bottom:8px">↩️ ใบลดหนี้ที่เกี่ยวข้อง</h4>
      <table>
        <thead><tr><th>เลขที่</th><th>ประเภท</th><th>เหตุผล</th><th class="num">ยอด</th><th>สถานะ</th><th></th></tr></thead>
        <tbody>
        @foreach($delivery->creditNotes as $cn)
          <tr>
            <td><code>{{ $cn->doc_no }}</code></td>
            <td>{{ $cn->type_label }}</td>
            <td>{{ $cn->reason }}</td>
            <td class="num">{{ number_format($cn->total_amount, 2) }}</td>
            <td><span class="badge {{ $cn->status === 'confirmed' ? 'ok' : ($cn->status === 'draft' ? 'warn' : 'bad') }}">{{ $cn->status }}</span></td>
            <td><a href="{{ route('accounting.credit.show', $cn) }}" class="btn btn-sm btn-view">👁️</a></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>
</div>
@endsection
