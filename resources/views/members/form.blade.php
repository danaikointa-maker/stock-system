@extends('layouts.app')
@section('title', $member->exists ? 'แก้ไขสมาชิก' : 'เพิ่มสมาชิกใหม่')
@section('crumb', 'สมาชิกและสิทธิ์')

@section('content')

<form method="POST"
      action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}">
  @csrf
  @if($member->exists) @method('PUT') @endif

  <div class="grid g2">
    <div class="card">
      <h3>ข้อมูลสมาชิก</h3>
      <div class="body">
        <div class="field">
          <label for="name">ชื่อ–นามสกุล *</label>
          <input type="text" id="name" name="name" value="{{ old('name', $member->name) }}" required>
        </div>

        <div class="field">
          <label for="email">อีเมล</label>
          <input type="email" id="email" name="email" value="{{ old('email', $member->email) }}">
          <div style="font-size:11.5px;color:var(--muted);margin-top:3px">
            ใช้เข้าสู่ระบบได้ (ต้องกรอกอีเมลหรือเบอร์โทรอย่างน้อยหนึ่งอย่าง)
          </div>
        </div>

        <div class="field">
          <label for="phone">เบอร์โทรศัพท์</label>
          <input type="text" id="phone" name="phone" value="{{ old('phone', $member->phone) }}">
        </div>

        <div class="field">
          <label for="org_node_id">หน่วยงานที่สังกัด *</label>
          <select id="org_node_id" name="org_node_id" required>
            <option value="">— เลือกหน่วยงาน —</option>
            @foreach($nodes as $n)
              <option value="{{ $n->id }}" @selected(old('org_node_id', $member->org_node_id) == $n->id)>
                {{ str_repeat('— ', $n->depth) }}{{ $n->name }} ({{ $n->code }}) · {{ $n->level_id->label() }}
              </option>
            @endforeach
          </select>
          <div style="font-size:11.5px;color:var(--muted);margin-top:3px">
            เลือกได้เฉพาะหน่วยงานในสายงานที่คุณดูแล
          </div>
        </div>

        <div class="field">
          <label style="display:flex;align-items:center;gap:8px;font-weight:400">
            <input type="checkbox" name="is_active" value="1" style="width:auto"
                   @checked(old('is_active', $member->exists ? $member->is_active : true))>
            เปิดใช้งานบัญชีนี้
          </label>
        </div>
      </div>
    </div>

    <div class="card">
      <h3>บทบาทและสิทธิ์</h3>
      <div class="body">
        <div class="field">
          <label for="role">บทบาท *</label>
          <select id="role" name="role" required>
            @foreach($roles as $r)
              <option value="{{ $r->value }}" @selected(old('role', $member->role?->value) === $r->value)>
                {{ $r->label() }}
              </option>
            @endforeach
          </select>
        </div>

        @php
          $abilityLabels = [
            'manage-members' => 'จัดการสมาชิกในสายงาน', 'manage-nodes' => 'เปิด/แก้ไขหน่วยงานลูก',
            'approve-transfer' => 'อนุมัติใบโอนสินค้า', 'ship-stock' => 'ส่งสินค้าออก',
            'receive-stock' => 'รับสินค้าเข้า', 'sell' => 'เปิดบิลขาย',
            'adjust-stock' => 'ปรับยอดสต๊อก', 'view-reports' => 'ดูรายงาน',
            'manage-products' => 'จัดการข้อมูลสินค้า',
          ];
        @endphp

        <div style="background:#f7f9fc;border:1px solid var(--line);border-radius:8px;padding:13px">
          <div style="font-size:12px;font-weight:600;margin-bottom:9px;color:#42506b">
            สิทธิ์ที่ได้รับตามบทบาท
          </div>
          @foreach($roles as $r)
            <div style="display:none;font-size:12.5px" data-role="{{ $r->value }}" class="role-abilities">
              @foreach($abilityLabels as $key => $lbl)
                <div style="padding:3px 0;color:{{ $r->can($key) ? 'var(--ink)' : '#adb7c9' }}">
                  {{ $r->can($key) ? '✓' : '✕' }} {{ $lbl }}
                </div>
              @endforeach
            </div>
          @endforeach
        </div>

        <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line)">
          <div class="field">
            <label for="password">
              {{ $member->exists ? 'รหัสผ่านใหม่ (เว้นว่างถ้าไม่เปลี่ยน)' : 'รหัสผ่าน (เว้นว่างให้ระบบสุ่ม)' }}
            </label>
            <input type="password" id="password" name="password" autocomplete="new-password">
          </div>
          <div class="field">
            <label for="password_confirmation">ยืนยันรหัสผ่าน</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   autocomplete="new-password">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:9px">
    <button class="btn btn-p">{{ $member->exists ? 'บันทึกการแก้ไข' : 'เพิ่มสมาชิก' }}</button>
    <a href="{{ route('members.index') }}" class="btn">ยกเลิก</a>

    @if($member->exists)
      @can('delete', $member)
        <span style="flex:1"></span>
      @endcan
    @endif
  </div>
</form>

@if($member->exists)
  @can('delete', $member)
    <form method="POST" action="{{ route('members.destroy', $member) }}" style="margin-top:14px"
          onsubmit="return confirm('ยืนยันลบสมาชิก {{ $member->name }}?')">
      @csrf @method('DELETE')
      <button class="btn btn-d btn-sm">ลบสมาชิกนี้</button>
    </form>
  @endcan
@endif

<script>
(function () {
  var sel = document.getElementById('role');
  function sync() {
    document.querySelectorAll('.role-abilities').forEach(function (el) {
      el.style.display = el.dataset.role === sel.value ? 'block' : 'none';
    });
  }
  sel.addEventListener('change', sync);
  sync();
})();
</script>

@endsection
