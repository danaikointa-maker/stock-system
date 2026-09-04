@extends('layouts.app')
@section('title', '🧾 ใบกำกับภาษี')
@section('crumb', 'บัญชี · ใบกำกับภาษี')

@section('content')
<div class="card"><div class="body">
  <a href="{{ route('accounting.tax-invoices.create') }}" class="btn btn-create">🧾 สร้างใบกำกับภาษีใหม่</a>
</div></div>
<div class="card">
  <table>
    <thead><tr><th>เลขที่</th><th>วันที่</th><th>ผู้ซื้อ</th><th>ประเภท</th><th class="num">ยอดก่อน VAT</th><th class="num">VAT</th><th class="num">รวมทั้งสิ้น</th><th></th></tr></thead>
    <tbody>
    @forelse($taxInvoices as $t)
      <tr>
        <td><code>{{ $t->tax_invoice_no }}</code></td>
        <td>{{ $t->issue_date->format('d/m/Y') }}</td>
        <td>{{ $t->buyer_name }}</td>
        <td><span class="badge b-blue">{{ $t->typeLabel() }}</span></td>
        <td class="num">{{ number_format($t->subtotal, 2) }}</td>
        <td class="num">{{ number_format($t->vat_amount, 2) }}</td>
        <td class="num"><b>{{ number_format($t->total, 2) }}</b></td>
        <td><a href="{{ route('accounting.tax-invoices.show', $t) }}" class="btn btn-sm btn-view">👁️</a></td>
      </tr>
    @empty
      <tr><td colspan="8" class="empty">ไม่พบใบกำกับภาษี</td></tr>
    @endforelse
    </tbody>
  </table>
  @if($taxInvoices->hasPages()) <div class="pager">{{ $taxInvoices->links() }}</div> @endif
</div>
@endsection
