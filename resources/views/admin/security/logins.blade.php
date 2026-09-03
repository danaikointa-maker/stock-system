@extends('layouts.app')
@section('title', 'ประวัติการเข้าสู่ระบบ')
@push('head')
<style>
  .navtabs{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:18px}
  .navtabs a{padding:9px 15px;border:1.5px solid var(--line);border-radius:10px;
             font-size:13px;font-weight:600;color:var(--muted);background:#fff}
  .navtabs a.on{background:var(--brand);border-color:var(--brand);color:#fff}
  .sevdot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px}
  .sev-critical{background:#B71C1C} .sev-high{background:#E53935}
  .sev-medium{background:#FB8C00} .sev-low{background:#90A4AE} .sev-info{background:#42A5F5}
  .mono{font-family:monospace;font-size:11.5px}
  .diff{font-size:11.5px;line-height:1.7}
  .diff .o{color:#C62828} .diff .n{color:#2E7D32}
</style>@endpush

@section('content')
<h1 style="margin:0 0 18px">ประวัติการเข้าสู่ระบบ</h1>
@include('admin.security._tabs', ['active' => 'logins'])

@if($suspects->isNotEmpty())
  <div class="alert a-bad">
    <b>IP ที่ล็อกอินพลาดบ่อยผิดปกติใน 24 ชั่วโมง</b>
    @foreach($suspects as $s)
      <div style="font-size:12.5px;margin-top:4px">
        <span class="mono">{{ $s->ip_address }}</span> — พลาด {{ $s->c }} ครั้ง
        <a href="{{ route('admin.security.logins', ['ip' => $s->ip_address]) }}"
           style="margin-left:8px;font-size:11.5px">ดูรายละเอียด</a>
      </div>
    @endforeach
  </div>
@endif

<form method="GET" class="filters">
  <div><label>IP</label><input type="text" name="ip" value="{{ request('ip') }}"></div>
  <div><label>แสดง</label>
    <select name="failed">
      <option value="">ทั้งหมด</option>
      <option value="1" @selected(request('failed'))>เฉพาะที่ล้มเหลว</option>
    </select></div>
  <div>
    <button type="submit" class="btn btn-p btn-sm">กรอง</button>
    <a href="{{ route('admin.security.logins') }}" class="btn btn-sm">ล้าง</a>
  </div>
</form>

<div class="card">
  <div class="body" style="padding:0">
    <table>
      <thead><tr><th>เวลา</th><th>บัญชีที่กรอก</th><th>ผลลัพธ์</th><th>สาเหตุ</th><th>IP</th></tr></thead>
      <tbody>
        @forelse($logins as $l)
          <tr>
            <td style="font-size:11.5px;white-space:nowrap">{{ $l->created_at?->format('j M H:i:s') }}</td>
            <td style="font-size:12.5px">{{ $l->login_input }}</td>
            <td>
              @if($l->succeeded)
                <span class="badge b-green">สำเร็จ</span>
              @else
                <span class="badge b-red">ล้มเหลว</span>
              @endif
            </td>
            <td style="font-size:12px;color:var(--muted)">{{ $l->failure_reason ?? '—' }}</td>
            <td class="mono">{{ $l->ip_address }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="empty">ยังไม่มีข้อมูล</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div class="pager">{{ $logins->links('partials.pagination') }}</div>
@endsection
