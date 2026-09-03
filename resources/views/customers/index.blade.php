@extends('layouts.app')
@section('title', 'ลูกค้าและคะแนน')
@section('crumb', 'ลูกค้าและคะแนนสะสม')

@section('content')

<div class="grid g4">
  <div class="kpi"><div class="lbl">ลูกค้าทั้งหมด</div><div class="val">{{ number_format($totals['customers']) }}</div></div>
  <div class="kpi"><div class="lbl">คะแนนคงค้างในระบบ</div><div class="val">{{ number_format($totals['points']) }}</div></div>
  <div class="kpi {{ $totals['blocked'] ? 'warn' : '' }}">
    <div class="lbl">ถูกระงับ</div><div class="val">{{ number_format($totals['blocked']) }}</div>
  </div>
  <div class="kpi">
    <div class="lbl">ของรางวัล</div>
    <div class="val" style="font-size:16px"><a href="{{ route('customers.rewards') }}">จัดการของรางวัล →</a></div>
  </div>
</div>

<div class="card">
  <h3>ค้นหาลูกค้า</h3>
  <div class="body">
    <form method="GET" class="filters">
      <div class="field">
        <label for="q">เบอร์โทรหรือชื่อ</label>
        <input type="text" id="q" name="q" value="{{ $q }}" placeholder="08xxxxxxxx หรือชื่อลูกค้า">
      </div>
      <button type="submit" class="btn btn-p">ค้นหา</button>
      @if($q)<a href="{{ route('customers.index') }}" class="btn">ล้าง</a>@endif
    </form>
  </div>
</div>

<div class="card">
  <h3>รายชื่อลูกค้า ({{ number_format($customers->total()) }})</h3>
  <table>
    <thead>
      <tr><th>เบอร์โทร</th><th>ชื่อ</th><th>ระดับ</th>
        <th class="num">คะแนนคงเหลือ</th><th>สถานะ</th><th>สมัครเมื่อ</th><th></th></tr>
    </thead>
    <tbody>
      @forelse($customers as $c)
        <tr>
          <td><code>{{ $c->phone }}</code></td>
          <td>{{ $c->name }}</td>
          <td>{{ $c->tier ?? '—' }}</td>
          <td class="num"><b>{{ number_format($c->points_balance) }}</b></td>
          <td>
            <span class="badge {{ $c->isBlocked() ? 'b-red' : 'b-green' }}">
              {{ $c->isBlocked() ? 'ถูกระงับ' : 'ปกติ' }}
            </span>
          </td>
          <td>{{ $c->created_at?->format('d/m/y') }}</td>
          <td><a href="{{ route('customers.show', $c) }}" class="btn btn-sm">ดู</a></td>
        </tr>
      @empty
        <tr><td colspan="7" class="empty">ไม่พบลูกค้า</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager">{{ $customers->links('partials.pagination') }}</div>

@endsection
