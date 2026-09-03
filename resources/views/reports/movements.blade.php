@extends('layouts.app')
@section('title', 'ความเคลื่อนไหวสินค้า')
@section('crumb', "การ์ดสินค้า ระหว่าง {$from} ถึง {$to}")

@section('content')

@php
  $typeLabels = [
    'receipt' => 'รับเข้า', 'transfer_out' => 'โอนออก', 'transfer_in' => 'โอนเข้า',
    'sale' => 'ขาย', 'return_in' => 'รับคืน', 'return_out' => 'ส่งคืน',
    'adjust_in' => 'ปรับเพิ่ม', 'adjust_out' => 'ปรับลด',
    'damage' => 'เสียหาย', 'expired' => 'หมดอายุ',
  ];
@endphp

<div class="card">
  <div class="body">
    <form method="GET" class="filters">
      <div class="field">
        <label>ตั้งแต่</label>
        <input type="date" name="from" value="{{ $from }}">
      </div>
      <div class="field">
        <label>ถึง</label>
        <input type="date" name="to" value="{{ $to }}">
      </div>
      <div class="field">
        <label>หน่วยงาน</label>
        <select name="node_id">
          <option value="">ทั้งหมด</option>
          @foreach($nodes as $n)
            <option value="{{ $n->id }}" @selected(request('node_id') == $n->id)>{{ $n->code }} — {{ $n->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>สินค้า</label>
        <select name="product_id">
          <option value="">ทั้งหมด</option>
          @foreach($products as $p)
            <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->sku }} — {{ $p->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>ประเภท</label>
        <select name="type">
          <option value="">ทั้งหมด</option>
          @foreach($types as $t)
            <option value="{{ $t->value }}" @selected(request('type') === $t->value)>
              {{ $typeLabels[$t->value] ?? $t->value }}
            </option>
          @endforeach
        </select>
      </div>
      <button class="btn btn-p">กรอง</button>
      <a href="{{ route('reports.movements') }}" class="btn">ล้าง</a>
    </form>
  </div>
</div>

<div class="card">
  <h3>รายการเคลื่อนไหว ({{ number_format($rows->total()) }} รายการ)</h3>

  @if($rows->isEmpty())
    <div class="empty">ไม่พบรายการเคลื่อนไหวในเงื่อนไขที่เลือก</div>
  @else
    <table>
      <thead>
        <tr>
          <th>วันเวลา</th><th>หน่วยงาน</th><th>สินค้า</th><th>ล็อต</th>
          <th>ประเภท</th><th class="num">เข้า</th><th class="num">ออก</th>
          <th class="num">คงเหลือ</th><th>หมายเหตุ</th>
        </tr>
      </thead>
      <tbody>
      @foreach($rows as $m)
        <tr>
          <td style="white-space:nowrap;font-size:12px">{{ $m->created_at->format('d/m/y H:i') }}</td>
          <td><code>{{ $m->node->code }}</code></td>
          <td>{{ $m->product->name }}</td>
          <td style="font-size:12px">{{ $m->lot?->lot_no ?? '—' }}</td>
          <td>
            <span class="badge {{ $m->direction === 'in' ? 'b-green' : 'b-amber' }}">
              {{ $typeLabels[$m->type->value] ?? $m->type->value }}
            </span>
          </td>
          <td class="num" style="color:var(--ok)">{{ $m->direction === 'in' ? '+' . number_format($m->qty) : '' }}</td>
          <td class="num" style="color:var(--bad)">{{ $m->direction === 'out' ? '-' . number_format($m->qty) : '' }}</td>
          <td class="num"><b>{{ number_format($m->balance_after) }}</b></td>
          <td style="font-size:12px;color:var(--muted)">{{ $m->note ?? '—' }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>

    <div class="pager">{{ $rows->links('partials.pagination') }}</div>
  @endif
</div>

@endsection
