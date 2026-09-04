@extends('layouts.app')
@section('title', '📋 ใบหัก ณ ที่จ่าย')
@section('crumb', 'บัญชี · ใบหัก ณ ที่จ่าย')

@section('content')
<div class="card">
  <table>
    <thead><tr><th>เลขที่</th><th>วันที่</th><th>ผู้รับเงิน</th><th>ประเภทเงินได้</th><th class="num">ยอดก่อนหัก</th><th class="num">อัตรา</th><th class="num">จำนวนหัก</th><th class="num">จ่ายสุทธิ</th><th></th></tr></thead>
    <tbody>
    @forelse($taxes as $t)
      <tr>
        <td><code>{{ $t->wht_no }}</code></td>
        <td>{{ $t->issue_date->format('d/m/Y') }}</td>
        <td>{{ $t->payee_name }}</td>
        <td>{{ $t->income_type ?? '-' }}</td>
        <td class="num">{{ number_format($t->income_amount, 2) }}</td>
        <td class="num">{{ $t->wht_rate }}%</td>
        <td class="num"><b style="color:var(--bad-dark)">{{ number_format($t->wht_amount, 2) }}</b></td>
        <td class="num"><b style="color:var(--ok-dark)">{{ number_format($t->net_amount, 2) }}</b></td>
        <td><a href="{{ route('accounting.wht.show', $t) }}" class="btn btn-sm btn-view">👁️</a></td>
      </tr>
    @empty
      <tr><td colspan="9" class="empty">ไม่พบใบหัก ณ ที่จ่าย</td></tr>
    @endforelse
    </tbody>
  </table>
  @if($taxes->hasPages()) <div class="pager">{{ $taxes->links() }}</div> @endif
</div>
@endsection
