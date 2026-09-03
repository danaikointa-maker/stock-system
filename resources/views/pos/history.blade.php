@extends('layouts.app')
@section('title', 'ประวัติการขาย')
@section('crumb', 'บิลขายทั้งหมดในสายงานของคุณ')

@section('content')

<div class="card">
  <div class="body">
    <form method="GET" class="filters">
      <div class="field"><label>เลขที่บิล</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="SAL-..."></div>
      <div class="field"><label>ตั้งแต่</label>
        <input type="date" name="from" value="{{ request('from') }}"></div>
      <div class="field"><label>ถึง</label>
        <input type="date" name="to" value="{{ request('to') }}"></div>
      <div class="field"><label>สถานะ</label>
        <select name="status">
          <option value="">ทั้งหมด</option>
          <option value="completed" @selected(request('status')==='completed')>สำเร็จ</option>
          <option value="voided" @selected(request('status')==='voided')>ยกเลิกแล้ว</option>
        </select>
      </div>
      <button class="btn btn-p">ค้นหา</button>
      <a href="{{ route('pos.history') }}" class="btn">ล้าง</a>
    </form>
  </div>
</div>

<div class="card">
  <h3>รายการบิล ({{ number_format($sales->total()) }} บิล)</h3>

  @if($sales->isEmpty())
    <div class="empty">ไม่พบบิลขายตามเงื่อนไข</div>
  @else
    <table>
      <thead>
        <tr><th>เลขที่</th><th>วันเวลา</th><th>หน่วยงาน</th><th>ลูกค้า</th>
            <th class="num">ยอดสุทธิ</th><th>ชำระ</th><th>สถานะ</th><th></th></tr>
      </thead>
      <tbody>
      @foreach($sales as $s)
        <tr>
          <td><code>{{ $s->doc_no }}</code></td>
          <td style="font-size:12.5px;white-space:nowrap">{{ $s->sold_at->format('d/m/y H:i') }}</td>
          <td>{{ $s->node->name }}<div style="font-size:11px"><code>{{ $s->node->code }}</code></div></td>
          <td style="font-size:12.5px">{{ $s->customer?->phone ?? '—' }}</td>
          <td class="num"><b>{{ number_format($s->total, 2) }}</b></td>
          <td><span class="badge b-gray">
            {{ ['cash'=>'เงินสด','qr'=>'QR','transfer'=>'โอน','credit'=>'เครดิต'][$s->payment_method] ?? $s->payment_method }}
          </span></td>
          <td>
            @if($s->status === 'completed')<span class="badge b-green">สำเร็จ</span>
            @else<span class="badge b-red">ยกเลิกแล้ว</span>@endif
          </td>
          <td class="num"><a href="{{ route('pos.receipt', $s) }}" class="btn btn-sm">ใบเสร็จ</a></td>
        </tr>
      @endforeach
      </tbody>
      <tfoot>
        <tr style="background:#f7f9fc;font-weight:700">
          <td colspan="4">รวมในหน้านี้</td>
          <td class="num">{{ number_format($sales->getCollection()->where('status','completed')->sum('total'), 2) }}</td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
    </table>
    <div class="pager">{{ $sales->links('partials.pagination') }}</div>
  @endif
</div>

@endsection
