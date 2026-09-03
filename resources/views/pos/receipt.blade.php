@extends('layouts.app')
@section('title', 'ใบเสร็จ ' . $sale->doc_no)
@section('crumb', $sale->node->name)

@section('content')

<div style="max-width:560px">
  <div class="card">
    <h3>
      ใบเสร็จรับเงิน
      @if($sale->status === 'completed')
        <span class="badge b-green">สำเร็จ</span>
      @else
        <span class="badge b-red">ยกเลิกแล้ว</span>
      @endif
    </h3>

    <div class="body">
      <table>
        <tbody>
          <tr><th style="width:120px">เลขที่บิล</th><td><code>{{ $sale->doc_no }}</code></td></tr>
          <tr><th>วันเวลา</th><td>{{ $sale->sold_at->format('d/m/Y H:i') }}</td></tr>
          <tr><th>หน่วยงาน</th><td>{{ $sale->node->name }} <code>{{ $sale->node->code }}</code></td></tr>
          <tr><th>ลูกค้า</th><td>{{ $sale->customer?->phone ?? 'ลูกค้าทั่วไป' }}</td></tr>
          <tr><th>ชำระโดย</th><td>
            {{ ['cash'=>'เงินสด','qr'=>'สแกน QR','transfer'=>'โอนเงิน','credit'=>'เครดิต'][$sale->payment_method] ?? $sale->payment_method }}
          </td></tr>
        </tbody>
      </table>
    </div>

    <table>
      <thead><tr><th>สินค้า</th><th class="num">จำนวน</th><th class="num">ราคา</th><th class="num">รวม</th></tr></thead>
      <tbody>
      @foreach($sale->items as $it)
        <tr>
          <td>
            {{ $it->product->name }}
            <div style="font-size:11px"><code>{{ $it->product->sku }}</code></div>
          </td>
          <td class="num">{{ number_format($it->qty) }}</td>
          <td class="num">{{ number_format($it->unit_price, 2) }}</td>
          <td class="num"><b>{{ number_format($it->line_total, 2) }}</b></td>
        </tr>
      @endforeach
      </tbody>
      <tfoot>
        <tr><th colspan="3" style="text-align:right">ยอดรวม</th><td class="num">{{ number_format($sale->subtotal, 2) }}</td></tr>
        @if($sale->discount > 0)
          <tr><th colspan="3" style="text-align:right">ส่วนลด</th><td class="num">-{{ number_format($sale->discount, 2) }}</td></tr>
        @endif
        <tr style="background:#f7f9fc">
          <th colspan="3" style="text-align:right;font-size:14px">ยอดสุทธิ</th>
          <td class="num" style="font-size:17px;font-weight:700;color:var(--brand)">{{ number_format($sale->total, 2) }}</td>
        </tr>
      </tfoot>
    </table>

    <div class="body" style="border-top:1px solid var(--line);font-size:12.5px;color:var(--muted)">
      สินค้าในบิลนี้ถูกเปิดใช้งาน QR เรียบร้อยแล้ว — ลูกค้าสแกนเพื่อรับคะแนนสะสมได้ทันที
    </div>
  </div>

  <div style="display:flex;gap:9px">
    <a href="{{ route('pos.index', ['node' => $sale->org_node_id]) }}" class="btn btn-p">เปิดบิลใหม่</a>
    <a href="{{ route('pos.history') }}" class="btn">ประวัติการขาย</a>

    @can('void', $sale)
      <span style="flex:1"></span>
      <form method="POST" action="{{ route('pos.void', $sale) }}"
            onsubmit="return confirm('ยกเลิกบิลนี้และคืนสินค้าเข้าสต๊อก?')">
        @csrf @method('PATCH')
        <input type="hidden" name="reason" value="ยกเลิกจากหน้าใบเสร็จ">
        <button class="btn btn-d">ยกเลิกบิล</button>
      </form>
    @endcan
  </div>
</div>

@endsection
