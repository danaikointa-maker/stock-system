@extends('layouts.app')
@section('title', $node->exists ? 'แก้ไขหน่วยงาน' : 'เปิดหน่วยงานใหม่')
@section('crumb', 'หน่วยงานในสังกัด')

@section('content')

<form method="POST" action="{{ $node->exists ? route('nodes.update', $node) : route('nodes.store') }}">
  @csrf
  @if($node->exists) @method('PUT') @endif

  <div class="grid g2">
    <div class="card">
      <h3>ข้อมูลหน่วยงาน</h3>
      <div class="body">

        @if($node->exists)
          <div class="field">
            <label>ระดับชั้น</label>
            <input type="text" value="{{ $node->level_id->label() }}" disabled>
            <div style="font-size:11.5px;color:var(--muted);margin-top:3px">
              ระดับชั้นและหน่วยงานต้นสังกัดแก้ไขไม่ได้ เพราะกระทบโครงสร้างของลูกหลานทั้งสาย
            </div>
          </div>
          <div class="field">
            <label>สังกัด</label>
            <input type="text" value="{{ $node->parent?->name ?? 'ไม่มี (ระดับสูงสุด)' }}" disabled>
          </div>
        @else
          <div class="field">
            <label for="parent_id">หน่วยงานต้นสังกัด *</label>
            <select id="parent_id" name="parent_id" required>
              <option value="">— เลือกต้นสังกัด —</option>
              @foreach($parents as $p)
                <option value="{{ $p->id }}" @selected(old('parent_id') == $p->id)>
                  {{ str_repeat('— ', $p->depth) }}{{ $p->name }} ({{ $p->code }}) · {{ $p->level_id->label() }}
                </option>
              @endforeach
            </select>
            <div style="font-size:11.5px;color:var(--muted);margin-top:3px">
              ระบบจะกำหนดระดับชั้นให้อัตโนมัติเป็นระดับถัดจากต้นสังกัดที่เลือก
            </div>
          </div>
        @endif

        <div class="field">
          <label for="code">รหัสหน่วยงาน *</label>
          <input type="text" id="code" name="code" value="{{ old('code', $node->code) }}"
                 placeholder="เช่น SH-002" required>
        </div>

        <div class="field">
          <label for="name">ชื่อหน่วยงาน *</label>
          <input type="text" id="name" name="name" value="{{ old('name', $node->name) }}" required>
        </div>
      </div>
    </div>

    <div class="card">
      <h3>ข้อมูลติดต่อและเงื่อนไข</h3>
      <div class="body">
        <div class="field">
          <label for="phone">เบอร์โทรศัพท์</label>
          <input type="text" id="phone" name="phone" value="{{ old('phone', $node->phone) }}">
        </div>

        <div class="field">
          <label for="address">ที่อยู่</label>
          <textarea id="address" name="address" rows="3">{{ old('address', $node->address) }}</textarea>
        </div>

        <div class="field">
          <label for="credit_limit">วงเงินเครดิต (บาท)</label>
          <input type="number" step="0.01" min="0" id="credit_limit" name="credit_limit"
                 value="{{ old('credit_limit', $node->credit_limit ?? 0) }}">
        </div>

        <div class="field">
          <label for="status">สถานะ</label>
          <select id="status" name="status">
            <option value="active" @selected(old('status', $node->status) === 'active')>เปิดทำการ</option>
            <option value="suspended" @selected(old('status', $node->status) === 'suspended')>ระงับชั่วคราว</option>
            <option value="closed" @selected(old('status', $node->status) === 'closed')>ปิดกิจการ</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:9px">
    <button class="btn btn-p">{{ $node->exists ? 'บันทึกการแก้ไข' : 'เปิดหน่วยงาน' }}</button>
    <a href="{{ route('nodes.index') }}" class="btn">ยกเลิก</a>
  </div>
</form>

@endsection
