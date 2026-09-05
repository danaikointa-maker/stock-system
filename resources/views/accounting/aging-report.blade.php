@extends('layouts.app')
@section('title', '⏳ Aging Report (AR/AP)')
@section('crumb', 'บัญชี · ลูกหนี้/เจ้าหนี้คงค้าง')

@section('content')
<div class="grid g2">
  {{-- AR — Receivables --}}
  <div class="card">
    <div class="section-bar alert">⏳ ลูกหนี้ค้างรับ (A/R)</div>
    @if($receivables->isEmpty())
      <div class="empty">✅ ไม่มีลูกหนี้คงค้าง</div>
    @else
      <table>
        <thead><tr><th>บิล</th><th>ลูกค้า</th><th>กำหนดชำระ</th><th class="num">ยอดค้าง</th><th class="num">เกินกำหนด</th></tr></thead>
        <tbody>
        @foreach($receivables as $r)
          @php $daysOverdue = now()->diffInDays($r->due_date); @endphp
          <tr>
            <td><a href="{{ route('accounting.invoices.show', $r->id) }}"><code>{{ $r->invoice_no }}</code></a></td>
            <td>{{ $r->customer_name }}</td>
            <td>{{ $r->due_date->format('d/m/Y') }}</td>
            <td class="num"><b style="color:var(--bad-dark)">{{ number_format($r->balance, 2) }}</b></td>
            <td class="num">
              @if($daysOverdue > 90)
                <span class="badge bad">{{ $daysOverdue }} วัน</span>
              @elseif($daysOverdue > 30)
                <span class="badge bad">{{ $daysOverdue }} วัน</span>
              @elseif($daysOverdue > 0)
                <span class="badge warn">{{ $daysOverdue }} วัน</span>
              @else
                <span class="badge ok">ยังไม่เกิน</span>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
        <tfoot><tr style="background:#fef3c7"><td colspan="3"><b>รวม</b></td><td class="num"><b>{{ number_format($receivables->sum('balance'), 2) }}</b></td><td></td></tr></tfoot>
      </table>
    @endif
  </div>

  {{-- AP — Payables --}}
  <div class="card">
    <div class="section-bar report">📋 เจ้าหนี้ค้างจ่าย (A/P)</div>
    @if($payables->isEmpty())
      <div class="empty">✅ ไม่มีเจ้าหนี้ค้างจ่าย</div>
    @else
      <table>
        <thead><tr><th>PO</th><th>ผู้ขาย</th><th class="num">ยอดค้าง</th><th>กำหนดรับ</th></tr></thead>
        <tbody>
        @foreach($payables as $p)
          <tr>
            <td><code>{{ $p->po_no }}</code></td>
            <td>{{ $p->vendor_name }}</td>
            <td class="num"><b>{{ number_format($p->net_total, 2) }}</b></td>
            <td>{{ $p->expected_date ? $p->expected_date->format('d/m/Y') : '-' }}</td>
          </tr>
        @endforeach
        </tbody>
        <tfoot><tr style="background:#dbeafe"><td colspan="2"><b>รวม</b></td><td class="num"><b>{{ number_format($payables->sum('net_total'), 2) }}</b></td><td></td></tr></tfoot>
      </table>
    @endif
  </div>
</div>

<div style="margin-top:16px">
  <a href="{{ route('accounting.dashboard') }}" class="btn">⬅️ กลับ Dashboard</a>
</div>
@endsection
