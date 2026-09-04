@extends('layouts.app')
@section('title', 'สมาชิกและสิทธิ์')
@section('crumb', 'จัดการผู้ใช้ในสายงานของคุณ')

@section('content')

<div class="card">
  <h3>
    ค้นหาสมาชิก
    @can('create', App\Models\User::class)
      <a href="{{ route('members.create') }}" class="btn btn-p btn-sm">👤 เพิ่มสมาชิก</a>
    @endcan
  </h3>
  <div class="body">
    <form method="GET" class="filters">
      <div class="field">
        <label>ค้นหา</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="ชื่อ / อีเมล / เบอร์โทร">
      </div>
      <div class="field">
        <label>หน่วยงาน</label>
        <select name="node_id">
          <option value="">ทั้งหมด</option>
          @foreach($nodes as $n)
            <option value="{{ $n->id }}" @selected(request('node_id') == $n->id)>
              {{ str_repeat('— ', $n->depth) }}{{ $n->name }} ({{ $n->code }})
            </option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>บทบาท</label>
        <select name="role">
          <option value="">ทั้งหมด</option>
          @foreach($roles as $r)
            <option value="{{ $r->value }}" @selected(request('role') === $r->value)>{{ $r->label() }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>สถานะ</label>
        <select name="status">
          <option value="">ทั้งหมด</option>
          <option value="active" @selected(request('status') === 'active')>ใช้งานอยู่</option>
          <option value="inactive" @selected(request('status') === 'inactive')>ถูกระงับ</option>
        </select>
      </div>
      <button class="btn btn-p">🔍 ค้นหา</button>
      <a href="{{ route('members.index') }}" class="btn">🔄 ล้าง</a>
    </form>
  </div>
</div>

<div class="card">
  <h3>รายชื่อสมาชิก ({{ number_format($members->total()) }} คน)</h3>

  @if($members->isEmpty())
    <div class="empty">ไม่พบสมาชิกตามเงื่อนไขที่ค้นหา</div>
  @else
    <table>
      <thead>
        <tr>
          <th>ชื่อ</th><th>ติดต่อ</th><th>หน่วยงาน</th>
          <th>บทบาท</th><th>สถานะ</th><th style="width:230px"></th>
        </tr>
      </thead>
      <tbody>
      @foreach($members as $m)
        <tr>
          <td><b>{{ $m->name }}</b></td>
          <td style="font-size:12.5px">
            {{ $m->email ?? '—' }}
            @if($m->phone)<div style="color:var(--muted)">{{ $m->phone }}</div>@endif
          </td>
          <td>
            {{ $m->node?->name ?? '—' }}
            <div style="font-size:11px"><code>{{ $m->node?->code }}</code></div>
          </td>
          <td>
            <span class="badge b-blue">{{ $m->role->label() }}</span>
            <div style="font-size:11px;color:var(--muted);margin-top:2px">
              {{ count($m->role->abilities()) }} สิทธิ์
            </div>
          </td>
          <td>
            @if($m->is_active)
              <span class="badge b-green">ใช้งานอยู่</span>
            @else
              <span class="badge b-red">ถูกระงับ</span>
            @endif
          </td>
          <td style="text-align:right">
            @can('update', $m)
              <a href="{{ route('members.edit', $m) }}" class="btn btn-sm btn-edit">✏️ แก้ไข</a>

              <form method="POST" action="{{ route('members.toggle', $m) }}" style="display:inline">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-d">{{ $m->is_active ? '⛔ ระงับ' : '✅ เปิดใช้' }}</button>
              </form>

              <form method="POST" action="{{ route('members.reset-password', $m) }}" style="display:inline"
                    onsubmit="return confirm('รีเซ็ตรหัสผ่านของ {{ $m->name }}?')">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-approve">🔑 รีเซ็ตรหัส</button>
              </form>
            @else
              <span style="color:var(--muted);font-size:12px">ไม่มีสิทธิ์จัดการ</span>
            @endcan
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>

    <div class="pager">{{ $members->links('partials.pagination') }}</div>
  @endif
</div>

<div class="card">
  <h3>ตารางสิทธิ์ตามบทบาท</h3>
  <div class="body" style="overflow-x:auto">
    @php
      $abilityLabels = [
        'manage-members' => 'จัดการสมาชิก', 'manage-nodes' => 'เปิดหน่วยงาน',
        'approve-transfer' => 'อนุมัติโอน', 'ship-stock' => 'ส่งของ',
        'receive-stock' => 'รับของ', 'sell' => 'เปิดบิลขาย',
        'adjust-stock' => 'ปรับยอด', 'view-reports' => 'ดูรายงาน',
        'manage-products' => 'จัดการสินค้า',
      ];
    @endphp
    <table>
      <thead>
        <tr>
          <th>บทบาท</th>
          @foreach($abilityLabels as $lbl)<th style="text-align:center">{{ $lbl }}</th>@endforeach
        </tr>
      </thead>
      <tbody>
      @foreach($roles as $r)
        <tr>
          <td><b>{{ $r->label() }}</b></td>
          @foreach(array_keys($abilityLabels) as $a)
            <td style="text-align:center">
              @if($r->can($a))
                <span style="color:var(--ok);font-weight:700">✓</span>
              @else
                <span style="color:#d5dce8">—</span>
              @endif
            </td>
          @endforeach
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>

@endsection
