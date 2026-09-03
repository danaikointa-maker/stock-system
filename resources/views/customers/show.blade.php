@extends('layouts.app')
@section('title', 'ลูกค้า ' . $customer->phone)
@section('crumb', 'ลูกค้าและคะแนนสะสม')

@section('content')

@php
  $typeLabel = [
    'earn_scan'=>'สแกน QR รับคะแนน','earn_bonus'=>'โบนัสพิเศษ','redeem'=>'แลกของรางวัล',
    'expire'=>'คะแนนหมดอายุ','adjust'=>'ปรับโดยเจ้าหน้าที่','reverse'=>'เรียกคืนคะแนน',
  ];
  $redeemLabel = ['pending'=>'รอจัดส่ง','approved'=>'อนุมัติแล้ว','shipped'=>'จัดส่งแล้ว','completed'=>'สำเร็จ','rejected'=>'ยกเลิก'];
  $redeemCls   = ['pending'=>'b-amber','approved'=>'b-blue','shipped'=>'b-green','completed'=>'b-green','rejected'=>'b-gray'];
@endphp

<div class="grid g4">
  <div class="kpi"><div class="lbl">คะแนนคงเหลือ</div><div class="val">{{ number_format($customer->points_balance) }}</div></div>
  <div class="kpi {{ $audit !== $customer->points_balance ? 'warn' : '' }}">
    <div class="lbl">ยอดคำนวณจากประวัติ</div><div class="val">{{ number_format($audit) }}</div>
  </div>
  <div class="kpi"><div class="lbl">จำนวนรายการ</div><div class="val">{{ number_format($history->count()) }}</div></div>
  <div class="kpi {{ $customer->isBlocked() ? 'bad' : 'ok' }}">
    <div class="lbl">สถานะ</div>
    <div class="val">{{ $customer->isBlocked() ? 'ถูกระงับ' : 'ปกติ' }}</div>
  </div>
</div>

@if($audit !== $customer->points_balance)
  <div class="alert a-bad">
    <b>ยอดคะแนนไม่ตรงกับประวัติ</b> — ยอดในบัญชี {{ number_format($customer->points_balance) }}
    แต่คำนวณจากรายการทั้งหมดได้ {{ number_format($audit) }}
    อาจเกิดจากการแก้ไขข้อมูลนอกระบบ ควรตรวจสอบ
  </div>
@endif

<div class="grid g2">
  <div class="card">
    <h3>ข้อมูลลูกค้า</h3>
    <table>
      <tr><th style="width:38%">เบอร์โทร</th><td><code>{{ $customer->phone }}</code></td></tr>
      <tr><th>ชื่อ</th><td>{{ $customer->name }}</td></tr>
      <tr><th>ระดับ</th><td>{{ $customer->tier ?? '—' }}</td></tr>
      <tr><th>แนะนำโดย</th><td>{{ $customer->referredByNode?->name ?? '—' }}</td></tr>
      <tr><th>สมัครเมื่อ</th><td>{{ $customer->created_at?->format('d/m/Y H:i') }}</td></tr>
    </table>
  </div>

  @can('manage-members')
  <div class="card">
    <h3>การจัดการ</h3>
    <div class="body">
      @error('points')<div class="alert a-bad">{{ $message }}</div>@enderror

      <form method="POST" action="{{ route('customers.points', $customer) }}">
        @csrf
        <div class="field">
          <label for="points">ปรับคะแนน (ใส่ค่าลบเพื่อหัก)</label>
          <input type="number" id="points" name="points" placeholder="เช่น 50 หรือ -20" required>
        </div>
        <div class="field">
          <label for="note">เหตุผล *</label>
          <input type="text" id="note" name="note" placeholder="ระบุเหตุผลเพื่อการตรวจสอบย้อนหลัง" required>
        </div>
        <button type="submit" class="btn btn-p">ปรับคะแนน</button>
      </form>

      <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">

      <form method="POST" action="{{ route('customers.toggle', $customer) }}"
            onsubmit="return confirm('ยืนยันเปลี่ยนสถานะลูกค้ารายนี้?')">
        @csrf @method('PATCH')
        <button type="submit" class="btn {{ $customer->isBlocked() ? '' : 'btn-d' }}">
          {{ $customer->isBlocked() ? 'ปลดระงับลูกค้า' : 'ระงับการรับคะแนน' }}
        </button>
        <div style="font-size:11.5px;color:var(--muted);margin-top:5px">
          ใช้เมื่อพบพฤติกรรมสแกนผิดปกติ ลูกค้าที่ถูกระงับจะสแกนรับคะแนนไม่ได้
        </div>
      </form>
    </div>
  </div>
  @endcan
</div>

@if($redeems->isNotEmpty())
<div class="card">
  <h3>ประวัติการแลกของรางวัล</h3>
  <table>
    <thead><tr><th>วันที่</th><th>ของรางวัล</th><th class="num">คะแนนที่ใช้</th><th>สถานะ</th></tr></thead>
    <tbody>
      @foreach($redeems as $d)
        <tr>
          <td>{{ $d->created_at?->format('d/m/y H:i') }}</td>
          <td>{{ $d->reward?->name ?? '—' }}</td>
          <td class="num">{{ number_format($d->points_used) }}</td>
          <td><span class="badge {{ $redeemCls[$d->status] ?? 'b-gray' }}">{{ $redeemLabel[$d->status] ?? $d->status }}</span></td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

<div class="card">
  <h3>ประวัติคะแนน (50 รายการล่าสุด)</h3>
  <table>
    <thead><tr><th>เวลา</th><th>ประเภท</th><th class="num">คะแนน</th><th class="num">ยอดหลังรายการ</th><th>หมายเหตุ</th></tr></thead>
    <tbody>
      @forelse($history as $h)
        <tr>
          <td>{{ $h->created_at?->format('d/m/y H:i') }}</td>
          <td>{{ $typeLabel[$h->type] ?? $h->type }}</td>
          <td class="num" style="font-weight:700;color:{{ $h->points >= 0 ? 'var(--ok)' : 'var(--bad)' }}">
            {{ $h->points >= 0 ? '+' : '' }}{{ number_format($h->points) }}
          </td>
          <td class="num">{{ isset($h->balance_after) ? number_format($h->balance_after) : '—' }}</td>
          <td style="font-size:12px;color:var(--muted)">{{ $h->note }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="empty">ยังไม่มีประวัติ</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<a href="{{ route('customers.index') }}" class="btn">← กลับรายชื่อลูกค้า</a>

@endsection
