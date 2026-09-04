@extends('layouts.app')
@section('title', 'ภาพรวม')
@section('crumb', $node->level_id->label() . ' · ' . $node->name)

@section('content')

{{-- ทางลัด — ปุ่มแยกสีตามหน้าที่ --}}
<div style="display:flex;gap:9px;margin-bottom:20px;flex-wrap:wrap">
  @can('create', App\Models\Sale::class)
    <a href="{{ route('pos.index') }}" class="btn btn-create">💰 เปิดบิลขาย</a>
  @endcan
  @can('create', App\Models\Transfer::class)
    <a href="{{ route('transfers.create') }}" class="btn btn-ship">📋 สร้างใบโอน</a>
  @endcan
  @if($kpi['pending_approve'])
    <a href="{{ route('transfers.index', ['tab' => 'approve']) }}" class="btn btn-approve">
      ⏳ รออนุมัติ <span class="badge" style="background:rgba(255,255,255,.3);color:#fff">{{ $kpi['pending_approve'] }}</span>
    </a>
  @endif
  @if($kpi['pending_receive'])
    <a href="{{ route('transfers.index', ['tab' => 'receive']) }}" class="btn btn-receive">
      📥 รอรับของ <span class="badge" style="background:rgba(255,255,255,.3);color:#fff">{{ $kpi['pending_receive'] }}</span>
    </a>
  @endif
</div>

{{-- KPI Cards — 4 cards with color accents --}}
<div class="grid g4" style="margin-bottom:20px">
  <div class="kpi">
    <div class="lbl">📦 สต๊อกคงเหลือรวม</div>
    <div class="val">{{ number_format($kpi['stock_qty']) }}</div>
    <div class="sub">มูลค่าทุน {{ number_format($kpi['stock_value'], 0) }} บาท</div>
  </div>
  <div class="kpi ok">
    <div class="lbl">💰 ยอดขายวันนี้</div>
    <div class="val">{{ number_format($kpi['sales_today'], 0) }}</div>
    <div class="sub">{{ $kpi['bills_today'] }} บิล · เดือนนี้ {{ number_format($kpi['sales_month'], 0) }} บาท</div>
  </div>
  <div class="kpi {{ $kpi['low_stock_count'] ? 'warn' : '' }}">
    <div class="lbl">⚠️ สินค้าใกล้หมด</div>
    <div class="val">{{ $kpi['low_stock_count'] }}</div>
    <div class="sub">รายการที่ต่ำกว่าจุดสั่งซื้อ</div>
  </div>
  <div class="kpi">
    <div class="lbl">📱 สแกน QR เดือนนี้</div>
    <div class="val">{{ number_format($kpi['scans_month']) }}</div>
    <div class="sub">แจกไป {{ number_format($kpi['points_month']) }} คะแนน</div>
  </div>
</div>

<div class="grid g4" style="margin-bottom:20px">
  <div class="kpi {{ $kpi['pending_approve'] ? 'warn' : '' }}">
    <div class="lbl">⏳ รออนุมัติโอน</div>
    <div class="val">{{ $kpi['pending_approve'] }}</div>
  </div>
  <div class="kpi {{ $kpi['pending_receive'] ? 'warn' : '' }}">
    <div class="lbl">📥 รอรับของเข้า</div>
    <div class="val">{{ $kpi['pending_receive'] }}</div>
  </div>
  <div class="kpi">
    <div class="lbl">🏢 หน่วยงานใต้สังกัด</div>
    <div class="val">{{ $kpi['child_node_count'] }}</div>
  </div>
  <div class="kpi">
    <div class="lbl">👥 สมาชิกในสายงาน</div>
    <div class="val">{{ $kpi['member_count'] }}</div>
  </div>
</div>

