@extends('layouts.app')
@section('title', 'สรุปผลประกอบการ')
@section('crumb', "ข้อมูลระหว่าง {$from} ถึง {$to}")

@section('content')

<div class="card">
  <div class="body">
    <form method="GET" class="filters">
      <div class="field">
        <label>ตั้งแต่วันที่</label>
        <input type="date" name="from" value="{{ $from }}">
      </div>
      <div class="field">
        <label>ถึงวันที่</label>
        <input type="date" name="to" value="{{ $to }}">
      </div>
      <button class="btn btn-p">📊 แสดงรายงาน</button>
      <a href="{{ route('reports.export', ['type' => 'sales', 'from' => $from, 'to' => $to]) }}" class="btn">
        ส่งออกยอดขาย CSV
      </a>
      <a href="{{ route('reports.export', ['type' => 'products', 'from' => $from, 'to' => $to]) }}" class="btn">
        ส่งออกสินค้าขายดี CSV
      </a>
    </form>
  </div>
</div>

<div class="grid g4" style="margin-bottom:18px">
  <div class="kpi ok">
    <div class="lbl">ยอดขายเดือนนี้</div>
    <div class="val">{{ number_format($kpi['sales_month'], 0) }}</div>
    <div class="sub">บาท</div>
  </div>
  <div class="kpi">
    <div class="lbl">มูลค่าสต๊อก (ทุน)</div>
    <div class="val">{{ number_format($kpi['stock_value'], 0) }}</div>
    <div class="sub">{{ number_format($kpi['stock_qty']) }} ชิ้น</div>
  </div>
  <div class="kpi">
    <div class="lbl">คะแนนที่แจกเดือนนี้</div>
    <div class="val">{{ number_format($kpi['points_month']) }}</div>
    <div class="sub">จาก {{ number_format($kpi['scans_month']) }} การสแกน</div>
  </div>
  <div class="kpi {{ $kpi['low_stock_count'] ? 'warn' : '' }}">
    <div class="lbl">รายการใกล้หมด</div>
    <div class="val">{{ $kpi['low_stock_count'] }}</div>
  </div>
</div>

{{-- กราฟยอดขายแยกตามหน่วยงาน — Bar Chart --}}
@if($salesByNode->isNotEmpty())
<div class="card">
  <div class="section-bar report">📊 ยอดขายแยกตามหน่วยงาน</div>
  <div class="body">
    <div class="chart-wrap"><canvas id="salesByNodeChart"></canvas></div>
  </div>
</div>
@endif

<div class="grid g2">
  {{-- Doughnut — สินค้าขายดี --}}
  @if($topProducts->isNotEmpty())
  <div class="card">
    <div class="section-bar sales">🍩 สัดส่วนสินค้าขายดี</div>
    <div class="body">
      <div class="chart-wrap" style="max-height:280px;display:flex;justify-content:center">
        <canvas id="topProductsChart"></canvas>
      </div>
    </div>
  </div>
  @endif
</div>

<div class="card">
  <h3>ยอดขายแยกตามหน่วยงาน</h3>
  @if($salesByNode->isEmpty())
    <div class="empty">ไม่มียอดขายในช่วงเวลาที่เลือก</div>
  @else
    @php $maxRev = max($salesByNode->max('revenue'), 1); $sumRev = $salesByNode->sum('revenue'); @endphp
    <table>
      <thead>
        <tr>
          <th>รหัส</th><th>หน่วยงาน</th><th>ระดับ</th>
          <th class="num">บิล</th><th class="num">ยอดขาย</th>
          <th style="width:150px">สัดส่วน</th><th class="num">%</th>
        </tr>
      </thead>
      <tbody>
      @foreach($salesByNode as $r)
        <tr>
          <td><code>{{ $r->code }}</code></td>
          <td>{{ $r->name }}</td>
          <td><span class="badge b-gray">{{ $r->level_name }}</span></td>
          <td class="num">{{ number_format($r->bills) }}</td>
          <td class="num"><b>{{ number_format($r->revenue, 2) }}</b></td>
          <td><div class="bar"><i style="width:{{ round($r->revenue / $maxRev * 100) }}%"></i></div></td>
          <td class="num">{{ number_format($r->revenue / max($sumRev, 1) * 100, 1) }}%</td>
        </tr>
      @endforeach
      </tbody>
      <tfoot>
        <tr style="background:#f7f9fc;font-weight:700">
          <td colspan="3">รวมทั้งสิ้น</td>
          <td class="num">{{ number_format($salesByNode->sum('bills')) }}</td>
          <td class="num">{{ number_format($sumRev, 2) }}</td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  @endif
