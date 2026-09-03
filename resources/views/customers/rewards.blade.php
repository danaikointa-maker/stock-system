@extends('layouts.app')
@section('title', 'ของรางวัล')
@section('crumb', 'ของรางวัลและคำขอแลก')

@section('content')

@php
  $redeemLabel = ['pending'=>'รอจัดส่ง','approved'=>'อนุมัติแล้ว','shipped'=>'จัดส่งแล้ว','completed'=>'สำเร็จ','rejected'=>'ยกเลิก'];
  $redeemCls   = ['pending'=>'b-amber','approved'=>'b-blue','shipped'=>'b-green','completed'=>'b-green','rejected'=>'b-gray'];
@endphp

@error('redeem')<div class="alert a-bad">{{ $message }}</div>@enderror

<div class="card">
  <h3>คำขอแลกที่รอจัดส่ง ({{ $pending->count() }})</h3>
  <table>
    <thead>
      <tr><th>วันที่</th><th>ลูกค้า</th><th>ของรางวัล</th>
        <th class="num">คะแนนที่ใช้</th><th>ที่อยู่จัดส่ง</th><th>ดำเนินการ</th></tr>
    </thead>
    <tbody>
      @forelse($pending as $r)
        <tr>
          <td>{{ $r->created_at?->format('d/m/y H:i') }}</td>
          <td>
            <a href="{{ route('customers.show', $r->customer_id) }}">{{ $r->customer?->name }}</a>
            <div style="font-size:11px;color:var(--muted)">{{ $r->customer?->phone }}</div>
          </td>
          <td>{{ $r->reward?->name ?? '—' }}</td>
          <td class="num">{{ number_format($r->points_used) }}</td>
          <td style="font-size:12px;max-width:240px">{{ $r->address ?: '—' }}</td>
          <td>
            @can('manage-products')
            <div style="display:flex;gap:6px">
              <form method="POST" action="{{ route('customers.redemptions.ship', $r) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="shipped">
                <button type="submit" class="btn btn-p btn-sm">จัดส่งแล้ว</button>
              </form>
              <form method="POST" action="{{ route('customers.redemptions.ship', $r) }}"
                    onsubmit="return confirm('ยกเลิกรายการนี้และคืนคะแนนให้ลูกค้า?')">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="rejected">
                <button type="submit" class="btn btn-d btn-sm">ยกเลิก</button>
              </form>
            </div>
            @endcan
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="empty">ไม่มีคำขอที่รอจัดส่ง</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="grid g2">
  @can('manage-products')
  <div class="card">
    <h3>เพิ่มของรางวัล</h3>
    <div class="body">
      <form method="POST" action="{{ route('customers.rewards.store') }}">
        @csrf
        <div class="field">
          <label for="name">ชื่อของรางวัล *</label>
          <input type="text" id="name" name="name" value="{{ old('name') }}" required>
          @error('name')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="field">
          <label for="points_cost">ใช้กี่คะแนน *</label>
          <input type="number" min="1" id="points_cost" name="points_cost" value="{{ old('points_cost') }}" required>
          @error('points_cost')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="field">
          <label for="stock_qty">จำนวนที่มี *</label>
          <input type="number" min="0" id="stock_qty" name="stock_qty" value="{{ old('stock_qty') }}" required>
        </div>
        <div class="field">
          <label for="status">สถานะ *</label>
          <select id="status" name="status" required>
            <option value="active">เปิดให้แลก</option>
            <option value="inactive">ปิด</option>
          </select>
        </div>
        <button type="submit" class="btn btn-p">เพิ่มของรางวัล</button>
      </form>
    </div>
  </div>
  @endcan

  <div class="card">
    <h3>ประวัติการแลกล่าสุด</h3>
    <table>
      <thead><tr><th>วันที่</th><th>ลูกค้า</th><th>ของรางวัล</th><th>สถานะ</th></tr></thead>
      <tbody>
        @forelse($done as $r)
          <tr>
            <td>{{ $r->created_at?->format('d/m/y') }}</td>
            <td style="font-size:12px">{{ $r->customer?->phone }}</td>
            <td>{{ $r->reward?->name ?? '—' }}</td>
            <td><span class="badge {{ $redeemCls[$r->status] ?? 'b-gray' }}">{{ $redeemLabel[$r->status] ?? $r->status }}</span></td>
          </tr>
        @empty
          <tr><td colspan="4" class="empty">ยังไม่มีประวัติ</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3>ของรางวัลทั้งหมด</h3>
  <table>
    <thead>
      <tr><th>ชื่อ</th><th class="num">คะแนน</th><th class="num">คงเหลือ</th><th>สถานะ</th>
        @can('manage-products')<th>แก้ไข</th>@endcan</tr>
    </thead>
    <tbody>
      @forelse($rewards as $r)
        <tr>
          @can('manage-products')
            <form method="POST" action="{{ route('customers.rewards.update', $r) }}" id="rw{{ $r->id }}">
              @csrf @method('PATCH')
            </form>
            <td><input form="rw{{ $r->id }}" type="text" name="name" value="{{ $r->name }}" required></td>
            <td class="num"><input form="rw{{ $r->id }}" type="number" name="points_cost" min="1"
                   value="{{ $r->points_cost }}" style="width:100px;text-align:right" required></td>
            <td class="num"><input form="rw{{ $r->id }}" type="number" name="stock_qty" min="0"
                   value="{{ $r->stock_qty }}" style="width:100px;text-align:right" required></td>
            <td>
              <select form="rw{{ $r->id }}" name="status">
                <option value="active" @selected($r->status === 'active')>เปิด</option>
                <option value="inactive" @selected($r->status !== 'active')>ปิด</option>
              </select>
            </td>
            <td><button form="rw{{ $r->id }}" type="submit" class="btn btn-sm">บันทึก</button></td>
          @else
            <td>{{ $r->name }}</td>
            <td class="num">{{ number_format($r->points_cost) }}</td>
            <td class="num">{{ number_format($r->stock_qty) }}</td>
            <td>
              <span class="badge {{ $r->status === 'active' ? 'b-green' : 'b-gray' }}">
                {{ $r->status === 'active' ? 'เปิด' : 'ปิด' }}
              </span>
            </td>
          @endcan
        </tr>
      @empty
        <tr><td colspan="5" class="empty">ยังไม่มีของรางวัล</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@endsection
