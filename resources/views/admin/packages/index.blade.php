@extends('layouts.app')
@section('title', 'แพ็กเกจและค่าตั้งค่าระบบ')

@push('head')
<style>
  .pkg-card{border:2px solid var(--line);border-radius:14px;padding:16px;margin-bottom:12px}
  .pkg-card.off{opacity:.55;background:#FAFAF7}
  .pkg-card .top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
  .pkg-card .nm{font-size:16px;font-weight:800}
  .pkg-card .cd{font-family:monospace;font-size:11.5px;color:var(--muted)}
  .pkg-card .price{font-size:22px;font-weight:800;color:var(--brand)}
  .pkg-card .spec{font-size:12.5px;color:var(--muted);margin-top:8px;line-height:1.8}
  .pkg-card .spec b{color:var(--ink)}
  .warnbox{background:#FFF3CD;border:1px solid #FFE082;border-radius:12px;
           padding:13px 15px;font-size:12.5px;color:#7A5C00;line-height:1.7;margin-bottom:14px}
</style>
@endpush

@section('content')
<h1 style="margin:0 0 4px">แพ็กเกจและค่าตั้งค่าระบบ</h1>
<p style="margin:0 0 18px;color:var(--muted);font-size:13.5px">
  เฉพาะเจ้าของระบบ · ตั้งแพ็กเกจให้ตัวแทนเลือกใช้ตอนสมัครร้าน
</p>

@if(session('status'))<div class="alert a-ok">{{ session('status') }}</div>@endif
@if($errors->any())
  <div class="alert a-bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

{{-- ค่าของแต้ม --}}
<div class="card" style="margin-bottom:18px">
  <div class="body">
    <h3 style="margin:0 0 4px;font-size:15px">ค่าของ 1 แต้ม</h3>
    <p class="hint" style="margin-bottom:13px">
      ใช้คำนวณเงินที่ร้านเบิกคืนได้ · แก้ได้เฉพาะเจ้าของระบบ · บันทึกประวัติทุกครั้ง
    </p>

    <div class="warnbox">
      <b>ค่าปัจจุบัน: {{ number_format($pointValue, 4) }} บาท/แต้ม</b><br>
      การเปลี่ยนค่านี้กระทบเงินทั้งระบบ แต่ไม่ย้อนหลังกับใบเบิกที่ออกไปแล้ว
      เพราะใบเบิกล็อกอัตราไว้ตอนสร้าง
    </div>

    <form method="POST" action="{{ route('admin.packages.point-value') }}">
      @csrf @method('PATCH')
      <div class="grid g2">
        <div class="field">
          <label for="pv">ค่าใหม่ (บาทต่อแต้ม)</label>
          <input class="input" type="number" id="pv" name="point_value_baht"
                 step="0.0001" min="0.0001" max="1000" value="{{ $pointValue }}" required>
        </div>
        <div class="field">
          <label for="pvr">เหตุผล <span style="color:var(--brand)">*</span></label>
          <input class="input" type="text" id="pvr" name="reason" maxlength="255" required
                 placeholder="เช่น ปรับตามนโยบายปี 2570">
        </div>
      </div>
      <button type="submit" class="btn btn-p">บันทึกค่าแต้มใหม่</button>
    </form>
  </div>
</div>

{{-- แพ็กเกจ --}}
<div class="card" style="margin-bottom:18px">
  <div class="body">
    <h3 style="margin:0 0 13px;font-size:15px">แพ็กเกจสมาชิก</h3>

    @foreach($packages as $p)
      <div class="pkg-card {{ $p->is_active ? '' : 'off' }}">
        <div class="top">
          <div>
            <div class="nm">{{ $p->name }}</div>
            <div class="cd">{{ $p->code }}</div>
            <div class="spec">
              อายุ <b>{{ $p->duration_months }} เดือน</b> ·
              <b>{{ number_format($p->monthly_point_limit) }}</b> แต้ม/เดือน ·
              คอมฯ <b>{{ number_format($p->agent_commission_pct, 0) }}%</b>
              @if($p->allow_rollover) · ยกยอดได้ @endif
              @if($p->allow_cash_redeem) · แลกเงินสดได้ @endif
              <br>ใช้อยู่ <b>{{ number_format($usage[$p->id] ?? 0) }}</b> ร้าน
            </div>
          </div>
          <div style="text-align:right">
            <div class="price">{{ number_format($p->price, 0) }} ฿</div>
            <div style="display:flex;gap:6px;margin-top:8px">
              <form method="POST" action="{{ route('admin.packages.toggle', $p) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-sm">{{ $p->is_active ? 'ปิด' : 'เปิด' }}</button>
              </form>
              <button type="button" class="btn btn-sm" onclick="toggleEdit({{ $p->id }})">แก้ไข</button>
            </div>
          </div>
        </div>

        <form method="POST" action="{{ route('admin.packages.update', $p) }}"
              id="edit{{ $p->id }}" style="display:none;margin-top:14px;border-top:1px dashed var(--line);padding-top:14px">
          @csrf @method('PUT')
          <div class="grid g2">
            <div class="field"><label>รหัส</label>
              <input class="input" name="code" value="{{ $p->code }}" maxlength="30" required></div>
            <div class="field"><label>ชื่อ</label>
              <input class="input" name="name" value="{{ $p->name }}" maxlength="120" required></div>
          </div>
          <div class="field"><label>คำโปรย</label>
            <input class="input" name="tagline" value="{{ $p->tagline }}" maxlength="200"></div>
          <div class="grid g4">
            <div class="field"><label>อายุ (เดือน)</label>
              <input class="input" type="number" name="duration_months" value="{{ $p->duration_months }}" min="1" required></div>
            <div class="field"><label>แต้ม/เดือน</label>
              <input class="input" type="number" name="monthly_point_limit" value="{{ $p->monthly_point_limit }}" min="0" required></div>
            <div class="field"><label>ราคา (บาท)</label>
              <input class="input" type="number" name="price" value="{{ $p->price }}" step="0.01" min="0" required></div>
            <div class="field"><label>คอมฯ (%)</label>
              <input class="input" type="number" name="agent_commission_pct" value="{{ $p->agent_commission_pct }}" step="0.01" min="0" max="100" required></div>
          </div>
          <div style="display:flex;gap:16px;margin-bottom:12px;font-size:13px">
            <label style="display:flex;align-items:center;gap:7px">
              <input type="hidden" name="allow_rollover" value="0">
              <input type="checkbox" name="allow_rollover" value="1" @checked($p->allow_rollover)
                     style="width:16px;height:16px;accent-color:var(--brand)"> ยกยอดข้ามเดือน
            </label>
            <label style="display:flex;align-items:center;gap:7px">
              <input type="hidden" name="allow_cash_redeem" value="0">
              <input type="checkbox" name="allow_cash_redeem" value="1" @checked($p->allow_cash_redeem)
                     style="width:16px;height:16px;accent-color:var(--brand)"> แลกเป็นเงินสดได้
            </label>
          </div>
          <button type="submit" class="btn btn-p btn-sm">บันทึก</button>
        </form>
      </div>
    @endforeach

    <details style="margin-top:14px">
      <summary style="cursor:pointer;font-weight:700;font-size:14px;padding:10px 0">+ เพิ่มแพ็กเกจใหม่</summary>
      <form method="POST" action="{{ route('admin.packages.store') }}" style="margin-top:12px">
        @csrf
        <div class="grid g2">
          <div class="field"><label>รหัส <span style="color:var(--brand)">*</span></label>
            <input class="input" name="code" maxlength="30" required placeholder="PKG-PLATINUM"></div>
          <div class="field"><label>ชื่อ <span style="color:var(--brand)">*</span></label>
            <input class="input" name="name" maxlength="120" required placeholder="แพ็กเกจแพลทินัม"></div>
        </div>
        <div class="field"><label>คำโปรย</label>
          <input class="input" name="tagline" maxlength="200"></div>
        <div class="grid g4">
          <div class="field"><label>อายุ (เดือน) <span style="color:var(--brand)">*</span></label>
            <input class="input" type="number" name="duration_months" min="1" value="12" required></div>
          <div class="field"><label>แต้ม/เดือน <span style="color:var(--brand)">*</span></label>
            <input class="input" type="number" name="monthly_point_limit" min="0" required></div>
          <div class="field"><label>ราคา (บาท) <span style="color:var(--brand)">*</span></label>
            <input class="input" type="number" name="price" step="0.01" min="0" required></div>
          <div class="field"><label>คอมฯ (%) <span style="color:var(--brand)">*</span></label>
            <input class="input" type="number" name="agent_commission_pct" step="0.01" min="0" max="100" value="10" required></div>
        </div>
        <button type="submit" class="btn btn-p">เพิ่มแพ็กเกจ</button>
      </form>
    </details>
  </div>
</div>

{{-- ค่าตั้งค่าอื่น --}}
<div class="card">
  <div class="body" style="padding:0">
    <div style="padding:16px 18px 8px">
      <h3 style="margin:0;font-size:15px">ค่าตั้งค่าระบบ</h3>
    </div>
    <table>
      <thead><tr><th>คีย์</th><th>คำอธิบาย</th><th>ค่า</th><th></th></tr></thead>
      <tbody>
        @foreach($settings as $s)
          <tr>
            <td style="font-family:monospace;font-size:11.5px">{{ $s->skey }}</td>
            <td style="font-size:12.5px;color:var(--muted)">{{ $s->description }}</td>
            <td>
              @if($s->skey === 'point_value_baht')
                <b>{{ $s->svalue }}</b>
                <span class="badge b-amber" style="margin-left:6px">แก้ด้านบน</span>
              @else
                <form method="POST" action="{{ route('admin.packages.setting') }}"
                      style="display:flex;gap:6px;align-items:center">
                  @csrf @method('PATCH')
                  <input type="hidden" name="skey" value="{{ $s->skey }}">
                  <input class="input" name="svalue" value="{{ $s->svalue }}"
                         style="max-width:150px;padding:7px 10px;font-size:13px">
                  <button type="submit" class="btn btn-sm">บันทึก</button>
                </form>
              @endif
            </td>
            <td></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection

@push('scripts')
<script>
function toggleEdit(id) {
  var el = document.getElementById('edit' + id);
  el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>
@endpush
