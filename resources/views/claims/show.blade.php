@extends('layouts.app')
@section('title', 'ใบเบิก ' . $claim->code)

@push('head')
<style>
  .claim-head{
    background:linear-gradient(135deg,var(--brand),var(--brand-dark));
    color:#fff;border-radius:16px;padding:20px 22px;margin-bottom:18px;
  }
  .claim-head .code{font-family:monospace;font-size:13px;opacity:.9}
  .claim-head .amt{font-size:36px;font-weight:800;line-height:1.2;margin:4px 0}
  .claim-head .meta{font-size:13px;opacity:.94}
  .steps{display:flex;gap:0;margin-bottom:18px;flex-wrap:wrap}
  .step{
    flex:1;min-width:110px;padding:11px 8px;text-align:center;font-size:12px;
    background:#F2F2EC;color:var(--muted);position:relative;
  }
  .step:first-child{border-radius:10px 0 0 10px}
  .step:last-child{border-radius:0 10px 10px 0}
  .step.done{background:var(--ok);color:#fff;font-weight:700}
  .step.now{background:var(--brand);color:#fff;font-weight:700}
  .step.bad{background:var(--bad);color:#fff;font-weight:700}
</style>
@endpush

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px">
  <h1 style="margin:0">ใบเบิกเงิน</h1>
  <a href="{{ route('claims.index') }}" class="btn btn-sm">กลับ</a>
</div>

@if(session('status'))<div class="alert a-ok">{{ session('status') }}</div>@endif
@if($errors->any())
  <div class="alert a-bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

@if($claim->status === 'rejected' && $claim->reject_reason)
  <div class="alert a-bad">
    <b>ใบเบิกถูกปฏิเสธ</b><br>
    เหตุผล: {{ $claim->reject_reason }}<br>
    <small>รายการถูกปลดแล้ว คุณสามารถสร้างใบเบิกใหม่ได้จากหน้ารายการ</small>
  </div>
@endif

<div class="claim-head">
  <div class="code">{{ $claim->code }}</div>
  <div class="amt">{{ number_format($claim->total_amount, 2) }} ฿</div>
  <div class="meta">
    งวด {{ $claim->period_ym }} ·
    {{ number_format($claim->entry_count) }} รายการ ·
    {{ number_format($claim->total_points) }} แต้ม
    (แต้มละ {{ number_format($claim->point_value, 4) }} บาท)
  </div>
</div>

{{-- ขั้นตอน --}}
@php
  $order = ['draft' => 0, 'submitted' => 1, 'approved' => 2, 'paid' => 3];
  $cur = $order[$claim->status] ?? 0;
  $isRejected = $claim->status === 'rejected';
@endphp
<div class="steps">
  @foreach(['ร่าง', 'ยื่นแล้ว', 'อนุมัติ', 'จ่ายเงิน'] as $i => $label)
    <div class="step {{ $isRejected && $i > 0 ? 'bad' : ($i < $cur ? 'done' : ($i === $cur ? 'now' : '')) }}">
      {{ $isRejected && $i === 1 ? 'ถูกปฏิเสธ' : $label }}
    </div>
  @endforeach
</div>

<div class="grid g2" style="margin-bottom:18px">
  <div class="card">
    <div class="body">
      <h3 style="margin:0 0 11px;font-size:14px">ข้อมูลใบเบิก</h3>
      @foreach([
        'ร้าน'        => $shop->name ?? '—',
        'งวด'         => $claim->period_ym,
        'สร้างเมื่อ'   => optional($claim->created_at)->format('j M Y H:i'),
        'ยื่นเมื่อ'    => optional($claim->submitted_at)->format('j M Y H:i') ?: '—',
        'อนุมัติเมื่อ' => optional($claim->approved_at)->format('j M Y H:i') ?: '—',
        'จ่ายเมื่อ'    => optional($claim->paid_at)->format('j M Y H:i') ?: '—',
      ] as $k => $v)
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed var(--line);font-size:13.5px">
          <span style="color:var(--muted)">{{ $k }}</span>
          <span style="font-weight:600">{{ $v }}</span>
        </div>
      @endforeach

      @if($claim->payment_ref)
        <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:13.5px">
          <span style="color:var(--muted)">อ้างอิงการจ่าย</span>
          <span style="font-weight:600;font-family:monospace">{{ $claim->payment_ref }}</span>
        </div>
      @endif
    </div>
  </div>

  <div class="card">
    <div class="body">
      <h3 style="margin:0 0 11px;font-size:14px">การดำเนินการ</h3>

      @if($claim->status === 'draft')
        <p class="hint" style="margin-bottom:12px">
          ตรวจสอบรายการด้านล่างให้ครบถ้วน เมื่อยื่นแล้วจะแก้ไขไม่ได้
        </p>
        <form method="POST" action="{{ route('claims.submit', $claim) }}" style="margin-bottom:9px">
          @csrf @method('PATCH')
          <button type="submit" class="btn btn-p" style="width:100%">ยื่นใบเบิกให้เจ้าของระบบ</button>
        </form>
        <form method="POST" action="{{ route('claims.destroy', $claim) }}"
              onsubmit="return confirm('ยกเลิกใบร่างนี้? รายการจะถูกปลดให้เบิกใหม่ได้')">
          @csrf @method('DELETE')
          <button type="submit" class="btn btn-d" style="width:100%">ยกเลิกใบร่าง</button>
        </form>
      @elseif($claim->status === 'submitted')
        <div class="alert a-info" style="margin:0">
          ยื่นแล้ว รอเจ้าของระบบพิจารณา<br>
          <small>ระบบแจ้งเตือนไปยังผู้ดูแลแล้ว</small>
        </div>
      @elseif($claim->status === 'approved')
        <div class="alert a-ok" style="margin:0">
          อนุมัติแล้ว รอรับเงินโอน
          @if($claim->note)<br><small>หมายเหตุ: {{ $claim->note }}</small>@endif
        </div>
      @elseif($claim->status === 'paid')
        <div class="alert a-ok" style="margin:0">
          <b>จ่ายเงินเรียบร้อยแล้ว</b><br>
          <small>
            {{ match($claim->payment_method) {
              'transfer' => 'โอนเงิน', 'cash' => 'เงินสด', 'credit' => 'เครดิต', default => '—'
            } }}
            {{ $claim->payment_ref ? '· ' . $claim->payment_ref : '' }}
          </small>
        </div>
      @endif
    </div>
  </div>
</div>

<div class="card">
  <div class="body" style="padding:0">
    <table>
      <thead>
        <tr>
          <th>รหัส</th><th>วันเวลา</th><th>รายการ</th>
          <th>ประเภท</th><th class="num">แต้ม</th><th class="num">มูลค่า</th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $it)
          <tr>
            <td style="font-family:monospace;font-size:11.5px">{{ $it->code }}</td>
            <td>{{ optional($it->redeemed_at)->format('j M y H:i') }}</td>
            <td>{{ $it->reward_name }}</td>
            <td>
              @switch($it->redeem_type)
                @case('goods')<span class="badge b-blue">สินค้า</span>@break
                @case('service')<span class="badge b-green">บริการ</span>@break
                @case('discount')<span class="badge b-amber">ส่วนลด</span>@break
                @case('cash')<span class="badge b-gray">เงินสด</span>@break
              @endswitch
            </td>
            <td class="num">{{ number_format($it->points_used) }}</td>
            <td class="num">{{ number_format($it->cash_value, 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="empty">ไม่มีรายการ</td></tr>
        @endforelse
      </tbody>
      <tfoot>
        <tr style="background:#FAFAF6;font-weight:800">
          <td colspan="4" style="text-align:right">รวม</td>
          <td class="num">{{ number_format($claim->total_points) }}</td>
          <td class="num">{{ number_format($claim->total_amount, 2) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
@endsection
