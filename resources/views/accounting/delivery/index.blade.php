@extends('layouts.app')
@section('title', '🚚 ใบส่งของ')
@section('crumb', 'บัญชี · ใบส่งของ')

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('accounting.delivery.create') }}" class="btn btn-p">➕ สร้างใบส่งของ</a>
  <a href="{{ route('accounting.dashboard') }}" class="btn">⬅️ กลับ Dashboard</a>
</div>

<div class="card">
  <table>
    <thead><tr>
      <th>เลขที่</th>
      <th>ลูกค้า</th>
      <th class="num">จำนวน</th>
      <th class="num">ยอดรวม</th>
      <th>สถานะ</th>
      <th>วันที่</th>
      <th></th>
    </tr></thead>
    <tbody>
    @forelse($notes as $n)
      <tr>
        <td><code>{{ $n->doc_no }}</code></td>
        <td>{{ $n->customer_name }}</td>
        <td class="num">{{ number_format($n->total_qty) }}</td>
        <td class="num">{{ number_format($n->total_amount, 2) }}</td>
        <td>
          @if($n->status === 'draft')
            <span class="badge warn">📝 ร่าง</span>
          @elseif($n->status === 'ready')
            <span class="badge info">📦 พร้อมส่ง</span>
          @elseif($n->status === 'shipped')
            <span class="badge info">🚚 ส่งแล้ว</span>
          @elseif($n->status === 'delivered')
            <span class="badge ok">✅ ถึงแล้ว</span>
          @elseif($n->status === 'returned')
            <span class="badge bad">↩️ คืน</span>
          @else
            <span class="badge bad">❌ ยกเลิก</span>
          @endif
        </td>
        <td>{{ $n->created_at->format('d/m/Y') }}</td>
        <td>
          <a href="{{ route('accounting.delivery.show', $n) }}" class="btn btn-sm btn-view">👁️</a>
        </td>
      </tr>
    @empty
      <tr><td colspan="7" class="empty">ไม่พบใบส่งของ</td></tr>
    @endforelse
    </tbody>
  </table>
  @if($notes->hasPages()) <div class="pager">{{ $notes->links() }}</div> @endif
</div>
@endsection
