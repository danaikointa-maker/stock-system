@extends('layouts.app')
@section('title', 'ตรวจสอบใบเบิก ' . $claim->code)

@push('head')
<style>
  .claim-head{
    background:linear-gradient(135deg,var(--brand),var(--brand-dark));
    color:#fff;border-radius:16px;padding:20px 22px;margin-bottom:18px;
  }
  .claim-head .code{font-family:monospace;font-size:13px;opacity:.9}
  .claim-head .amt{font-size:36px;font-weight:800;line-height:1.2;margin:4px 0}
  .claim-head .meta{font-size:13px;opacity:.94}
  .chain{font-size:12px;color:var(--muted);line-height:1.8}
  .chain b{color:var(--ink)}
</style>
@endpush

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px">
  <h1 style="margin:0">ตรวจสอบใบเบิกเงิน</h1>
  <a href="{{ route('admin.claims.index') }}" class="btn btn-sm">กลับ</a>
</div>

@if(session('status'))<div class="alert a-ok">{{ session('status') }}</div>@endif
@if($errors->any())
  <div class="alert a-bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<div class="claim-head">
  <div class="code">{{ $claim->code }}</div>
  <div class="amt">{{ number_format($claim->total_amount, 2) }} ฿</div>
  <div class="meta">
    {{ $claim->claimant->name ?? '—' }} · งวด {{ $claim->period_ym }} ·
    {{ number_format($claim->entry_count) }} รายการ ·
    {{ number_format($claim->total_points) }} แต้ม
  </div>
</div>

<div class="grid g2" style="margin-bottom:18px">
  <div class="card">
    <div class="body">
      <h3 style="margin:0 0 11px;font-size:14px">ข้อมูลร้าน</h3>
      <div class="chain">
        สายงาน:<br>
        @foreach($upline as $i => $name)
          {{ str_repeat('　', $i) }}{{ $i > 0 ? '└ ' : '' }}{{ $name }}<br>
        @endforeach
        {{ str_repeat('　', count($upline)) }}└ <b>{{ $claim->claimant->name ?? '' }}</b>
      </div>

      <div style="margin-top:14px">
        @foreach([
          'สถานะ'       => match($claim->status) {
            'draft'=>'ร่าง','submitted'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว',
            'paid'=>'จ่ายแล้ว','rejected'=>'ปฏิเสธ', default=>$claim->status },
          'ยื่นเมื่อ'   => optional($claim->submitted_at)->format('j M Y H:i') ?: '—',
          'อัตราแต้ม'   => number_format($claim->point_value, 4) . ' บาท/แต้ม',
        ] as $k => $v)
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed var(--line);font-size:13.5px">
            <span style="color:var(--muted)">{{ $k }}</span>
            <span style="font-weight:600">{{ $v }}</span>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="card">
    <div class="body">
      <h3 style="margin:0 0 11px;font-size:14px">การดำเนินการ</h3>

      @if($claim->status === 'submitted')
        <form method="POST" action="{{ route('admin.claims.approve', $claim) }}" style="margin-bottom:14px">
          @csrf @method('PATCH')
          <div class="field">
            <label for="note">หมายเหตุ (ถ้ามี)</label>
            <input class="input" type="text" id="note" name="note" maxlength="1000">
          </div>
          <button type="submit" class="btn btn-p" style="width:100%">อนุมัติใบเบิก</button>
        </form>

        <details>
          <summary style="cursor:pointer;font-size:13px;color:var(--bad);font-weight:700">ปฏิเสธใบเบิก</summary>
          <form method="POST" action="{{ route('admin.claims.reject', $claim) }}" style="margin-top:11px">
            @csrf @method('PATCH')
            <div class="field">
              <label for="reject_reason">เหตุผล <span style="color:var(--brand)">*</span></label>
              <input class="input" type="text" id="reject_reason" name="reject_reason"
                     maxlength="255" required placeholder="เช่น ยอดไม่ตรงกับรายงาน">
            </div>
            <button type="submit" class="btn btn-d" style="width:100%">ยืนยันปฏิเสธ</button>
          </form>
        </details>

      @elseif($claim->status === 'approved')
        <div class="alert a-info" style="margin-bottom:13px">
          อนุมัติแล้วเมื่อ {{ optional($claim->approved_at)->format('j M Y H:i') }}
          @if($claim->note)<br><small>{{ $claim->note }}</small>@endif
        </div>

        <form method="POST" action="{{ route('admin.claims.pay', $claim) }}">
          @csrf @method('PATCH')
          <div class="field">
            <label for="payment_method">วิธีการจ่าย <span style="color:var(--brand)">*</span></label>
            <select class="input" id="payment_method" name="payment_method" required>
              <option value="transfer">โอนเงิน</option>
              <option value="cash">เงินสด</option>
              <option value="credit">เครดิต / หักกลบ</option>
            </select>
          </div>
          <div class="field">
            <label for="payment_ref">เลขอ้างอิง / เลขที่สลิป</label>
            <input class="input" type="text" id="payment_ref" name="payment_ref" maxlength="120">
          </div>
          <button type="submit" class="btn btn-p" style="width:100%">บันทึกการจ่ายเงิน</button>
        </form>

        <details style="margin-top:13px">
          <summary style="cursor:pointer;font-size:13px;color:var(--bad);font-weight:700">ยกเลิกการอนุมัติ</summary>
          <form method="POST" action="{{ route('admin.claims.reject', $claim) }}" style="margin-top:11px">
            @csrf @method('PATCH')
            <div class="field">
              <label for="reject_reason2">เหตุผล <span style="color:var(--brand)">*</span></label>
              <input class="input" type="text" id="reject_reason2" name="reject_reason" maxlength="255" required>
            </div>
            <button type="submit" class="btn btn-d" style="width:100%">ยืนยันยกเลิก</button>
          </form>
        </details>

      @elseif($claim->status === 'paid')
        <div class="alert a-ok" style="margin:0">
          <b>จ่ายเงินเรียบร้อยแล้ว</b><br>
          {{ optional($claim->paid_at)->format('j M Y H:i') }} ·
          {{ match($claim->payment_method) {
            'transfer'=>'โอนเงิน','cash'=>'เงินสด','credit'=>'เครดิต', default=>'—' } }}
          @if($claim->payment_ref)<br><small>อ้างอิง: {{ $claim->payment_ref }}</small>@endif
        </div>

      @elseif($claim->status === 'rejected')
        <div class="alert a-bad" style="margin:0">
          <b>ปฏิเสธแล้ว</b><br>
          {{ $claim->reject_reason }}<br>
          <small>รายการถูกปลดให้ร้านยื่นใหม่ได้</small>
        </div>

      @else
        <div class="alert a-info" style="margin:0">
          ใบเบิกยังเป็นร่าง ร้านยังไม่ได้ยื่นเข้ามา
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
