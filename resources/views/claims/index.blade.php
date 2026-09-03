@extends('layouts.app')
@section('title', 'เบิกเงินคืน')

@push('head')
<style>
  .period-card{
    display:flex;justify-content:space-between;align-items:center;gap:14px;
    padding:15px 17px;border:2px solid #FFD97A;background:#FFFCF2;
    border-radius:14px;margin-bottom:10px;flex-wrap:wrap;
  }
  .period-card .g .p{font-size:16px;font-weight:800}
  .period-card .g .d{font-size:12px;color:var(--muted);margin-top:2px}
  .period-card .amt{font-size:22px;font-weight:800;color:var(--brand);white-space:nowrap}
</style>
@endpush

@section('content')
<h1 style="margin:0 0 4px">เบิกเงินคืน</h1>
<p style="margin:0 0 18px;color:var(--muted);font-size:13.5px">
  {{ $shop->name }} · เบิกเงินจากแต้มที่รับแลกจากลูกค้า
</p>

@if(session('status'))<div class="alert a-ok">{{ session('status') }}</div>@endif
@if($errors->any())
  <div class="alert a-bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<div class="grid g4" style="margin-bottom:18px">
  <div class="kpi">
    <div class="l">รายการที่ยังไม่เบิก</div>
    <div class="v">{{ number_format($pending['count']) }}</div>
  </div>
  <div class="kpi">
    <div class="l">แต้มรอเบิก</div>
    <div class="v">{{ number_format($pending['points']) }}</div>
  </div>
  <div class="kpi ok">
    <div class="l">เงินที่เบิกได้ (บาท)</div>
    <div class="v">{{ number_format($pending['amount'], 2) }}</div>
  </div>
  <div class="kpi">
    <div class="l">ใบเบิกทั้งหมด</div>
    <div class="v">{{ number_format($claims->total()) }}</div>
  </div>
</div>

{{-- งวดที่ยังไม่ได้เบิก --}}
<div class="card" style="margin-bottom:18px">
  <div class="body">
    <h3 style="margin:0 0 4px;font-size:15px">งวดที่เบิกได้</h3>
    <p class="hint" style="margin-bottom:14px">
      เบิกได้เฉพาะงวดที่ผ่านมาแล้ว งวดปัจจุบันต้องรอสิ้นเดือนก่อน
    </p>

    @forelse($unclaimed as $u)
      <div class="period-card">
        <div class="g">
          <div class="p">งวด {{ $u['period'] }}</div>
          <div class="d">{{ number_format($u['count']) }} รายการ · {{ number_format($u['points']) }} แต้ม</div>
        </div>
        <div class="amt">{{ number_format($u['amount'], 2) }} ฿</div>
        <form method="POST" action="{{ route('claims.store') }}">
          @csrf
          <input type="hidden" name="period_ym" value="{{ $u['period'] }}">
          <button type="submit" class="btn btn-p btn-sm">สร้างใบเบิก</button>
        </form>
      </div>
    @empty
      <div class="empty">
        ยังไม่มีงวดที่เบิกได้<br>
        <small>รายการที่รับแลกในเดือนนี้จะเบิกได้เมื่อขึ้นเดือนใหม่</small>
      </div>
    @endforelse
  </div>
</div>

{{-- ประวัติใบเบิก --}}
<div class="card">
  <div class="body" style="padding:0">
    <table>
      <thead>
        <tr>
          <th>เลขที่</th><th>งวด</th><th class="num">รายการ</th>
          <th class="num">แต้ม</th><th class="num">จำนวนเงิน</th>
          <th>สถานะ</th><th>อัปเดต</th><th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($claims as $c)
          <tr>
            <td><a href="{{ route('claims.show', $c) }}" style="font-family:monospace;font-size:12px">{{ $c->code }}</a></td>
            <td>{{ $c->period_ym }}</td>
            <td class="num">{{ number_format($c->entry_count) }}</td>
            <td class="num">{{ number_format($c->total_points) }}</td>
            <td class="num"><b>{{ number_format($c->total_amount, 2) }}</b></td>
            <td>
              @switch($c->status)
                @case('draft')<span class="badge b-gray">ร่าง</span>@break
                @case('submitted')<span class="badge b-blue">รออนุมัติ</span>@break
                @case('approved')<span class="badge b-amber">อนุมัติแล้ว รอจ่าย</span>@break
                @case('paid')<span class="badge b-green">จ่ายแล้ว</span>@break
                @case('rejected')<span class="badge b-red">ถูกปฏิเสธ</span>@break
              @endswitch
            </td>
            <td style="font-size:12px;color:var(--muted)">
              {{ optional($c->paid_at ?? $c->approved_at ?? $c->submitted_at ?? $c->created_at)->format('j M y') }}
            </td>
            <td>
              <a href="{{ route('claims.show', $c) }}" class="btn btn-sm">ดู</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="empty">ยังไม่มีใบเบิก</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="pager">{{ $claims->links('partials.pagination') }}</div>
@endsection
