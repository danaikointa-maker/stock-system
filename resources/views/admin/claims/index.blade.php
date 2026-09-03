@extends('layouts.app')
@section('title', 'อนุมัติใบเบิกเงิน')

@section('content')
<h1 style="margin:0 0 4px">อนุมัติใบเบิกเงิน</h1>
<p style="margin:0 0 18px;color:var(--muted);font-size:13.5px">
  เจ้าของระบบเป็นผู้จ่ายเงินคืนให้ร้านค้าโดยตรง
</p>

@if(session('status'))<div class="alert a-ok">{{ session('status') }}</div>@endif
@if($errors->any())
  <div class="alert a-bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<div class="grid g4" style="margin-bottom:18px">
  <div class="kpi {{ $summary['submitted_count'] > 0 ? 'warn' : '' }}">
    <div class="l">รออนุมัติ</div>
    <div class="v">{{ number_format($summary['submitted_count']) }}</div>
  </div>
  <div class="kpi">
    <div class="l">ยอดรออนุมัติ (บาท)</div>
    <div class="v">{{ number_format($summary['submitted_amount'], 2) }}</div>
  </div>
  <div class="kpi">
    <div class="l">อนุมัติแล้ว รอจ่าย</div>
    <div class="v">{{ number_format($summary['approved_count']) }}</div>
  </div>
  <div class="kpi ok">
    <div class="l">จ่ายไปแล้ว (บาท)</div>
    <div class="v">{{ number_format($summary['paid_amount'], 2) }}</div>
  </div>
</div>

<form method="GET" class="filters">
  <div>
    <label>สถานะ</label>
    <select name="status">
      <option value="">ทั้งหมด</option>
      @foreach(['submitted'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','paid'=>'จ่ายแล้ว','rejected'=>'ปฏิเสธ','draft'=>'ร่าง'] as $k=>$v)
        <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label>งวด</label>
    <input type="text" name="period" value="{{ request('period') }}" placeholder="2026-09" maxlength="7">
  </div>
  <div>
    <label>ชื่อร้าน</label>
    <input type="text" name="shop" value="{{ request('shop') }}" placeholder="ค้นหาชื่อร้าน">
  </div>
  <div>
    <button type="submit" class="btn btn-p btn-sm">กรอง</button>
    <a href="{{ route('admin.claims.index') }}" class="btn btn-sm">ล้าง</a>
  </div>
</form>

<div class="card">
  <div class="body" style="padding:0">
    <table>
      <thead>
        <tr>
          <th>เลขที่</th><th>ร้าน</th><th>งวด</th>
          <th class="num">แต้ม</th><th class="num">จำนวนเงิน</th>
          <th>สถานะ</th><th>ยื่นเมื่อ</th><th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($claims as $c)
          <tr>
            <td><a href="{{ route('admin.claims.show', $c) }}" style="font-family:monospace;font-size:12px">{{ $c->code }}</a></td>
            <td>{{ $c->claimant->name ?? '—' }}</td>
            <td>{{ $c->period_ym }}</td>
            <td class="num">{{ number_format($c->total_points) }}</td>
            <td class="num"><b>{{ number_format($c->total_amount, 2) }}</b></td>
            <td>
              @switch($c->status)
                @case('draft')<span class="badge b-gray">ร่าง</span>@break
                @case('submitted')<span class="badge b-blue">รออนุมัติ</span>@break
                @case('approved')<span class="badge b-amber">รอจ่าย</span>@break
                @case('paid')<span class="badge b-green">จ่ายแล้ว</span>@break
                @case('rejected')<span class="badge b-red">ปฏิเสธ</span>@break
              @endswitch
            </td>
            <td style="font-size:12px;color:var(--muted)">
              {{ optional($c->submitted_at)->format('j M y H:i') ?: '—' }}
            </td>
            <td><a href="{{ route('admin.claims.show', $c) }}" class="btn btn-sm">ตรวจสอบ</a></td>
          </tr>
        @empty
          <tr><td colspan="8" class="empty">ไม่มีใบเบิก</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="pager">{{ $claims->links('partials.pagination') }}</div>
@endsection
