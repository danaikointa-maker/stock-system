@extends('layouts.app')
@section('title', 'สมาชิกร้านค้า')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
  <div>
    <h1 style="margin:0 0 4px">สมาชิกร้านค้า</h1>
    <p style="margin:0;color:var(--muted);font-size:13.5px">
      สมัครและต่ออายุสมาชิกให้ร้านในสายงานของคุณ
    </p>
  </div>
  <a href="{{ route('subscriptions.create') }}" class="btn btn-p">+ สมัครร้านใหม่</a>
</div>

@if(session('status'))<div class="alert a-ok">{{ session('status') }}</div>@endif
@if($errors->any())
  <div class="alert a-bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<div class="grid g4" style="margin-bottom:18px">
  <div class="kpi ok"><div class="l">ใช้งานอยู่</div><div class="v">{{ number_format($summary['active']) }}</div></div>
  <div class="kpi {{ $summary['pending'] > 0 ? 'warn' : '' }}">
    <div class="l">รอชำระเงิน</div><div class="v">{{ number_format($summary['pending']) }}</div>
  </div>
  <div class="kpi"><div class="l">รายได้ค่าสมัคร (บาท)</div><div class="v">{{ number_format($summary['revenue'], 2) }}</div></div>
  <div class="kpi"><div class="l">คอมมิชชั่นรวม (บาท)</div><div class="v">{{ number_format($summary['commission'], 2) }}</div></div>
</div>

@if($expiring->isNotEmpty())
  <div class="alert a-info">
    <b>สมาชิกใกล้หมดอายุใน 30 วัน ({{ $expiring->count() }} ร้าน)</b>
    @foreach($expiring as $e)
      <div style="font-size:12.5px;margin-top:4px">
        {{ $e->shop->name ?? '' }} — หมดอายุ {{ $e->ends_on->format('j M Y') }}
        ({{ (int) now()->diffInDays($e->ends_on, false) }} วัน)
      </div>
    @endforeach
  </div>
@endif

<form method="GET" class="filters">
  <div>
    <label>สถานะ</label>
    <select name="status">
      <option value="">ทั้งหมด</option>
      @foreach(['active'=>'ใช้งานอยู่','pending_payment'=>'รอชำระ','expired'=>'หมดอายุ','cancelled'=>'ยกเลิก','suspended'=>'ระงับ'] as $k=>$v)
        <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label>ค้นหาร้าน</label>
    <input type="text" name="q" value="{{ request('q') }}" placeholder="ชื่อหรือรหัสร้าน">
  </div>
  <div>
    <button type="submit" class="btn btn-p btn-sm">กรอง</button>
    <a href="{{ route('subscriptions.index') }}" class="btn btn-sm">ล้าง</a>
  </div>
</form>

<div class="card">
  <div class="body" style="padding:0">
    <table>
      <thead>
        <tr>
          <th>เลขที่</th><th>ร้าน</th><th>แพ็กเกจ</th>
          <th class="num">แต้ม/เดือน</th><th>อายุสมาชิก</th>
          <th>ตัวแทน</th><th>สถานะ</th><th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($subs as $s)
          <tr>
            <td><a href="{{ route('subscriptions.show', $s) }}" style="font-family:monospace;font-size:11.5px">{{ $s->code }}</a></td>
            <td>{{ $s->shop->name ?? '—' }}</td>
            <td>{{ $s->package->name ?? '—' }}</td>
            <td class="num">{{ number_format($s->monthly_point_limit) }}</td>
            <td style="font-size:12px">
              {{ $s->starts_on->format('j M y') }} – {{ $s->ends_on->format('j M y') }}
            </td>
            <td style="font-size:12px">{{ $s->recruiter->name ?? '—' }}</td>
            <td>
              @switch($s->status)
                @case('active')<span class="badge b-green">ใช้งานอยู่</span>@break
                @case('pending_payment')<span class="badge b-amber">รอชำระ</span>@break
                @case('expired')<span class="badge b-gray">หมดอายุ</span>@break
                @case('cancelled')<span class="badge b-red">ยกเลิก</span>@break
                @case('suspended')<span class="badge b-red">ระงับ</span>@break
              @endswitch
            </td>
            <td><a href="{{ route('subscriptions.show', $s) }}" class="btn btn-sm">ดู</a></td>
          </tr>
        @empty
          <tr><td colspan="8" class="empty">ยังไม่มีสมาชิกร้านค้า</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="pager">{{ $subs->links('partials.pagination') }}</div>
@endsection
