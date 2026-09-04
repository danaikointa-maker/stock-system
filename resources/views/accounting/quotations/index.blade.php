@extends('layouts.app')
@section('title', '📋 ใบเสนอราคา')
@section('crumb', 'บัญชี · ใบเสนอราคา')

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('accounting.quotations.create') }}" class="btn btn-p">➕ สร้างใบเสนอราคา</a>
  <a href="{{ route('accounting.dashboard') }}" class="btn">⬅️ กลับ Dashboard</a>
</div>

<div class="card">
  <table>
    <thead><tr><th>เลขที่</th><th>ลูกค้า</th><th>วันที่</th><th>หมดอายุ</th><th class="num">ยอดรวม</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    @forelse($quotations as $q)
      <tr>
        <td><code>{{ $q->doc_no }}</code></td>
        <td>{{ $q->customer_name }}</td>
        <td>{{ $q->issue_date->format('d/m/Y') }}</td>
        <td>{{ $q->valid_until->format('d/m/Y') }}</td>
        <td class="num"><b>{{ number_format($q->total, 2) }}</b></td>
        <td><span class="badge {{ $q->statusBadge() }}">{{ $q->statusLabel() }}</span></td>
        <td><a href="{{ route('accounting.quotations.show', $q) }}" class="btn btn-sm btn-view">👁️</a></td>
      </tr>
    @empty
      <tr><td colspan="7" class="empty">ไม่พบใบเสนอราคา</td></tr>
    @endforelse
    </tbody>
  </table>
  @if($quotations->hasPages()) <div class="pager">{{ $quotations->links() }}</div> @endif
</div>
@endsection
