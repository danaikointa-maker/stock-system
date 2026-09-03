@extends('layouts.app')
@section('title', 'ประวัติการรับแลกแต้ม')

@section('content')
<h1 style="margin:0 0 4px">ประวัติการรับแลกแต้ม</h1>
<p style="margin:0 0 18px;color:var(--muted);font-size:13.5px">{{ $shop->name }}</p>

{{-- สรุปเดือนนี้ --}}
<div class="grid g4" style="margin-bottom:18px">
  <div class="kpi">
    <div class="l">รายการเดือนนี้</div>
    <div class="v">{{ number_format($summary['count']) }}</div>
  </div>
  <div class="kpi">
    <div class="l">แต้มที่รับแลก</div>
    <div class="v">{{ number_format($summary['points']) }}</div>
  </div>
  <div class="kpi">
    <div class="l">เบิกได้ (บาท)</div>
    <div class="v">{{ number_format($summary['amount'], 2) }}</div>
  </div>
  <div class="kpi {{ $allowance && $allowance->remaining_points <= 0 ? 'warn' : '' }}">
    <div class="l">วงเงินคงเหลือ</div>
    <div class="v">{{ $allowance ? number_format($allowance->remaining_points) : '—' }}</div>
  </div>
</div>

{{-- ตัวกรอง --}}
<form method="GET" class="filters">
  <div>
    <label>ตั้งแต่วันที่</label>
    <input type="date" name="from" value="{{ request('from') }}">
  </div>
  <div>
    <label>ถึงวันที่</label>
    <input type="date" name="to" value="{{ request('to') }}">
  </div>
  <div>
    <label>ประเภท</label>
    <select name="type">
      <option value="">ทั้งหมด</option>
      <option value="goods" @selected(request('type')==='goods')>สินค้า</option>
      <option value="service" @selected(request('type')==='service')>บริการ</option>
      <option value="discount" @selected(request('type')==='discount')>ส่วนลด</option>
      <option value="cash" @selected(request('type')==='cash')>เงินสด</option>
    </select>
  </div>
  <div>
    <button type="submit" class="btn btn-p btn-sm">กรอง</button>
    <a href="{{ route('redeem.history') }}" class="btn btn-sm">ล้าง</a>
  </div>
</form>

<div class="card">
  <div class="body" style="padding:0">
    <table>
      <thead>
        <tr>
          <th>รหัส</th>
          <th>วันเวลา</th>
          <th>ลูกค้า</th>
          <th>รายการ</th>
          <th>ประเภท</th>
          <th class="num">แต้ม</th>
          <th class="num">มูลค่า</th>
          <th>สถานะ</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td><a href="{{ route('redeem.receipt', $r->id) }}" style="font-family:monospace;font-size:12px">{{ $r->code }}</a></td>
            <td>{{ \Carbon\Carbon::parse($r->redeemed_at)->format('j M y H:i') }}</td>
            <td>
              {{ $r->customer_name ?? '—' }}
              <div style="font-size:11px;color:var(--muted)">{{ $r->customer_phone }}</div>
            </td>
            <td>{{ $r->reward_name }}</td>
            <td>
              @switch($r->redeem_type)
                @case('goods')<span class="badge b-blue">สินค้า</span>@break
                @case('service')<span class="badge b-green">บริการ</span>@break
                @case('discount')<span class="badge b-amber">ส่วนลด</span>@break
                @case('cash')<span class="badge b-gray">เงินสด</span>@break
              @endswitch
            </td>
            <td class="num">{{ number_format($r->points_used) }}</td>
            <td class="num">{{ number_format($r->cash_value, 2) }}</td>
            <td>
              @if($r->claim_id)
                <span class="badge b-green">เบิกแล้ว</span>
              @else
                <span class="badge b-amber">รอเบิก</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="empty">ยังไม่มีรายการรับแลกแต้ม</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="pager">{{ $rows->links('partials.pagination') }}</div>
@endsection
