@extends('layouts.app')
@section('title', 'ศูนย์ความปลอดภัย')

@push('head')
<style>
  .navtabs{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:18px}
  .navtabs a{padding:9px 15px;border:1.5px solid var(--line);border-radius:10px;
             font-size:13px;font-weight:600;color:var(--muted);background:#fff}
  .navtabs a.on{background:var(--brand);border-color:var(--brand);color:#fff}
  .ev{display:flex;gap:11px;padding:12px 0;border-bottom:1px dashed var(--line)}
  .ev:last-child{border:0}
  .ev .sev{width:8px;height:8px;border-radius:50%;margin-top:6px;flex-shrink:0}
  .sev-critical{background:#B71C1C} .sev-high{background:#E53935}
  .sev-medium{background:#FB8C00} .sev-low{background:#90A4AE} .sev-info{background:#42A5F5}
  .ev .g{flex:1;min-width:0}
  .ev .g b{font-size:13.5px;display:block}
  .ev .g small{color:var(--muted);font-size:11.5px;display:block;margin-top:2px;line-height:1.6}
</style>
@endpush

@section('content')
<h1 style="margin:0 0 4px">ศูนย์ความปลอดภัย</h1>
<p style="margin:0 0 18px;color:var(--muted);font-size:13.5px">
  หลักฐานทุกอย่างที่ต้องใช้ตรวจสอบ · เฉพาะเจ้าของระบบ
</p>

@if(session('status'))<div class="alert a-ok">{{ session('status') }}</div>@endif

@include('admin.security._tabs', ['active' => 'index'])

<div class="grid g4" style="margin-bottom:18px">
  <div class="kpi {{ $stats['alerts_new'] > 0 ? 'bad' : '' }}">
    <div class="l">แจ้งเตือนใหม่</div><div class="v">{{ number_format($stats['alerts_new']) }}</div>
  </div>
  <div class="kpi {{ $stats['events_serious'] > 0 ? 'warn' : '' }}">
    <div class="l">เหตุการณ์ร้ายแรง</div><div class="v">{{ number_format($stats['events_serious']) }}</div>
  </div>
  <div class="kpi"><div class="l">เหตุการณ์วันนี้</div><div class="v">{{ number_format($stats['events_today']) }}</div></div>
  <div class="kpi"><div class="l">ล็อกอินพลาด 24 ชม.</div><div class="v">{{ number_format($stats['failed_logins']) }}</div></div>
</div>

<div class="grid g2" style="margin-bottom:18px">
  <div class="card">
    <div class="body">
      <h3 style="margin:0 0 11px;font-size:14px">แจ้งเตือนที่ต้องจัดการ</h3>
      @forelse($alerts as $a)
        <div class="ev">
          <span class="sev sev-{{ $a->severity === 'critical' ? 'critical' : ($a->severity === 'danger' ? 'high' : 'medium') }}"></span>
          <div class="g">
            <b>{{ $a->title }}</b>
            <small>{{ $a->created_at->diffForHumans() }} · {{ $a->alert_type }}</small>
          </div>
          <form method="POST" action="{{ route('admin.security.alert', $a) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="resolved">
            <button type="submit" class="btn btn-sm">ปิดงาน</button>
          </form>
        </div>
      @empty
        <div class="empty" style="padding:24px 0">ไม่มีแจ้งเตือนค้าง</div>
      @endforelse
    </div>
  </div>

  <div class="card">
    <div class="body">
      <h3 style="margin:0 0 11px;font-size:14px">เหตุการณ์ร้ายแรงที่ยังไม่ตรวจ</h3>
      @forelse($serious as $e)
        <div class="ev">
          <span class="sev sev-{{ $e->severity }}"></span>
          <div class="g">
            <b>{{ $e->message }}</b>
            <small>
              {{ $e->created_at->diffForHumans() }} ·
              {{ $e->actor_label ?? 'ไม่ระบุตัวตน' }} · {{ $e->ip_address }}
            </small>
          </div>
          <form method="POST" action="{{ route('admin.security.review', $e) }}">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-sm">ตรวจแล้ว</button>
          </form>
        </div>
      @empty
        <div class="empty" style="padding:24px 0">ไม่มีเหตุการณ์ร้ายแรง</div>
      @endforelse
    </div>
  </div>
</div>

<div class="card">
  <div class="body">
    <h3 style="margin:0 0 11px;font-size:14px">รายการที่ถูกระงับ ({{ $stats['blocked_active'] }})</h3>

    @forelse($blocked as $b)
      <div class="ev">
        <span class="sev sev-high"></span>
        <div class="g">
          <b>{{ $b->entity_type }}: {{ $b->entity_value }}</b>
          <small>
            {{ $b->reason }} ·
            {{ $b->block_type === 'permanent' ? 'ถาวร' : 'ถึง ' . optional($b->blocked_until)->format('j M H:i') }}
            · พยายามเข้า {{ $b->hit_count }} ครั้ง
          </small>
        </div>
        <form method="POST" action="{{ route('admin.security.unblock', $b) }}">
          @csrf @method('PATCH')
          <button type="submit" class="btn btn-sm">ปลดระงับ</button>
        </form>
      </div>
    @empty
      <div class="empty" style="padding:20px 0">ไม่มีรายการถูกระงับ</div>
    @endforelse

    <details style="margin-top:14px">
      <summary style="cursor:pointer;font-weight:700;font-size:13.5px">+ ระงับด้วยตนเอง</summary>
      <form method="POST" action="{{ route('admin.security.block') }}" style="margin-top:12px">
        @csrf
        <div class="grid g4">
          <div class="field"><label>ประเภท</label>
            <select class="input" name="entity_type">
              <option value="ip">IP Address</option>
              <option value="user">ผู้ใช้ระบบ</option>
              <option value="customer">ลูกค้า</option>
              <option value="phone">เบอร์โทร</option>
            </select></div>
          <div class="field"><label>ค่า</label>
            <input class="input" name="entity_value" maxlength="191" required placeholder="เช่น 1.2.3.4"></div>
          <div class="field"><label>ระยะเวลา (นาที)</label>
            <input class="input" type="number" name="minutes" value="60" min="1"></div>
          <div class="field"><label>เหตุผล</label>
            <input class="input" name="reason" maxlength="255" required></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:12px">
          <input type="checkbox" name="permanent" value="1" style="width:16px;height:16px;accent-color:var(--brand)">
          ระงับถาวร
        </label>
        <button type="submit" class="btn btn-d">ระงับการใช้งาน</button>
      </form>
    </details>
  </div>
</div>
@endsection
