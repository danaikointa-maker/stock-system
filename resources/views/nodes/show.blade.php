@extends('layouts.app')
@section('title', $node->name)
@section('crumb', $node->level_id->label() . ' · ' . $node->code)

@section('content')

<div class="grid g2">
  <div class="card">
    <h3>
      ข้อมูลหน่วยงาน
      @can('update', $node)
        <a href="{{ route('nodes.edit', $node) }}" class="btn btn-sm">แก้ไข</a>
      @endcan
    </h3>
    <div class="body">
      <table>
        <tbody>
          <tr><th style="width:130px">รหัส</th><td><code>{{ $node->code }}</code></td></tr>
          <tr><th>ระดับชั้น</th><td><span class="badge b-blue">{{ $node->level_id->label() }}</span></td></tr>
          <tr><th>ต้นสังกัด</th><td>{{ $node->parent?->name ?? '— (ระดับสูงสุด)' }}</td></tr>
          <tr><th>เบอร์โทร</th><td>{{ $node->phone ?? '—' }}</td></tr>
          <tr><th>ที่อยู่</th><td>{{ $node->address ?? '—' }}</td></tr>
          <tr><th>วงเงินเครดิต</th><td class="num">{{ number_format($node->credit_limit, 2) }} บาท</td></tr>
          <tr><th>สถานะ</th><td>
            @if($node->status === 'active')<span class="badge b-green">เปิดทำการ</span>
            @elseif($node->status === 'suspended')<span class="badge b-amber">ระงับชั่วคราว</span>
            @else<span class="badge b-red">ปิดกิจการ</span>@endif
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h3>สมาชิกในหน่วยงาน ({{ $members->count() }})</h3>
    @if($members->isEmpty())
      <div class="empty">ยังไม่มีสมาชิก</div>
    @else
      <table>
        <thead><tr><th>ชื่อ</th><th>บทบาท</th><th>สถานะ</th></tr></thead>
        <tbody>
        @foreach($members as $m)
          <tr>
            <td>
              {{ $m->name }}
              <div style="font-size:11px;color:var(--muted)">{{ $m->email ?? $m->phone }}</div>
            </td>
            <td><span class="badge b-blue">{{ $m->role->label() }}</span></td>
            <td>
              @if($m->is_active)<span class="badge b-green">ใช้งาน</span>
              @else<span class="badge b-red">ระงับ</span>@endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

@if($node->children->isNotEmpty())
<div class="card">
  <h3>หน่วยงานลูกโดยตรง ({{ $node->children->count() }})</h3>
  <table>
    <thead><tr><th>รหัส</th><th>ชื่อ</th><th>ระดับ</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    @foreach($node->children as $c)
      <tr>
        <td><code>{{ $c->code }}</code></td>
        <td>{{ $c->name }}</td>
        <td><span class="badge b-gray">{{ $c->level_id->label() }}</span></td>
        <td>
          @if($c->status === 'active')<span class="badge b-green">เปิดทำการ</span>
          @else<span class="badge b-amber">{{ $c->status }}</span>@endif
        </td>
        <td style="text-align:right"><a href="{{ route('nodes.show', $c) }}" class="btn btn-sm">ดู</a></td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif

<div class="card">
  <h3>สต๊อกคงเหลือในหน่วยงานนี้</h3>
  @if($balances->isEmpty())
    <div class="empty">ยังไม่มีสินค้าในหน่วยงานนี้</div>
  @else
    <table>
      <thead>
        <tr>
          <th>SKU</th><th>สินค้า</th>
          <th class="num">คงเหลือ</th><th class="num">จอง</th>
          <th class="num">ใช้ได้</th><th class="num">ระหว่างทาง</th><th class="num">จุดสั่งซื้อ</th>
        </tr>
      </thead>
      <tbody>
      @foreach($balances as $b)
        <tr>
          <td><code>{{ $b->product->sku }}</code></td>
          <td>{{ $b->product->name }}</td>
          <td class="num"><b>{{ number_format($b->qty_on_hand) }}</b></td>
          <td class="num">{{ number_format($b->qty_reserved) }}</td>
          <td class="num">
            <span class="badge {{ $b->available <= $b->reorder_point && $b->reorder_point > 0 ? 'b-red' : 'b-green' }}">
              {{ number_format($b->available) }}
            </span>
          </td>
          <td class="num">{{ number_format($b->qty_in_transit) }}</td>
          <td class="num">{{ number_format($b->reorder_point) }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  @endif
</div>

@endsection
