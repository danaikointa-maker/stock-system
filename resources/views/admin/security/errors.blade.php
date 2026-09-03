@extends('layouts.app')
@section('title', 'ข้อผิดพลาดของระบบ')
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
<h1 style="margin:0 0 18px">ข้อผิดพลาดของระบบ</h1>
@include('admin.security._tabs', ['active' => 'errors'])

<form method="GET" class="filters">
  <div><label>แสดง</label>
    <select name="all">
      <option value="">เฉพาะที่ยังไม่แก้</option>
      <option value="1" @selected(request('all'))>ทั้งหมด</option>
    </select></div>
  <div><button type="submit" class="btn btn-p btn-sm">กรอง</button></div>
</form>

<div class="card">
  <div class="body" style="padding:0">
    <table>
      <thead><tr><th>ล่าสุด</th><th>ระดับ</th><th>ข้อความ</th><th>ตำแหน่ง</th><th class="num">ครั้ง</th></tr></thead>
      <tbody>
        @forelse($errorLogs as $e)
          <tr>
            <td style="font-size:11.5px;white-space:nowrap">{{ optional($e->last_seen_at)->format('j M H:i') }}</td>
            <td><span class="sevdot sev-{{ $e->level === 'critical' ? 'critical' : 'high' }}"></span>{{ $e->level }}</td>
            <td style="font-size:12.5px">{{ \Illuminate\Support\Str::limit($e->message, 90) }}</td>
            <td class="mono" style="color:var(--muted)">
              {{ $e->file ? basename($e->file) . ':' . $e->line : '—' }}
            </td>
            <td class="num">{{ number_format($e->occurrence_count) }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="empty">ไม่มีข้อผิดพลาดค้าง</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div class="pager">{{ $errorLogs->links('partials.pagination') }}</div>
@endsection