</div>

<div class="grid g2">
  <div class="card">
    <h3>สินค้าขายดี</h3>
    @if($topProducts->isEmpty())
      <div class="empty">ไม่มีข้อมูล</div>
    @else
      @php $maxQty = max($topProducts->max('qty'), 1); @endphp
      <table>
        <thead><tr><th>SKU</th><th>สินค้า</th><th class="num">จำนวน</th><th style="width:100px"></th><th class="num">ยอดเงิน</th></tr></thead>
        <tbody>
        @foreach($topProducts as $p)
          <tr>
            <td><code>{{ $p->sku }}</code></td>
            <td>{{ $p->name }}</td>
            <td class="num"><b>{{ number_format($p->qty) }}</b></td>
            <td><div class="bar"><i style="width:{{ round($p->qty / $maxQty * 100) }}%"></i></div></td>
            <td class="num">{{ number_format($p->revenue, 2) }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="card">
    <h3>ผลงานหน่วยงานใต้สังกัดโดยตรง</h3>
    @if($children->isEmpty())
      <div class="empty">ไม่มีหน่วยงานใต้สังกัด</div>
    @else
      @php $maxC = max($children->max('sales'), 1); @endphp
      <table>
        <thead><tr><th>หน่วยงาน</th><th class="num">ยอดขาย</th><th style="width:90px"></th><th class="num">บิล</th><th class="num">สต๊อก</th></tr></thead>
        <tbody>
        @foreach($children as $row)
          <tr>
            <td>
              {{ $row['node']->name }}
              <div style="font-size:11px"><code>{{ $row['node']->code }}</code></div>
            </td>
            <td class="num"><b>{{ number_format($row['sales'], 0) }}</b></td>
            <td><div class="bar"><i style="width:{{ round($row['sales'] / $maxC * 100) }}%"></i></div></td>
            <td class="num">{{ $row['bills'] }}</td>
            <td class="num">{{ number_format($row['stock_qty']) }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof Chart === 'undefined') return;
  var colors = ['#10b981','#3b82f6','#f97316','#8b5cf6','#f43f5e','#06b6d4','#f59e0b','#6366f1','#ec4899','#14b8a6'];

  // Bar chart — sales by node
  var nodeCtx = document.getElementById('salesByNodeChart');
  if (nodeCtx) {
    var nodeData = @json($salesByNode);
    new Chart(nodeCtx, {
      type: 'bar',
      data: {
        labels: nodeData.map(function(r){ return r.name; }),
        datasets: [{
          label: 'ยอดขาย (บาท)',
          data: nodeData.map(function(r){ return r.revenue; }),
          backgroundColor: colors.slice(0, nodeData.length),
          borderRadius: 6,
          borderSkipped: false,
          maxBarThickness: 50,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        indexAxis: nodeData.length > 5 ? 'y' : 'x',
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1e293b', padding: 12, cornerRadius: 8,
            callbacks: { label: function(c){ return '฿' + c.parsed.y?.toLocaleString() || c.parsed.x?.toLocaleString(); } }
          }
        },
        scales: {
          x: { grid: { color: 'rgba(148,163,184,.1)' }, ticks: { font: { size: 11 }, color: '#94a3b8',
            callback: function(v){ return v >= 1000 ? (v/1000)+'k' : v; } } },
          y: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#94a3b8' } }
        }
      }
    });
  }

  // Doughnut — top products
  var prodCtx = document.getElementById('topProductsChart');
  if (prodCtx) {
    var prodData = @json($topProducts);
    new Chart(prodCtx, {
      type: 'doughnut',
      data: {
        labels: prodData.map(function(p){ return p.name; }),
        datasets: [{
          data: prodData.map(function(p){ return p.qty; }),
          backgroundColor: colors.slice(0, prodData.length),
          borderWidth: 2, borderColor: '#fff', hoverOffset: 8,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '55%',
        plugins: {
          legend: { position: 'right', labels: { padding: 12, font: { size: 11 }, usePointStyle: true } },
          tooltip: {
            backgroundColor: '#1e293b', padding: 12, cornerRadius: 8,
            callbacks: { label: function(c){
              var total = c.dataset.data.reduce(function(a,b){return a+b;},0);
              return c.label + ': ' + c.parsed.toLocaleString() + ' ชิ้น (' + ((c.parsed/total)*100).toFixed(1) + '%)';
            }}
          }
        }
      }
    });
  }
});
</script>
@endpush
