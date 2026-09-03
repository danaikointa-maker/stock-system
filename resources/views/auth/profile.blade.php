@extends('layouts.app')
@section('title', 'บัญชีของฉัน')
@section('crumb', 'ข้อมูลส่วนตัวและรหัสผ่าน')

@section('content')

@php $u = auth()->user(); @endphp

<div class="grid g2">
  <div class="card">
    <h3>ข้อมูลบัญชี</h3>
    <div class="body">
      <table>
        <tbody>
          <tr><th style="width:130px">ชื่อ</th><td>{{ $u->name }}</td></tr>
          <tr><th>อีเมล</th><td>{{ $u->email ?? '—' }}</td></tr>
          <tr><th>เบอร์โทร</th><td>{{ $u->phone ?? '—' }}</td></tr>
          <tr><th>หน่วยงาน</th><td>{{ $u->node?->name }} <code>{{ $u->node?->code }}</code></td></tr>
          <tr><th>ระดับชั้น</th><td><span class="badge b-blue">{{ $u->level()?->label() }}</span></td></tr>
          <tr><th>บทบาท</th><td><span class="badge b-green">{{ $u->role->label() }}</span></td></tr>
        </tbody>
      </table>

      <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line)">
        <div style="font-size:12px;font-weight:600;margin-bottom:8px;color:#42506b">สิทธิ์ที่คุณมี</div>
        @php
          $abilityLabels = [
            'manage-members' => 'จัดการสมาชิกในสายงาน', 'manage-nodes' => 'เปิด/แก้ไขหน่วยงานลูก',
            'approve-transfer' => 'อนุมัติใบโอนสินค้า', 'ship-stock' => 'ส่งสินค้าออก',
            'receive-stock' => 'รับสินค้าเข้า', 'sell' => 'เปิดบิลขาย',
            'adjust-stock' => 'ปรับยอดสต๊อก', 'view-reports' => 'ดูรายงาน',
            'manage-products' => 'จัดการข้อมูลสินค้า',
          ];
        @endphp
        @foreach($u->role->abilities() as $a)
          <span class="badge b-blue" style="margin:2px">{{ $abilityLabels[$a] ?? $a }}</span>
        @endforeach
      </div>
    </div>
  </div>

  <div class="card">
    <h3>เปลี่ยนรหัสผ่าน</h3>
    <div class="body">
      <form method="POST" action="{{ route('profile.password') }}">
        @csrf @method('PUT')

        <div class="field">
          <label for="current_password">รหัสผ่านปัจจุบัน *</label>
          <input type="password" id="current_password" name="current_password"
                 autocomplete="current-password" required>
        </div>

        <div class="field">
          <label for="password">รหัสผ่านใหม่ * (อย่างน้อย 8 ตัวอักษร)</label>
          <input type="password" id="password" name="password" autocomplete="new-password" required>
        </div>

        <div class="field">
          <label for="password_confirmation">ยืนยันรหัสผ่านใหม่ *</label>
          <input type="password" id="password_confirmation" name="password_confirmation"
                 autocomplete="new-password" required>
        </div>

        <button class="btn btn-p">เปลี่ยนรหัสผ่าน</button>
      </form>
    </div>
  </div>
</div>

@endsection
