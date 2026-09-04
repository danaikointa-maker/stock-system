@extends('layouts.app')
@section('title', '💸 บิลจ่าย')
@section('crumb', 'บัญชี · บิลจ่าย')

@section('content')
<div class="card"><div class="body">
  <a href="{{ route('accounting.payments.create') }}" class="btn btn-create">💸 สร้างบิลจ่ายใหม่</a>
</div></div>

<div class="card">
  <table>
    <thead><tr><th>เลขที่</th><th>วันที่</th><th>ผู้รับเงิน</th><th class="num">จำนวน</th><th>วิธี</th><th>หัก ณ ที่จ่าย</th><th></th></tr></thead>
    <tbody>
    @forelse($payments as $p)
      <tr>
        <td><code>{{ $p->payment_no }}</code></td>
        <td>{{ $p->payment_date->format('d/m/Y') }}</td>
        <td>{{ $p->payee_name }}</td>
        <td class="num"><b>{{ number_format($p->amount, 2) }}</b></td>
        <td>{{ $p->methodLabel() }}</td>
        <td>@if($p->withholdingTax)<span class="badge b-amber">WHT {{ $p->withholdingTax->wht_rate }}%</span>@else - @endif</td>
        <td><a href="{{ route('accounting.payments.show', $p) }}" class="btn btn-sm btn-view">👁️</a></td>
      </tr>
    @empty
      <tr><td colspan="7" class="empty">ไม่พบบิลจ่าย</td></tr>
    @endforelse
    </tbody>
  </table>
  @if($payments->hasPages()) <div class="pager">{{ $payments->links() }}</div> @endif
</div>
@endsection