<div class="grid g2">

  {{-- 📊 Bar Chart — ยอดขาย 14 วัน --}}
  <div class="card">
    <div class="section-bar sales">📊 ยอดขาย 14 วันล่าสุด</div>
    <div class="body">
      <div class="chart-wrap">
        <canvas id="salesChart"></canvas>
      </div>
    </div>
  </div>

  {{-- 🍩 Doughnut — สัดส่วนสต๊อกตามหน่วยงาน (ถ้ามี child) --}}
  @if($childSummary->isNotEmpty())
  <div class="card">
    <div class="section-bar team">🍩 สัดส่วนยอดขายหน่วยงานลูก</div>
    <div class="body">
      <div class="chart-wrap" style="max-height:280px;display:flex;justify-content:center">
        <canvas id="childPieChart"></canvas>
      </div>
    </div>
  </div>
  @else
  {{-- สินค้าใกล้หมด --}}
  <div class="card">
    <div class="section-bar alert">
      ⚠️ สินค้าใกล้หมด
      <a href="{{ route('reports.stock') }}" class="btn btn-sm" style="margin-left:auto;background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.3);color:#fff">ดูทั้งหมด</a>
    </div>
    @if($lowStock->isEmpty())
      <div class="empty">✅ สต๊อกทุกรายการอยู่ในระดับปกติ</div>
    @else
      <table>
        <thead><tr><th>สินค้า</th><th>หน่วยงาน</th><th class="num">คงเหลือ</th><th class="num">จุดสั่ง</th></tr></thead>
        <tbody>
        @foreach($lowStock as $b)
          <tr>
            <td>{{ $b->product->name }}</td>
            <td><code>{{ $b->node->code }}</code></td>
            <td class="num"><span class="badge b-red">{{ $b->qty_on_hand }}</span></td>
            <td class="num">{{ $b->reorder_point }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
  @endif

  {{-- 📋 ใบโอนรออนุมัติ --}}
  <div class="card">
    <div class="section-bar transfer">
      ⏳ ใบโอนรออนุมัติ
    </div>
    @if($pendingOut->isEmpty())
      <div class="empty">✅ ไม่มีรายการรออนุมัติ</div>
    @else
      <table>
        <thead><tr><th>เลขที่</th><th>ปลายทาง</th><th class="num">จำนวน</th><th></th></tr></thead>
        <tbody>
        @foreach($pendingOut as $t)
          <tr>
            <td><a href="{{ route('transfers.show', $t) }}"><code>{{ $t->doc_no }}</code></a></td>
            <td>{{ $t->toNode->name }}</td>
            <td class="num">{{ number_format($t->total_qty) }}</td>
            <td class="num"><a href="{{ route('transfers.show', $t) }}" class="btn btn-sm btn-approve">อนุมัติ</a></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>

  {{-- 📥 สินค้าระหว่างทาง --}}
  <div class="card">
    <div class="section-bar stock">
      📥 สินค้าระหว่างทาง (รอรับ)
    </div>
    @if($pendingIn->isEmpty())
      <div class="empty">✅ ไม่มีสินค้าระหว่างทาง</div>
    @else
      <table>
        <thead><tr><th>เลขที่</th><th>ต้นทาง</th><th class="num">จำนวน</th><th></th></tr></thead>
        <tbody>
        @foreach($pendingIn as $t)
          <tr>
            <td><a href="{{ route('transfers.show', $t) }}"><code>{{ $t->doc_no }}</code></a></td>
            <td>{{ $t->fromNode->name }}</td>
            <td class="num">{{ number_format($t->total_qty) }}</td>
            <td class="num"><a href="{{ route('transfers.show', $t) }}" class="btn btn-sm btn-receive">รับของ</a></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

{{-- ผลงานหน่วยงานลูก --}}
@if($childSummary->isNotEmpty())
  @php $maxSales = max($childSummary->max('sales'), 1); @endphp
  <div class="card">
    <div class="section-bar team">
      🏢 ผลงานหน่วยงานใต้สังกัด (เดือนนี้)
      <a href="{{ route('reports.summary') }}" class="btn btn-sm" style="margin-left:auto;background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.3);color:#fff">รายงานเต็ม</a>
    </div>
    <table>
      <thead>
        <tr>
          <th>หน่วยงาน</th><th>ระดับ</th>
          <th class="num">ยอดขาย</th><th style="width:130px">สัดส่วน</th>
          <th class="num">บิล</th><th class="num">สต๊อก</th>
          <th class="num">หน่วยงานย่อย</th><th class="num">สมาชิก</th>
        </tr>
      </thead>
      <tbody>
      @foreach($childSummary as $row)
        <tr>
          <td>
            <a href="{{ route('nodes.show', $row['node']) }}">{{ $row['node']->name }}</a>
            <div style="font-size:11px;color:var(--muted)"><code>{{ $row['node']->code }}</code></div>
          </td>
          <td><span class="badge b-gray">{{ $row['node']->level_id->label() }}</span></td>
          <td class="num"><b>{{ number_format($row['sales'], 0) }}</b></td>
          <td>
            <div class="bar"><i style="width:{{ round($row['sales'] / $maxSales * 100) }}%"></i></div>
          </td>
          <td class="num">{{ number_format($row['bills']) }}</td>
          <td class="num">{{ number_format($row['stock_qty']) }}</td>
          <td class="num">{{ $row['sub_nodes'] }}</td>
          <td class="num">{{ $row['members'] }}</td>
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

  // Color palette
  var colors = {
    green: '#10b981', greenLight: 'rgba(16,185,129,.15)',
    blue: '#3b82f6', blueLight: 'rgba(59,130,246,.15)',
    orange: '#f97316', orangeLight: 'rgba(249,115,22,.15)',
    purple: '#8b5cf6', purpleLight: 'rgba(139,92,246,.15)',
    rose: '#f43f5e', cyan: '#06b6d4', amber: '#f59e0b', indigo: '#6366f1',
  };
  var pieColors = [colors.green, colors.blue, colors.orange, colors.purple, colors.rose, colors.cyan, colors.amber, colors.indigo];

  // ═══ Sales Bar Chart ═══
  var salesCtx = document.getElementById('salesChart');
  if (salesCtx) {
    var salesData = @json($salesTrend);
    new Chart(salesCtx, {
      type: 'bar',
      data: {
        labels: salesData.map(function(d){ return d.date.slice(5); }), // MM-DD
        datasets: [{
          label: 'ยอดขาย (บาท)',
          data: salesData.map(function(d){ return d.total; }),
          backgroundColor: salesData.map(function(d){
            return d.total > 0 ? colors.green : 'rgba(148,163,184,.2)';
          }),
          borderRadius: 6,
          borderSkipped: false,
          maxBarThickness: 40,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1e293b',
            titleFont: { size: 13 },
            bodyFont: { size: 12 },
            padding: 12,
            cornerRadius: 8,
            callbacks: {
              label: function(ctx){ return '฿' + ctx.parsed.y.toLocaleString(); }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(148,163,184,.1)' },
            ticks: {
              callback: function(v){ return v >= 1000 ? (v/1000)+'k' : v; },
              font: { size: 11 },
              color: '#94a3b8'
            }
          },
          x: {
            grid: { display: false },
            ticks: { font: { size: 10 }, color: '#94a3b8' }
          }
        }
      }
    });
  }

  // ═══ Child Pie/Doughnut Chart ═══
  var pieCtx = document.getElementById('childPieChart');
  if (pieCtx) {
    var childData = @json($childSummary);
    new Chart(pieCtx, {
      type: 'doughnut',
      data: {
        labels: childData.map(function(c){ return c.node.name; }),
        datasets: [{
          data: childData.map(function(c){ return c.sales; }),
          backgroundColor: pieColors.slice(0, childData.length),
          borderWidth: 2,
          borderColor: '#fff',
          hoverOffset: 8,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '55%',
        plugins: {
          legend: {
            position: 'right',
            labels: { padding: 12, font: { size: 11 }, usePointStyle: true, pointStyleWidth: 10 }
          },
          tooltip: {
            backgroundColor: '#1e293b',
            padding: 12,
            cornerRadius: 8,
            callbacks: {
              label: function(ctx){
                var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                var pct = ((ctx.parsed / total) * 100).toFixed(1);
                return ctx.label + ': ฿' + ctx.parsed.toLocaleString() + ' (' + pct + '%)';
              }
            }
          }
        }
      }
    });
  }
});
</script>
@endpush
