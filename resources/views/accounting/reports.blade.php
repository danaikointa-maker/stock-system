@extends('layouts.app')
@section('title', '📈 รายงานทางบัญชี')
@section('crumb', 'บัญชี · รายงาน')

@section('content')
<div class="card"><div class="body">
  <form method="GET" class="filters">
    <div class="field"><label>ตั้งแต่</label><input type="date" name="from" value="{{ $from }}"></div>
    <div class="field"><label>ถึง</label><input type="date" name="to" value="{{ $to }}"></div>
    <button class="btn btn-p">📊 แสดงรายงาน</button>
  </form>
</div></div>

{{-- สรุป --}}
<div class="grid g4" style="margin-bottom:20px">
  <div class="kpi ok">
    <div class="lbl">📄 ยอดขาย/บริการ</div>
    <div class="val">{{ number_format($revenue, 0) }}</div>
    <div class="sub">บาท</div>
  </div>
  <div class="kpi ok">
    <div class="lbl">💰 ยอดรับ</div>
    <div class="val">{{ number_format($received, 0) }}</div>
    <div class="sub">บาท</div>
  </div>
  <div class="kpi bad">
    <div class="lbl">💸 ยอดจ่าย</div>
    <div class="val">{{ number_format($paid, 0) }}</div>
    <div class="sub">บาท</div>
  </div>
  <div class="kpi">
    <div class="lbl">💰 กำไรขั้นต้น</div>
    <div class="val">{{ number_format($revenue - $paid, 0) }}</div>
    <div class="sub">บาท (ขาย - จ่าย)</div>
  </div>
</div>

<div class="grid g4" style="margin-bottom:20px">
  <div class="kpi">
    <div class="lbl">🧾 VAT เก็บได้</div>
    <div class="val">{{ number_format($vatCollected, 2) }}</div>
    <div class="sub">บาท (นำส่งสรรพากร)</div>
  </div>
  <div class="kpi">
    <div class="lbl">📋 หัก ณ ที่จ่าย</div>
    <div class="val">{{ number_format($whtPaid, 2) }}</div>
    <div class="sub">บาท (ยื่น ภ.ง.ด.)</div>
  </div>
  <div class="kpi {{ count($receivables) > 0 ? 'warn' : '' }}">
    <div class="lbl">⏳ ลูกหนี้คงค้าง</div>
    <div class="val">{{ number_format($receivables->sum('total_balance'), 0) }}</div>
    <div class="sub">{{ count($receivables) }} ราย</div>
  </div>
  <div class="kpi">
    <div class="lbl">📊 สภาพคล่อง</div>
    <div class="val">{{ number_format($received - $paid, 0) }}</div>
    <div class="sub">บาท (รับ - จ่าย)</div>
  </div>
</div>

<div class="grid g2">
  {{-- ลูกหนี้ --}}
  <div class="card">
    <div class="section-bar alert">⏳ ลูกหนี้คงค้าง (A/R Aging)</div>
    @if($receivables->isEmpty())
      <div class="empty">✅ ไม่มีลูกหนี้คงค้าง</div>
    @else
      <table>
        <thead><tr><th>ลูกค้า</th><th class="num">ยอดค้าง</th></tr></thead>
        <tbody>
        @foreach($receivables as $r)
          <tr>
            <td>{{ $r->customer_name }}</td>
            <td class="num"><b style="color:var(--bad-dark)">{{ number_format($r->total_balance, 2) }}</b></td>
          </tr>
        @endforeach
        </tbody>
        <tfoot><tr style="background:#fef3c7"><td><b>รวม</b></td><td class="num"><b>{{ number_format($receivables->sum('total_balance'), 2) }}</b></td></tr></tfoot>
      </table>
    @endif
  </div>

  {{-- รายเดือน --}}
  <div class="card">
    <div class="section-bar report">📊 สรุปยอดขายรายเดือน ({{ now()->year }})</div>
    <table>
      <thead><tr><th>เดือน</th><th class="num">บิล</th><th class="num">ยอดขาย</th><th class="num">VAT</th></tr></thead>
      <tbody>
      @php $months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']; @endphp
      @foreach($monthly as $m)
        <tr>
          <td>{{ $months[$m->m] }}</td>
          <td class="num">{{ number_format($m->count) }}</td>
          <td class="num"><b>{{ number_format($m->revenue, 2) }}</b></td>
          <td class="num">{{ number_format($m->vat, 2) }}</td>
        </tr>
      @endforeach
      </tbody>
      <tfoot><tr style="background:#f0fdf4"><td><b>รวม</b></td><td class="num"><b>{{ number_format($monthly->sum('count')) }}</b></td><td class="num"><b>{{ number_format($monthly->sum('revenue'), 2) }}</b></td><td class="num"><b>{{ number_format($monthly->sum('vat'), 2) }}</b></td></tr></tfoot>
    </table>
  </div>
</div>

@endsection
