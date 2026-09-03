@extends('layouts.app')
@section('title', 'สินค้า')
@section('crumb', 'จัดการสินค้า')

@section('content')

<div class="card">
  <h3>
    รายการสินค้า
    <a href="{{ route('products.create') }}" class="btn btn-p btn-sm">+ เพิ่มสินค้า</a>
  </h3>
  <div class="body">
    <form method="GET" class="filters">
      <div class="field">
        <label for="q">ค้นหา</label>
        <input type="text" id="q" name="q" value="{{ $q }}" placeholder="ชื่อ, SKU หรือบาร์โค้ด">
      </div>
      <button type="submit" class="btn btn-p">ค้นหา</button>
      @if($q)<a href="{{ route('products.index') }}" class="btn">ล้าง</a>@endif
    </form>
  </div>
</div>

<div class="card">
  <h3>ทั้งหมด {{ $products->total() }} รายการ</h3>
  <table>
    <thead>
      <tr>
        <th>SKU</th><th>สินค้า</th><th>หมวด</th>
        <th class="num">ทุน</th><th class="num">ขายปลีก</th>
        <th class="num">คะแนน/ชิ้น</th><th class="num">คงเหลือรวม</th>
        <th>สถานะ</th><th></th>
      </tr>
    </thead>
    <tbody>
      @forelse($products as $p)
        <tr>
          <td><code>{{ $p->sku }}</code></td>
          <td>
            <a href="{{ route('products.show', $p) }}"><b>{{ $p->name }}</b></a>
            @if($p->barcode)<div style="font-size:11px;color:var(--muted)">บาร์โค้ด {{ $p->barcode }}</div>@endif
          </td>
          <td>{{ $p->category?->name ?? '—' }}</td>
          <td class="num">{{ number_format($p->cost_price, 2) }}</td>
          <td class="num">{{ number_format($p->retail_price, 2) }}</td>
          <td class="num">{{ $p->points_per_unit }}</td>
          <td class="num"><b>{{ number_format($totals[$p->id] ?? 0) }}</b></td>
          <td>
            <span class="badge {{ $p->status === 'active' ? 'b-green' : 'b-gray' }}">
              {{ $p->status === 'active' ? 'ใช้งาน' : 'ปิด' }}
            </span>
          </td>
          <td><a href="{{ route('products.show', $p) }}" class="btn btn-sm">จัดการ</a></td>
        </tr>
      @empty
        <tr><td colspan="9" class="empty">ไม่พบสินค้า</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager">{{ $products->links('partials.pagination') }}</div>

@endsection
