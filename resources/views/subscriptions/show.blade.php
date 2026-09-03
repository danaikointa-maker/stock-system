@extends('layouts.app')
@section('title', 'สมาชิก ' . $sub->code)

@push('head')
<style>
  .sub-head{
    background:linear-gradient(135deg,var(--brand),var(--brand-dark));
    color:#fff;border-radius:16px;padding:20px 22px;margin-bottom:18px;
  }
  .sub-head .code{font-family:monospace;font-size:13px;opacity:.9}
  .sub-head .nm{font-size:26px;font-weight:800;line-height:1.25;margin:4px 0}
  .sub-head .meta{font-size:13px;opacity:.94}
  .alw-row{display:flex;justify-content:space-between;align-items:center;
           padding:10px 0;border-bottom:1px dashed var(--line);font-size:13px}
  .alw-row:last-child{border:0}
  .bar{height:6px;background:#EEE;border-radius:99px;width:120px;overflow:hidden}
  .bar i{display:block;height:100%;background:var(--brand);border-radius:99px}
</style>
@endpush

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
  <h1 style="margin:0">รายละเอียดสมาชิก</h1>
  <a href="{{ route('subscriptions.index') }}" class="btn btn-sm">กลับ</a>
</div>

@if(session('status'))<div class="alert a-ok">{{ session('status') }}</div>@endif
@if($errors->any())
  <div class="alert a-bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<div class="sub-head">
  <div class="code">{{ $sub->code }}</div>
  <div class="nm">{{ $sub->shop->name ?? '—' }}</div>
  <div class="meta">
    {{ $sub->package->name ?? '—' }} ·
    {{ number_format($sub->monthly_point_limit) }} แต้ม/เดือน ·
    {{ $sub->starts_on->format('j M y') }} – {{ $sub->ends_on->format('j M y') }}
    @if($sub->status === 'active')
      · เหลืออีก {{ (int) now()->diffInDays($sub->ends_on, false) }} วัน
    @endif
  </div>
</div>

<div class="grid g2" style="margin-bottom:18px">
  <div class="card">
    <div class="body">
      <h3 style="margin:0 0 11px;font-size:14px">ข้อมูลสัญญา</h3>
      @foreach([
        'สถานะ'          => match($sub->status) {
          'active'=>'ใช้งานอยู่','pending_payment'=>'รอชำระเงิน','expired'=>'หมดอายุ',
          'cancelled'=>'ยกเลิก','suspended'=>'ระงับ', default=>$sub->status },
        'ค่าสมัคร'        => number_format($sub->price_paid, 2) . ' บาท',
        'คอมฯ ตัวแทน'    => number_format($sub->commission_amount, 2) . ' บาท',
        'ตัวแทนผู้แนะนำ'  => $sub->recruiter->name ?? '—',
        'ชำระเมื่อ'       => optional($sub->paid_at)->format('j M Y H:i') ?: '—',
        'ยกยอดข้ามเดือน'  => $sub->allow_rollover ? 'ได้' : 'ไม่ได้',
        'แลกเป็นเงินสด'   => $sub->allow_cash_redeem ? 'ได้' : 'ไม่ได้',
      ] as $k => $v)
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed var(--line);font-size:13.5px">
          <span style="color:var(--muted)">{{ $k }}</span>
          <span style="font-weight:600">{{ $v }}</span>
        </div>
      @endforeach

      @if($sub->cancel_reason)
        <div class="alert a-bad" style="margin-top:12px;margin-bottom:0">
          ยกเลิกเมื่อ {{ optional($sub->cancelled_at)->format('j M Y') }}<br>
          เหตุผล: {{ $sub->cancel_reason }}
        </div>
      @endif
    </div>
  </div>

  <div class="card">
    <div class="body">
      <h3 style="margin:0 0 11px;font-size:14px">การดำเนินการ</h3>

      @if($sub->status === 'pending_payment')
        <div class="alert a-info" style="margin-bottom:12px">
          รอร้านชำระ {{ number_format($sub->price_paid, 2) }} บาท<br>
          <small>ร้านจะยังรับแลกแต้มไม่ได้จนกว่าจะยืนยันการชำระ</small>
        </div>
        <form method="POST" action="{{ route('subscriptions.pay', $sub) }}">
          @csrf @method('PATCH')
          <div class="field">
            <label for="payment_ref">เลขอ้างอิงการชำระ</label>
            <input class="input" type="text" id="payment_ref" name="payment_ref" maxlength="120">
          </div>
          <button type="submit" class="btn btn-p" style="width:100%">ยืนยันการชำระเงิน</button>
        </form>

      @elseif($sub->status === 'active')
        <div class="alert a-ok" style="margin-bottom:12px">
          สมาชิกใช้งานได้ปกติ ร้านรับแลกแต้มได้
        </div>

        <form method="POST" action="{{ route('subscriptions.renew', $sub) }}" style="margin-bottom:12px">
          @csrf @method('PATCH')
          <div class="field">
            <label for="package_id">ต่ออายุด้วยแพ็กเกจ</label>
            <select class="input" id="package_id" name="package_id">
              <option value="">— ใช้แพ็กเกจเดิม ({{ $sub->package->name ?? '' }}) —</option>
              @foreach(\App\Models\ShopPackage::active()->get() as $p)
                <option value="{{ $p->id }}">{{ $p->name }} · {{ number_format($p->price, 0) }} บาท</option>
              @endforeach
            </select>
          </div>
          <button type="submit" class="btn" style="width:100%">ต่ออายุสมาชิก</button>
        </form>

        <details>
          <summary style="cursor:pointer;font-size:13px;color:var(--bad);font-weight:700">ยกเลิกสมาชิก</summary>
          <form method="POST" action="{{ route('subscriptions.cancel', $sub) }}" style="margin-top:11px">
            @csrf @method('PATCH')
            <div class="field">
              <label for="cancel_reason">เหตุผล <span style="color:var(--brand)">*</span></label>
              <input class="input" type="text" id="cancel_reason" name="cancel_reason" maxlength="255" required>
            </div>
            <button type="submit" class="btn btn-d" style="width:100%">ยืนยันยกเลิก</button>
          </form>
        </details>

      @elseif(in_array($sub->status, ['expired','cancelled'], true))
        <div class="alert a-bad" style="margin-bottom:12px">
          สมาชิกไม่พร้อมใช้งาน — ร้านรับแลกแต้มไม่ได้
        </div>
        <form method="POST" action="{{ route('subscriptions.renew', $sub) }}">
          @csrf @method('PATCH')
          <div class="field">
            <label for="package_id2">ต่ออายุด้วยแพ็กเกจ</label>
            <select class="input" id="package_id2" name="package_id">
              <option value="">— ใช้แพ็กเกจเดิม —</option>
              @foreach(\App\Models\ShopPackage::active()->get() as $p)
                <option value="{{ $p->id }}">{{ $p->name }} · {{ number_format($p->price, 0) }} บาท</option>
              @endforeach
            </select>
          </div>
          <button type="submit" class="btn btn-p" style="width:100%">ต่ออายุใหม่</button>
        </form>
      @endif
    </div>
  </div>
</div>

<div class="card">
  <div class="body">
    <h3 style="margin:0 0 11px;font-size:14px">ประวัติวงเงินรายเดือน</h3>
    @forelse($allowances as $a)
      @php
        $total = $a->limit_points + $a->rollover_points + $a->topup_points;
        $pct = $total > 0 ? round($a->used_points * 100 / $total) : 0;
      @endphp
      <div class="alw-row">
        <div style="min-width:70px"><b>{{ $a->period_ym }}</b></div>
        <div style="flex:1;color:var(--muted);font-size:12px">
          ใช้ {{ number_format($a->used_points) }} / {{ number_format($total) }} แต้ม
          @if($a->rollover_points > 0)
            <span style="color:var(--ok)">(ยกยอดมา {{ number_format($a->rollover_points) }})</span>
          @endif
        </div>
        <div class="bar"><i style="width:{{ min(100, $pct) }}%"></i></div>
        <div style="min-width:48px;text-align:right;font-weight:700">{{ $pct }}%</div>
      </div>
    @empty
      <div class="empty">ยังไม่มีข้อมูลวงเงิน</div>
    @endforelse
  </div>
</div>
@endsection
