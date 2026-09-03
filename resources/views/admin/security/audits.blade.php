@extends('layouts.app')
@section('title', 'ประวัติการแก้ไขข้อมูล')
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
<h1 style="margin:0 0 18px">ประวัติการแก้ไขข้อมูล</h1>
@include('admin.security._tabs', ['active' => 'audits'])

<form method="GET" class="filters">
  <div><label>ตาราง/โมเดล</label>
    <input type="text" name="model" value="{{ request('model') }}" placeholder="เช่น ShopPackage"></div>
  <div><label>การกระทำ</label>
    <select name="action">
      <option value="">ทั้งหมด</option>
      @foreach(['created'=>'สร้าง','updated'=>'แก้ไข','deleted'=>'ลบ'] as $k=>$v)
        <option value="{{ $k }}" @selected(request('action')===$k)>{{ $v }}</option>
      @endforeach
    </select></div>
  <div>
    <button type="submit" class="btn btn-p btn-sm">กรอง</button>
    <a href="{{ route('admin.security.audits') }}" class="btn btn-sm">ล้าง</a>
  </div>
</form>

<div class="card">
  <div class="body" style="padding:0">
    <table>
      <thead><tr><th>เวลา</th><th>ผู้แก้</th><th>รายการ</th><th>การกระทำ</th><th>เปลี่ยนอะไร</th></tr></thead>
      <tbody>
        @forelse($audits as $a)
          <tr>
            <td style="font-size:11.5px;white-space:nowrap">{{ $a->created_at?->format('j M H:i') }}</td>
            <td style="font-size:12px">{{ $a->user_label ?? 'ระบบ' }}<div class="mono" style="color:var(--muted)">{{ $a->ip_address }}</div></td>
            <td class="mono">{{ class_basename($a->auditable_type) }} #{{ $a->auditable_id }}</td>
            <td>
              @switch($a->action)
                @case('created')<span class="badge b-green">สร้าง</span>@break
                @case('updated')<span class="badge b-blue">แก้ไข</span>@break
                @case('deleted')<span class="badge b-red">ลบ</span>@break
              @endswitch
            </td>
            <td class="diff">
              @if($a->changed_fields)
                @foreach(array_slice($a->changed_fields, 0, 5) as $f)
                  <div>
                    <b>{{ $f }}</b>:
                    <span class="o">{{ \Illuminate\Support\Str::limit((string)($a->old_values[$f] ?? '—'), 25) }}</span>
                    →
                    <span class="n">{{ \Illuminate\Support\Str::limit((string)($a->new_values[$f] ?? '—'), 25) }}</span>
                  </div>
                @endforeach
                @if(count($a->changed_fields) > 5)
                  <div style="color:var(--muted)">และอีก {{ count($a->changed_fields) - 5 }} ฟิลด์</div>
                @endif
              @else
                <span style="color:var(--muted)">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="empty">ยังไม่มีประวัติ</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div class="pager">{{ $audits->links('partials.pagination') }}</div>
@endsection
