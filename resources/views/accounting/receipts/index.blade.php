@extends('layouts.app')
@section('title', '💰 บิลรับ')
@section('crumb', 'บัญชี · บิลรับ')

@section('content')
<div class="card">
  <div class="body">
    <form method="GET" class="filters">
      <a href="{{ route('accounting.receipts.create') }}" class="btn btn-create">💰 สร้างบิลรับใหม่</a>
    </form>
  </div>
</div>

<div class="card">
  <table>
    <thead><tr><th>เลขที่</th><th>วันที่</th><th>ผู้จ่าย</th><th class="num">จำนวน</th><th>วิธี</th><th>บิลเรียกเก็บ</th><th></th></tr></thead>
    <tbody>
    @forelse($receipts as $r)
      <tr>
        <td><code>{{ $r->receipt_no }}</code></td>
        <td>{{ $r->receipt_date->format('d/m/Y') }}</td>
        <td>{{ $r->payer_name }}</td>
        <td class="num"><b>{{ number_format($r->amount, 2) }}</b></td>
        <td>{{ $r->methodLabel() }}</td>
        <td>@if($r->invoice)<a href="{{ route('accounting.invoices.show', $r->invoice) }}"><code>{{ $r->invoice->invoice_no }}</code></a>@else - @endif</td>
        <td><a href="{{ route('accounting.receipts.show', $r) }}" class="btn btn-sm btn-view">👁️</a></td>
      </tr>
    @empty
      <tr><td colspan="7" class="empty">ไม่พบบิลรับ</td></tr>
    @endforelse
    </tbody>
  </table>
  @if($receipts->hasPages()) <div class="pager">{{ $receipts->links() }}</div> @endif
</div>
@endsection
