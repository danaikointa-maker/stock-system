@extends('layouts.app')
@section('title', '📋 Stock Ledger')
@section('crumb', 'บัญชี · Stock Ledger (Audit Trail)')

@section('content')
<div class="card">
  <div class="section-bar">🔍 ค้นหา</div>
  <div class="body">
    <form method="GET" class="filters">
      <div class="field"><label>สินค้า</label>
        <select name="product_id">
          <option value="">ทั้งหมด</option>
          @foreach($products as $p)
            <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field"><label>ประเภท</label>
        <select name="movement_type">
          <option value="">ทั้งหมด</option>
          <option value="receipt" {{ request('movement_type') === 'receipt' ? 'selected' : '' }}>📥 รับเข้า</option>
          <option value="sale" {{ request('movement_type') === 'sale' ? 'selected' : '' }}>🛒 ขายออก</option>
          <option value="delivery" {{ request('movement_type') === 'delivery' ? 'selected' : '' }}>🚚 ส่งของ</option>
          <option value="transfer_out" {{ request('movement_type') === 'transfer_out' ? 'selected' : '' }}>⬆️ โอนออก</option>
          <option value="transfer_in" {{ request('movement_type') === 'transfer_in' ? 'selected' : '' }}>⬇️ โอนเข้า</option>
          <option value="return_in" {{ request('movement_type') === 'return_in' ? 'selected' : '' }}>↩️ รับคืน</option>
          <option value="adjust_in" {{ request('movement_type') === 'adjust_in' ? 'selected' : '' }}>🔧 ปรับเพิ่ม</option>
          <option value="adjust_out" {{ request('movement_type') === 'adjust_out' ? 'selected' : '' }}>🔧 ปรับลด</option>
          <option value="damage" {{ request('movement_type') === 'damage' ? 'selected' : '' }}>💥 เสียหาย</option>
          <option value="expired" {{ request('movement_type') === 'expired' ? 'selected' : '' }}>⏰ หมดอายุ</option>
        </select>
      </div>
      <div class="field"><label>ตั้งแต่</label><input type="date" name="from" value="{{ request('from') }}"></div>
      <div class="field"><label>ถึง</label><input type="date" name="to" value="{{ request('to') }}"></div>
      <button class="btn btn-p">🔍 ค้นหา</button>
      <a href="{{ route('accounting.stock-ledger') }}" class="btn">🔄 ล้าง</a>
    </form>
  </div>
</div>

<div style="margin:12px 0;display:flex;gap:8px">
  <a href="{{ route('accounting.audit') }}" class="btn btn-blue">🔍 ตรวจสอบยอดตรง (Audit)</a>
</div>

<div class="card">
  <div class="section-bar" style="background:#1e293b;color:#fff">
    📋 Stock Ledger — IMMUTABLE (แก้ไข/ลบไม่ได้) · รวม {{ $ledgers->total() }} รายการ
  </div>
  <table>
    <thead><tr>
      <th>#</th>
      <th>วันที่</th>
      <th>สาขา</th>
      <th>สินค้า</th>
      <th>ประเภท</th>
      <th class="num">จำนวน</th>
      <th class="num">ต้นทุน/หน่วย</th>
      <th class="num">มูลค่า</th>
      <th class="num">คงเหลือ</th>
      <th>เอกสาร</th>
      <th>ผู้ทำ</th>
    </tr></thead>
    <tbody>
    @forelse($ledgers as $l)
      <tr>
        <td>{{ $l->id }}</td>
        <td>{{ $l->created_at->format('d/m/Y H:i') }}</td>
        <td>{{ $l->node->name ?? '-' }}</td>
        <td>{{ $l->product->name ?? '-' }}</td>
        <td>
          <span style="color:{{ $l->direction === 'in' ? 'var(--ok-dark)' : 'var(--bad-dark)' }};font-weight:700">
            {{ $l->movement_label }}
          </span>
        </td>
        <td class="num">
          <b style="color:{{ $l->direction === 'in' ? 'var(--ok-dark)' : 'var(--bad-dark)' }}">
            {{ $l->direction === 'in' ? '+' : '-' }}{{ number_format($l->qty) }}
          </b>
        </td>
        <td class="num">{{ number_format($l->unit_cost, 2) }}</td>
        <td class="num">{{ number_format($l->total_cost, 2) }}</td>
        <td class="num"><b>{{ number_format($l->balance_after) }}</b></td>
        <td>
          @if($l->journal_entry_ref)
            <code style="font-size:10px">{{ $l->journal_entry_ref }}</code>
          @endif
          @if($l->note)
            <div style="font-size:10px;color:var(--muted)">{{ Str::limit($l->note, 25) }}</div>
          @endif
        </td>
        <td>{{ $l->creator->name ?? '-' }}</td>
      </tr>
    @empty
      <tr><td colspan="11" class="empty">ไม่มีข้อมูล</td></tr>
    @endforelse
    </tbody>
  </table>
  @if($ledgers->hasPages()) <div class="pager">{{ $ledgers->links() }}</div> @endif
</div>

<div style="margin-top:12px;padding:12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:12px;color:#92400e">
  <b>⚠️ Stock Ledger เป็น IMMUTABLE:</b> ทุก movement ถูกเก็บถาวร แก้ไข/ลบไม่ได้ — ถ้าผิดพลาดให้สร้าง reversal entry (รายการกลับทาง) เท่านั้น
</div>
@endsection
