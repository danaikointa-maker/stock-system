@extends('layouts.app')
@section('title', 'รายงานสต๊อกคงเหลือ')
@section('crumb', 'สต๊อกในสายงานที่คุณดูแล')

@section('content')

<div class="card">
  <div class="body">
    <form method="GET" class="filters">
      <div class="field">
        <label>สินค้า</label>
        <select name="product_id">
          <option value="">ทุกสินค้า</option>
          @foreach($products as $p)
            <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>
              {{ $p->sku }} — {{ $p->name }}
            </option>
          @endforeach
        </select>
      </div>
      <button class="btn btn-p">กรอง</button>
      <a href="{{ route('reports.export', ['type' => 'stock']) }}" class="btn">ส่งออก CSV</a>
    </form>
  </div>
</div>

@if($lowStock->isNotEmpty())
<div class="card" style="border-color:#fca5a5">
  <h3 style="color:var(--bad)">ต้องเติมสต๊อกด่วน ({{ $lowStock->count() }} รายการ)</h3>
  <table>
    <thead><tr><th>หน่วยงาน</th><th>สินค้า</th><th class="num">คงเหลือ</th><th class="num">จุดสั่งซื้อ</th><th class="num">ขาด</th></tr></thead>
    <tbody>
    @foreach($lowStock as $b)
      <tr>
        <td>{{ $b->node->name }} <code>{{ $b->node->code }}</code></td>
        <td>{{ $b->product->name }}</td>
        <td class="num"><span class="badge b-red">{{ number_format($b->qty_on_hand) }}</span></td>
        <td class="num">{{ number_format($b->reorder_point) }}</td>
        <td class="num" style="color:var(--bad);font-weight:700">
          {{ number_format(max(0, $b->reorder_point - $b->qty_on_hand)) }}
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif

<div class="card">
  <h3>สต๊อกคงเหลือทั้งหมด ({{ $rows->count() }} รายการ)</h3>
  @if($rows->isEmpty())
    <div class="empty">ไม่มีข้อมูลสต๊อก</div>
  @else
    <table>
      <thead>
        <tr>
          <th>หน่วยงาน</th><th>ระดับ</th><th>SKU</th><th>สินค้า</th>
          <th class="num">คงเหลือ</th><th class="num">จอง</th>
          <th class="num">ใช้ได้</th><th class="num">ระหว่างทาง</th>
        </tr>
      </thead>
      <tbody>
      @foreach($rows as $r)
        <tr>
          <td>{{ $r->node_name }}<div style="font-size:11px"><code>{{ $r->node_code }}</code></div></td>
          <td><span class="badge b-gray">{{ $r->level_name }}</span></td>
          <td><code>{{ $r->sku }}</code></td>
          <td>{{ $r->product_name }}</td>
          <td class="num"><b>{{ number_format($r->on_hand) }}</b></td>
          <td class="num">{{ number_format($r->reserved) }}</td>
          <td class="num"><span class="badge b-green">{{ number_format($r->available) }}</span></td>
          <td class="num">{{ $r->in_transit ? number_format($r->in_transit) : '—' }}</td>
        </tr>
      @endforeach
      </tbody>
      <tfoot>
        <tr style="background:#f7f9fc;font-weight:700">
          <td colspan="4">รวม</td>
          <td class="num">{{ number_format($rows->sum('on_hand')) }}</td>
          <td class="num">{{ number_format($rows->sum('reserved')) }}</td>
          <td class="num">{{ number_format($rows->sum('available')) }}</td>
          <td class="num">{{ number_format($rows->sum('in_transit')) }}</td>
        </tr>
      </tfoot>
    </table>
  @endif
</div>

@endsection
