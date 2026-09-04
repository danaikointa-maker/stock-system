@extends('layouts.app')
@section('title', '📄 บิลเรียกเก็บ')
@section('crumb', 'บัญชี · บิลเรียกเก็บ')

@section('content')

<div class="card">
  <div class="body">
    <form method="GET" class="filters">
      <div class="field"><label>ค้นหา</label><input type="text" name="q" value="{{ request('q') }}" placeholder="เลขที่ / ลูกค้า"></div>
      <div class="field"><label>สถานะ</label>
        <select name="status">
          <option value="">ทั้งหมด</option>
          @foreach(['draft'=>'ร่าง','issued'=>'ออกบิล','partial'=>'ชำระบางส่วน','paid'=>'ชำระแล้ว','overdue'=>'เกินกำหนด','void'=>'ยกเลิก'] as $k=>$v)
            <option value="{{ $k }}" {{ request('status')==$k?'selected':'' }}>{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <button class="btn btn-p">🔍 ค้นหา</button>
      <a href="{{ route('accounting.invoices.create') }}" class="btn btn-create">📄 สร้างบิลใหม่</a>
    </form>
  </div>
</div>

<div class="card">
  <table>
    <thead>
      <tr>
        <th>เลขที่</th><th>วันที่</th><th>ลูกค้า</th>
        <th class="num">ยอดรวม</th><th class="num">ค้างชำระ</th>
        <th>ครบกำหนด</th><th>สถานะ</th><th></th>
      </tr>
    </thead>
    <tbody>
    @forelse($invoices as $inv)
      <tr>
        <td><a href="{{ route('accounting.invoices.show', $inv) }}"><code>{{ $inv->invoice_no }}</code></a></td>
        <td>{{ $inv->invoice_date->format('d/m/Y') }}</td>
        <td>{{ $inv->customer_name }}</td>
        <td class="num"><b>{{ number_format($inv->total, 2) }}</b></td>
        <td class="num">{{ $inv->balance > 0 ? number_format($inv->balance, 2) : '-' }}</td>
        <td>{{ $inv->due_date->format('d/m/Y') }}</td>
        <td><span class="badge {{ $inv->statusBadge() }}">{{ $inv->statusLabel() }}</span></td>
        <td>
          <a href="{{ route('accounting.invoices.show', $inv) }}" class="btn btn-sm btn-view">👁️ ดู</a>
        </td>
      </tr>
    @empty
      <tr><td colspan="8" class="empty">ไม่พบบิลเรียกเก็บ</td></tr>
    @endforelse
    </tbody>
  </table>
  @if($invoices->hasPages())
    <div class="pager">{{ $invoices->links() }}</div>
  @endif
</div>
@endsection
