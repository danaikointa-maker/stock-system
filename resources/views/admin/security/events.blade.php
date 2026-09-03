@extends('layouts.app')
@section('title', 'เหตุการณ์ความปลอดภัย')
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
<h1 style="margin:0 0 18px">เหตุการณ์ความปลอดภัย</h1>
@if(session('status'))<div class="alert a-ok">{{ session('status') }}</div>@endif
@include('admin.security._tabs', ['active' => 'events'])

<form method="GET" class="filters">
  <div>
    <label>ชนิดเหตุการณ์</label>
    <select name="type">
      <option value="">ทั้งหมด</option>
      @foreach($types as $t)
        <option value="{{ $t }}" @selected(request('type')===$t)>{{ $t }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label>ความรุนแรง</label>
    <select name="severity">
      <option value="">ทั้งหมด</option>
      @foreach(['critical'=>'วิกฤต','high'=>'สูง','medium'=>'กลาง','low'=>'ต่ำ','info'=>'ข้อมูล'] as $k=>$v)
        <option value="{{ $k }}" @selected(request('severity')===$k)>{{ $v }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label>IP</label>
    <input type="text" name="ip" value="{{ request('ip') }}" placeholder="1.2.3.4">
  </div>
  <div>
    <label>ยังไม่ตรวจ</label>
    <select name="unreviewed">
      <option value="">ทั้งหมด</option>
      <option value="1" @selected(request('unreviewed'))>เฉพาะที่ยังไม่ตรวจ</option>
    </select>
  </div>
  <div>
    <button type="submit" class="btn btn-p btn-sm">กรอง</button>
    <a href="{{ route('admin.security.events') }}" class="btn btn-sm">ล้าง</a>
  </div>
</form>

<div class="card">
  <div class="body" style="padding:0">
    <table>
      <thead>
        <tr><th>เวลา</th><th>ชนิด</th><th>รายละเอียด</th><th>ผู้ทำ</th><th>IP</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($events as $e)
          <tr style="{{ $e->is_reviewed ? 'opacity:.55' : '' }}">
            <td style="font-size:11.5px;white-space:nowrap">{{ $e->created_at?->format('j M H:i') }}</td>
            <td>
              <span class="sevdot sev-{{ $e->severity }}"></span>
              <span class="mono">{{ $e->event_type }}</span>
            </td>
            <td style="font-size:12.5px">
              {{ $e->message }}
              @if($e->route)<div class="mono" style="color:var(--muted)">/{{ $e->route }}</div>@endif
            </td>
            <td style="font-size:12px">{{ $e->actor_label ?? $e->actor_type }}</td>
            <td class="mono">{{ $e->ip_address }}</td>
            <td>
              @if(! $e->is_reviewed)
                <form method="POST" action="{{ route('admin.security.review', $e) }}">
                  @csrf @method('PATCH')
                  <button type="submit" class="btn btn-sm">ตรวจแล้ว</button>
                </form>
              @else
                <span class="badge b-green">ตรวจแล้ว</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="empty">ไม่มีเหตุการณ์</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div class="pager">{{ $events->links('partials.pagination') }}</div>
@endsection
