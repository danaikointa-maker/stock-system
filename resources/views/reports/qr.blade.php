@extends('layouts.app')
@section('title', 'รายงาน QR และคะแนนสะสม')
@section('crumb', "ข้อมูลระหว่าง {$from} ถึง {$to}")

@section('content')

@php
  $resultLabels = [
    'success' => 'สำเร็จ', 'already_used' => 'ถูกใช้ไปแล้ว', 'invalid' => 'ไม่ถูกต้อง',
    'expired' => 'หมดอายุ', 'rate_limited' => 'สแกนถี่เกินไป', 'blocked' => 'ถูกระงับ',
  ];
  $statusLabels = [
    'created' => 'สร้างแล้ว (ยังไม่กระจาย)', 'in_stock' => 'อยู่ในสต๊อก',
    'sold' => 'ขายแล้ว (รอสแกน)', 'redeemed' => 'รับคะแนนแล้ว', 'void' => 'ยกเลิก',
  ];
  $successRate = $total_scans > 0 ? $success_scans / $total_scans * 100 : 0;
@endphp

<div class="card">
  <div class="body">
    <form method="GET" class="filters">
      <div class="field"><label>ตั้งแต่</label><input type="date" name="from" value="{{ $from }}"></div>
      <div class="field"><label>ถึง</label><input type="date" name="to" value="{{ $to }}"></div>
      <button class="btn btn-p">📊 แสดงรายงาน</button>
    </form>
  </div>
</div>

<div class="grid g4" style="margin-bottom:18px">
  <div class="kpi">
    <div class="lbl">การสแกนทั้งหมด</div>
    <div class="val">{{ number_format($total_scans) }}</div>
  </div>
  <div class="kpi ok">
    <div class="lbl">สแกนสำเร็จ</div>
    <div class="val">{{ number_format($success_scans) }}</div>
    <div class="sub">{{ number_format($successRate, 1) }}% ของทั้งหมด</div>
  </div>
  <div class="kpi">
    <div class="lbl">คะแนนที่แจกไป</div>
    <div class="val">{{ number_format($total_points) }}</div>
  </div>
  <div class="kpi {{ ($by_result['already_used'] ?? 0) + ($by_result['invalid'] ?? 0) > 0 ? 'warn' : '' }}">
    <div class="lbl">สแกนผิดปกติ</div>
    <div class="val">{{ number_format(($by_result['already_used'] ?? 0) + ($by_result['invalid'] ?? 0)) }}</div>
    <div class="sub">ซ้ำ / ไม่ถูกต้อง</div>
  </div>
</div>

{{-- กราฟ --}}
<div class="grid g2" style="margin-bottom:18px">
  <div class="card">
    <div class="section-bar qr">🍩 ผลลัพธ์การสแกน</div>
    <div class="body">
      <div class="chart-wrap" style="max-height:260px;display:flex;justify-content:center">
        <canvas id="resultDoughnut"></canvas>
      </div>
    </div>
  </div>
  @if($daily->isNotEmpty())
  <div class="card">
    <div class="section-bar report">📊 สแกนรายวัน</div>
    <div class="body">
      <div class="chart-wrap"><canvas id="dailyBar"></canvas></div>
    </div>
  </div>
  @endif
</div>

<div class="grid g2">
  <div class="card">
    <h3>ผลการสแกนแยกตามประเภท</h3>
    @if($by_result->isEmpty())
      <div class="empty">ไม่มีการสแกนในช่วงเวลานี้</div>
    @else
      @php $maxR = max($by_result->max(), 1); @endphp
      <table>
        <thead><tr><th>ผลลัพธ์</th><th class="num">จำนวน</th><th style="width:130px"></th><th class="num">%</th></tr></thead>
        <tbody>
        @foreach($by_result as $result => $count)
          <tr>
            <td>
              <span class="badge {{ $result === 'success' ? 'b-green' : ($result === 'rate_limited' ? 'b-amber' : 'b-red') }}">
                {{ $resultLabels[$result] ?? $result }}
              </span>
            </td>
            <td class="num"><b>{{ number_format($count) }}</b></td>
            <td><div class="bar"><i style="width:{{ round($count / $maxR * 100) }}%"></i></div></td>
            <td class="num">{{ number_format($count / max($total_scans, 1) * 100, 1) }}%</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="card">
    <h3>สถานะ QR ในสายงาน</h3>
    @if($qr_status->isEmpty())
      <div class="empty">ยังไม่มี QR ในสายงานนี้</div>
    @else
      @php $totalQr = $qr_status->sum(); @endphp
      <table>
        <thead><tr><th>สถานะ</th><th class="num">จำนวน</th><th style="width:130px"></th><th class="num">%</th></tr></thead>
        <tbody>
        @foreach($qr_status as $st => $count)
          <tr>
            <td><span class="badge b-gray">{{ $statusLabels[$st] ?? $st }}</span></td>
            <td class="num"><b>{{ number_format($count) }}</b></td>
            <td><div class="bar"><i style="width:{{ round($count / max($qr_status->max(), 1) * 100) }}%"></i></div></td>
            <td class="num">{{ number_format($count / max($totalQr, 1) * 100, 1) }}%</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

