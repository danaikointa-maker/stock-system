@extends('layouts.app')
@section('title', '📊 บัญชีและการเงิน')
@section('crumb', 'ระบบบัญชี · ' . auth()->user()->node?->name)

@section('content')

{{-- Quick links --}}
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <a href="{{ route('accounting.invoices.create') }}" class="btn btn-create">📄 สร้างบิลเรียกเก็บ</a>
  <a href="{{ route('accounting.receipts.create') }}" class="btn btn-ok">💰 สร้างบิลรับ</a>
  <a href="{{ route('accounting.payments.create') }}" class="btn btn-ship">💸 สร้างบิลจ่าย</a>
  <a href="{{ route('accounting.delivery.create') }}" class="btn btn-view">🚚 ใบส่งของ</a>
  <a href="{{ route('accounting.credit.create') }}" class="btn" style="background:#fef2f2;color:#b91c1c">↩️ ใบลดหนี้</a>
  <a href="{{ route('accounting.stock-ledger') }}" class="btn btn-blue">📋 Ledger</a>
  <a href="{{ route('accounting.audit') }}" class="btn btn-approve">🔍 Audit</a>
</div>

{{-- KPI Cards --}}
<div class="grid g4" style="margin-bottom:20px">
  <div class="kpi">
    <div class="lbl">📄 ยอดบิลเรียกเก็บ</div>
    <div class="val">{{ number_format($totalInvoiced, 0) }}</div>
    <div class="sub">บาท (ทั้งหมด)</div>
  </div>
  <div class="kpi ok">
    <div class="lbl">💰 ยอดรับ</div>
    <div class="val">{{ number_format($totalReceived, 0) }}</div>
    <div class="sub">บาท</div>
  </div>
  <div class="kpi bad">
    <div class="lbl">💸 ยอดจ่าย</div>
    <div class="val">{{ number_format($totalPaid, 0) }}</div>
    <div class="sub">บาท</div>
  </div>
  <div class="kpi {{ $outstanding > 0 ? 'warn' : '' }}">
    <div class="lbl">⏳ หนี้คงค้าง</div>
    <div class="val">{{ number_format($outstanding, 0) }}</div>
    <div class="sub">{{ $overdueCount }} บิลเกินกำหนด ({{ number_format($overdueAmount, 0) }})</div>
  </div>
</div>

<div class="grid g4" style="margin-bottom:20px">
  <div class="kpi">
    <div class="lbl">🚚 ใบส่งของ</div>
    <div class="val">{{ number_format($totalDeliveries) }}</div>
    <div class="sub">{{ $pendingShip }} พร้อมส่ง</div>
  </div>
  <div class="kpi">
    <div class="lbl">↩️ ใบลดหนี้ (ยืนยันแล้ว)</div>
    <div class="val">{{ number_format($totalCredits, 2) }}</div>
    <div class="sub">{{ $pendingCredits }} รอยืนยัน</div>
  </div>
  <div class="kpi">
    <div class="lbl">📋 Stock Ledger</div>
    <div class="val">IMMUTABLE</div>
    <div class="sub">แก้ไข/ลบไม่ได้</div>
  </div>
  <div class="kpi">
    <div class="lbl">🔍 Audit</div>
    <div class="val">ตรวจสอบได้</div>
    <div class="sub">ยอดตรง แม่นยำ</div>
  </div>
</div>

<div class="grid g2">

  {{-- บิลเรียกเก็บล่าสุด --}}
  <div class="card">
    <div class="section-bar sales">
      📄 บิลเรียกเก็บล่าสุด
      <a href="{{ route('accounting.invoices') }}" class="btn btn-sm" style="margin-left:auto;background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.3);color:#fff">ดูทั้งหมด</a>
    </div>
    @if($recentInvoices->isEmpty())
      <div class="empty">ยังไม่มีบิลเรียกเก็บ</div>
    @else
      <table>
        <thead><tr><th>เลขที่</th><th>ลูกค้า</th><th class="num">ยอด</th><th>สถานะ</th></tr></thead>
        <tbody>
        @foreach($recentInvoices as $inv)
          <tr>
            <td><a href="{{ route('accounting.invoices.show', $inv) }}"><code>{{ $inv->invoice_no }}</code></a></td>
            <td>{{ $inv->customer_name }}</td>
            <td class="num"><b>{{ number_format($inv->total, 2) }}</b></td>
            <td><span class="badge {{ $inv->statusBadge() }}">{{ $inv->statusLabel() }}</span></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>

  {{-- บิลรับล่าสุด --}}
  <div class="card">
    <div class="section-bar stock">
      💰 บิลรับล่าสุด
      <a href="{{ route('accounting.receipts') }}" class="btn btn-sm" style="margin-left:auto;background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.3);color:#fff">ดูทั้งหมด</a>
    </div>
    @if($recentReceipts->isEmpty())
      <div class="empty">ยังไม่มีบิลรับ</div>
    @else
      <table>
        <thead><tr><th>เลขที่</th><th>ผู้จ่าย</th><th class="num">ยอด</th><th>วิธี</th></tr></thead>
        <tbody>
        @foreach($recentReceipts as $rcp)
          <tr>
            <td><a href="{{ route('accounting.receipts.show', $rcp) }}"><code>{{ $rcp->receipt_no }}</code></a></td>
            <td>{{ $rcp->payer_name }}</td>
            <td class="num"><b>{{ number_format($rcp->amount, 2) }}</b></td>
            <td>{{ $rcp->methodLabel() }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>

</div>

{{-- Chart ยอดบิลรายเดือน --}}
<div class="card" style="margin-top:20px">
  <div class="section-bar report">📊 ยอดบิลเรียกเก็บรายเดือน ({{ now()->year }})</div>
  <div class="body">
    <div class="chart-wrap"><canvas id="monthlyChart"></canvas></div>
  </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof Chart === 'undefined') return;
  var months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
  var data = @json($monthlyInvoices);
  var values = [];
  for (var i = 1; i <= 12; i++) values.push(data[i] || 0);
  new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
      labels: months,
      datasets: [{
        label: 'ยอดบิล (บาท)',
        data: values,
        backgroundColor: values.map(function(v){ return v > 0 ? '#10b981' : 'rgba(148,163,184,.15)'; }),
        borderRadius: 6, borderSkipped: false, maxBarThickness: 50,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8,
          callbacks: { label: function(c){ return '฿' + c.parsed.y.toLocaleString(); } } }
      },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.1)' },
          ticks: { callback: function(v){ return v >= 1000 ? (v/1000)+'k' : v; }, font: { size: 11 }, color: '#94a3b8' } },
        x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#94a3b8' } }
      }
    }
  });
});
</script>
@endpush
