@extends('layouts.app')
@section('title', '🛒 ใบสั่งซื้อ')
@section('crumb', 'บัญชี · ใบสั่งซื้อ')

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('accounting.po.create') }}" class="btn btn-p">➕ สร้างใบสั่งซื้อ</a>
  <a href="{{ route('accounting.dashboard') }}" class="btn">⬅️ กลับ Dashboard</a>
</div>

<div class="card">
  <table>
    <thead><tr><th>เลขที่</th><th>ผู้ขาย</th><th>วันที่</th><th class="num">ยอดรวม</th><th class="num">จ่ายสุทธิ</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    @forelse($pos as $po)
      <tr>
        <td><code>{{ $po->po_no }}</code></td>
        <td>{{ $po->vendor_name }}</td>
        <td>{{ $po->order_date->format('d/m/Y') }}</td>
        <td class="num">{{ number_format($po->total, 2) }}</td>
        <td class="num"><b>{{ number_format($po->net_total, 2) }}</b></td>
        <td><span class="badge {{ $po->statusBadge() }}">{{ $po->statusLabel() }}</span></td>
        <td><a href="{{ route('accounting.po.show', $po) }}" class="btn btn-sm btn-view">👁️</a></td>
      </tr>
    @empty
      <tr><td colspan="7" class="empty">ไม่พบใบสั่งซื้อ</td></tr>
    @endforelse
    </tbody>
  </table>
  @if($pos->hasPages()) <div class="pager">{{ $pos->links() }}</div> @endif
</div>
@endsection