<div class="card">
  <h3>การสแกนรายวัน</h3>
  @if($daily->isEmpty())
    <div class="empty">ไม่มีข้อมูล</div>
  @else
    @php $maxD = max($daily->max('scans'), 1); @endphp
    <div class="body">
      <div class="spark" style="justify-content:flex-start">
        @foreach($daily as $d)
          <i style="height:{{ max(2, round($d->scans / $maxD * 100)) }}%"
             title="{{ $d->d }} — {{ $d->scans }} ครั้ง, {{ number_format($d->pts) }} คะแนน"></i>
        @endforeach
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-top:7px">
        <span>{{ $daily->first()->d }}</span>
        <span>สูงสุด {{ number_format($maxD) }} ครั้ง/วัน</span>
        <span>{{ $daily->last()->d }}</span>
      </div>
    </div>
  @endif
</div>

@if($suspicious->isNotEmpty())
<div class="card" style="border-color:#fca5a5">
  <h3 style="color:var(--bad)">รายการที่ควรตรวจสอบ</h3>
  <div class="body" style="padding-bottom:0;font-size:12.5px;color:var(--muted)">
    การสแกนซ้ำหรือ QR ไม่ถูกต้องจำนวนมาก อาจเป็นสัญญาณของสินค้าปลอมหรือการพยายามโกงคะแนน
  </div>
  <table>
    <thead><tr><th>เวลา</th><th>สินค้า</th><th>ผลลัพธ์</th><th>IP</th><th>Token</th></tr></thead>
    <tbody>
    @foreach($suspicious as $s)
      <tr>
        <td style="font-size:12px;white-space:nowrap">{{ $s->scanned_at->format('d/m/y H:i') }}</td>
        <td>{{ $s->qrcode?->product?->name ?? '— (ไม่พบ QR)' }}</td>
        <td><span class="badge b-red">{{ $resultLabels[$s->result] ?? $s->result }}</span></td>
        <td style="font-size:12px">{{ $s->ip_address ?? '—' }}</td>
        <td style="font-size:11px;color:var(--muted)">{{ Str::limit($s->raw_token, 16) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof Chart === 'undefined') return;

  // Doughnut — by result
  var resultCtx = document.getElementById('resultDoughnut');
  if (resultCtx) {
    var byResult = @json($by_result);
    var labels = {success:'สำเร็จ', already_used:'ใช้แล้ว', invalid:'ไม่ถูกต้อง', expired:'หมดอายุ', not_found:'ไม่พบ'};
    var colors = {success:'#10b981', already_used:'#f59e0b', invalid:'#ef4444', expired:'#8b5cf6', not_found:'#94a3b8'};
    var keys = Object.keys(byResult);
    new Chart(resultCtx, {
      type: 'doughnut',
      data: {
        labels: keys.map(function(k){ return labels[k] || k; }),
        datasets: [{
          data: keys.map(function(k){ return byResult[k]; }),
          backgroundColor: keys.map(function(k){ return colors[k] || '#94a3b8'; }),
          borderWidth: 2, borderColor: '#fff', hoverOffset: 8,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '55%',
        plugins: {
          legend: { position: 'bottom', labels: { padding: 14, font: { size: 11 }, usePointStyle: true } },
          tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8 }
        }
      }
    });
  }

  // Bar — daily scans
  var dailyCtx = document.getElementById('dailyBar');
  if (dailyCtx) {
    var daily = @json($daily);
    new Chart(dailyCtx, {
      type: 'bar',
      data: {
        labels: daily.map(function(d){ return d.d?.slice(5); }),
        datasets: [
          {
            label: 'สแกนสำเร็จ',
            data: daily.map(function(d){ return d.scans; }),
            backgroundColor: '#06b6d4', borderRadius: 4, maxBarThickness: 30,
          },
          {
            label: 'แต้มที่แจก',
            data: daily.map(function(d){ return d.pts; }),
            backgroundColor: '#8b5cf6', borderRadius: 4, maxBarThickness: 30,
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { font: { size: 11 }, usePointStyle: true } },
          tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8 }
        },
        scales: {
          y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.1)' }, ticks: { font: { size: 10 }, color: '#94a3b8' } },
          x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94a3b8' } }
        }
      }
    });
  }
});
</script>
@endpush
