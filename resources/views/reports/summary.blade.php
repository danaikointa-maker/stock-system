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
      <button class="btn btn-p">แสดงรายงาน</button>
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
