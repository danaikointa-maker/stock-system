@extends('layouts.app')
@section('title', 'ภาพรวม')
@section('crumb', $node->level_id->label() . ' · ' . $node->name)

@section('content')

@php $maxTrend = max($salesTrend->max('total'), 1); @endphp

{{-- ทางลัด --}}
<div style="display:flex;gap:9px;margin-bottom:16px;flex-wrap:wrap">
  @can('create', App\Models\Sale::class)
    <a href="{{ route('pos.index') }}" class="btn btn-p">+ เปิดบิลขาย</a>
  @endcan
  @can('create', App\Models\Transfer::class)
    <a href="{{ route('transfers.create') }}" class="btn">+ สร้างใบโอน</a>
  @endcan
  @if($kpi['pending_approve'])
    <a href="{{ route('transfers.index', ['tab' => 'approve']) }}" class="btn">
      รออนุมัติ <span class="badge b-amber">{{ $kpi['pending_approve'] }}</span>
    </a>
  @endif
  @if($kpi['pending_receive'])
    <a href="{{ route('transfers.index', ['tab' => 'receive']) }}" class="btn">
      รอรับของ <span class="badge b-amber">{{ $kpi['pending_receive'] }}</span>
    </a>
  @endif
</div>

{{-- KPI --}}
<div class="grid g4" style="margin-bottom:18px">
  <div class="kpi">
    <div class="lbl">สต๊อกคงเหลือรวม</div>
    <div class="val">{{ number_format($kpi['stock_qty']) }}</div>
    <div class="sub">มูลค่าทุน {{ number_format($kpi['stock_value'], 0) }} บาท</div>
  </div>
  <div class="kpi ok">
    <div class="lbl">ยอดขายวันนี้</div>
    <div class="val">{{ number_format($kpi['sales_today'], 0) }}</div>
    <div class="sub">{{ $kpi['bills_today'] }} บิล · เดือนนี้ {{ number_format($kpi['sales_month'], 0) }} บาท</div>
  </div>
  <div class="kpi {{ $kpi['low_stock_count'] ? 'warn' : '' }}">
    <div class="lbl">สินค้าใกล้หมด</div>
    <div class="val">{{ $kpi['low_stock_count'] }}</div>
    <div class="sub">รายการที่ต่ำกว่าจุดสั่งซื้อ</div>
  </div>
  <div class="kpi">
    <div class="lbl">สแกน QR เดือนนี้</div>
    <div class="val">{{ number_format($kpi['scans_month']) }}</div>
    <div class="sub">แจกไป {{ number_format($kpi['points_month']) }} คะแนน</div>
  </div>
</div>

<div class="grid g4" style="margin-bottom:18px">
  <div class="kpi {{ $kpi['pending_approve'] ? 'warn' : '' }}">
    <div class="lbl">รออนุมัติโอน</div>
    <div class="val">{{ $kpi['pending_approve'] }}</div>
  </div>
  <div class="kpi {{ $kpi['pending_receive'] ? 'warn' : '' }}">
    <div class="lbl">รอรับของเข้า</div>
    <div class="val">{{ $kpi['pending_receive'] }}</div>
  </div>
  <div class="kpi">
    <div class="lbl">หน่วยงานใต้สังกัด</div>
    <div class="val">{{ $kpi['child_node_count'] }}</div>
  </div>
  <div class="kpi">
    <div class="lbl">สมาชิกในสายงาน</div>
    <div class="val">{{ $kpi['member_count'] }}</div>
  </div>
</div>

<div class="grid g2">

  {{-- กราฟยอดขาย --}}
  <div class="card">
    <h3>ยอดขาย 14 วันล่าสุด</h3>
    <div class="body">
      <div class="spark">
        @foreach($salesTrend as $d)
          <i style="height:{{ max(2, round($d['total'] / $maxTrend * 100)) }}%"
             title="{{ $d['date'] }} — {{ number_format($d['total'], 0) }} บาท"></i>
        @endforeach
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-top:7px">
        <span>{{ $salesTrend->first()['date'] }}</span>
        <span>สูงสุด {{ number_format($maxTrend, 0) }} บาท</span>
        <span>{{ $salesTrend->last()['date'] }}</span>
      </div>
    </div>
  </div>

  {{-- สินค้าใกล้หมด --}}
  <div class="card">
    <h3>
      สินค้าใกล้หมด
      <a href="{{ route('reports.stock') }}" class="btn btn-sm">ดูทั้งหมด</a>
    </h3>
    @if($lowStock->isEmpty())
      <div class="empty">สต๊อกทุกรายการอยู่ในระดับปกติ</div>
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

  {{-- รออนุมัติ --}}
  <div class="card">
    <h3>ใบโอนรออนุมัติ</h3>
    @if($pendingOut->isEmpty())
      <div class="empty">ไม่มีรายการรออนุมัติ</div>
    @else
      <table>
        <thead><tr><th>เลขที่</th><th>ปลายทาง</th><th class="num">จำนวน</th><th></th></tr></thead>
        <tbody>
        @foreach($pendingOut as $t)
          <tr>
            <td><a href="{{ route('transfers.show', $t) }}"><code>{{ $t->doc_no }}</code></a></td>
            <td>{{ $t->toNode->name }}</td>
            <td class="num">{{ number_format($t->total_qty) }}</td>
            <td class="num"><a href="{{ route('transfers.show', $t) }}" class="btn btn-sm">จัดการ</a></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>

  {{-- รอรับของ --}}
  <div class="card">
    <h3>สินค้าระหว่างทาง (รอรับ)</h3>
    @if($pendingIn->isEmpty())
      <div class="empty">ไม่มีสินค้าระหว่างทาง</div>
    @else
      <table>
        <thead><tr><th>เลขที่</th><th>ต้นทาง</th><th class="num">จำนวน</th><th></th></tr></thead>
        <tbody>
        @foreach($pendingIn as $t)
          <tr>
            <td><a href="{{ route('transfers.show', $t) }}"><code>{{ $t->doc_no }}</code></a></td>
            <td>{{ $t->fromNode->name }}</td>
            <td class="num">{{ number_format($t->total_qty) }}</td>
            <td class="num"><a href="{{ route('transfers.show', $t) }}" class="btn btn-sm">รับของ</a></td>
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
    <h3>
      ผลงานหน่วยงานใต้สังกัดโดยตรง (เดือนนี้)
      <a href="{{ route('reports.summary') }}" class="btn btn-sm">รายงานเต็ม</a>
    </h3>
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
