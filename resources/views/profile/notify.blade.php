@extends('layouts.app')
@section('title', 'การแจ้งเตือน')

@push('head')
<style>
  .lk{display:flex;gap:12px;align-items:center;padding:13px 15px;
      border:1.5px solid var(--line);border-radius:13px;margin-bottom:9px}
  .lk.off{opacity:.55;background:#FAFAF7}
  .lk .av{width:42px;height:42px;border-radius:50%;flex-shrink:0;overflow:hidden;
          background:#06C755;display:grid;place-items:center;color:#fff;font-weight:800}
  .lk .av img{width:100%;height:100%;object-fit:cover}
  .lk .g{flex:1;min-width:0}
  .lk .g b{display:block;font-size:14px}
  .lk .g small{color:var(--muted);font-size:11.5px}
  .nq{display:flex;justify-content:space-between;gap:10px;padding:10px 0;
      border-bottom:1px dashed var(--line);font-size:12.5px}
  .nq:last-child{border:0}
</style>
@endpush

@section('content')
<h1 style="margin:0 0 4px">การแจ้งเตือน</h1>
<p style="margin:0 0 18px;color:var(--muted);font-size:13.5px">
  ผูก LINE เพื่อรับแจ้งเตือนเมื่อมีลูกค้าแลกแต้ม ใบเบิกอัปเดต หรือวงเงินใกล้หมด
</p>

@if(session('status'))<div class="alert a-ok">{{ session('status') }}</div>@endif
@if($errors->any())
  <div class="alert a-bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<div class="grid g2" style="align-items:start">
  <div class="card">
    <div class="body">
      <h3 style="margin:0 0 4px;font-size:14px">
        LINE ที่ผูกไว้ ({{ $links->where('provider','line')->count() }}/{{ $maxLinks }})
      </h3>
      <p class="hint" style="margin-bottom:13px">
        ผูกได้หลายไอดี ทุกไอดีที่เปิดรับจะได้รับข้อความเหมือนกัน
      </p>

      @forelse($links as $l)
        <div class="lk {{ $l->notify_enabled ? '' : 'off' }}">
          <div class="av">
            @if($l->picture_url)
              <img src="{{ $l->picture_url }}" alt="">
            @else
              {{ mb_substr($l->display_name ?? 'L', 0, 1) }}
            @endif
          </div>
          <div class="g">
            <b>{{ $l->display_name ?? 'LINE User' }}</b>
            <small>
              {{ strtoupper($l->provider) }}
              @if($l->is_primary) · หลัก @endif
              · ผูกเมื่อ {{ $l->linked_at?->format('j M y') }}
            </small>
          </div>
          <form method="POST" action="{{ route('profile.notify.toggle', $l) }}">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-sm">{{ $l->notify_enabled ? 'ปิด' : 'เปิด' }}</button>
          </form>
          <form method="POST" action="{{ route('profile.notify.unlink', $l) }}"
                onsubmit="return confirm('ยกเลิกการผูกไอดีนี้?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-d">ลบ</button>
          </form>
        </div>
      @empty
        <div class="empty" style="padding:22px 0">
          ยังไม่ได้ผูก LINE<br>
          <small>ผูกแล้วจะได้รับแจ้งเตือนทันทีเมื่อมีความเคลื่อนไหว</small>
        </div>
      @endforelse

      @if($links->where('provider','line')->count() < $maxLinks)
        <form method="POST" action="{{ route('profile.notify.link') }}" style="margin-top:12px">
          @csrf
          <button type="submit" class="btn" style="width:100%;background:#06C755;color:#fff;border-color:#06C755">
            + ผูก LINE เพิ่ม
          </button>
        </form>
      @else
        <div class="alert a-info" style="margin:12px 0 0">
          ผูกครบ {{ $maxLinks }} ไอดีแล้ว · ลบไอดีเดิมก่อนถ้าต้องการเพิ่ม
        </div>
      @endif

      <p class="hint" style="margin-top:13px">
        อีเมล <b>{{ auth()->user()->email }}</b> รับแจ้งเตือนอยู่แล้วโดยอัตโนมัติ
      </p>
    </div>
  </div>

  <div class="card">
    <div class="body">
      <h3 style="margin:0 0 11px;font-size:14px">แจ้งเตือนล่าสุด</h3>
      @forelse($recent as $n)
        <div class="nq">
          <div style="flex:1;min-width:0">
            <b>{{ $n->subject }}</b>
            <div style="color:var(--muted);font-size:11px;margin-top:2px">
              {{ strtoupper($n->channel) }} · {{ $n->created_at?->format('j M H:i') }}
              @if($n->error_message)
                <br><span style="color:var(--bad)">{{ Str::limit($n->error_message, 60) }}</span>
              @endif
            </div>
          </div>
          <div>
            @switch($n->status)
              @case('sent')<span class="badge b-green">ส่งแล้ว</span>@break
              @case('pending')<span class="badge b-amber">รอส่ง</span>@break
              @case('sending')<span class="badge b-blue">กำลังส่ง</span>@break
              @case('failed')<span class="badge b-red">ล้มเหลว</span>@break
              @default<span class="badge b-gray">{{ $n->status }}</span>
            @endswitch
          </div>
        </div>
      @empty
        <div class="empty" style="padding:22px 0">ยังไม่มีแจ้งเตือน</div>
      @endforelse
    </div>
  </div>
</div>
@endsection
