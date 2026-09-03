@extends('layouts.app')
@section('title', 'นับสต๊อก')
@section('crumb', 'นับสต๊อกและปรับยอด')

@section('content')

<div class="card">
  <h3>เลือกหน่วยงานที่จะนับ</h3>
  <div class="body">
    <form method="GET" class="filters">
      <div class="field">
        <label for="node">หน่วยงาน</label>
        <select id="node" name="node" onchange="this.form.submit()">
          @foreach($nodes as $n)
            <option value="{{ $n->id }}" @selected($n->id === $node->id)>
              {{ str_repeat('— ', $n->depth) }}{{ $n->name }} ({{ $n->code }})
            </option>
          @endforeach
        </select>
      </div>
      <noscript><button type="submit" class="btn btn-p">เลือก</button></noscript>
    </form>
    <div style="font-size:12px;color:var(--muted);margin-top:6px">
      กรอกเฉพาะรายการที่นับจริง — <b>เว้นว่างไว้จะถือว่าไม่ได้นับ</b> และระบบจะไม่แตะยอดนั้น
      (ถ้านับแล้วไม่เจอของเลย ให้กรอกเลข 0)
    </div>
  </div>
</div>

<form method="POST" action="{{ route('stock.count.store') }}"
      onsubmit="return confirm('ยืนยันปรับยอดสต๊อกตามที่นับ? การปรับจะถูกบันทึกลงการ์ดสินค้าและย้อนกลับไม่ได้')">
  @csrf
  <input type="hidden" name="org_node_id" value="{{ $node->id }}">

  <div class="card">
    <h3>ใบนับสต๊อก · {{ $node->name }} <code>{{ $node->code }}</code></h3>
    <table>
      <thead>
        <tr>
          <th>SKU</th><th>สินค้า</th><th>ล็อต</th>
          <th class="num">ยอดในระบบ</th><th class="num">นับได้จริง</th><th class="num">ผลต่าง</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $i => $r)
          <tr>
            <td><code>{{ $r->product?->sku }}</code></td>
            <td>{{ $r->product?->name ?? '—' }}</td>
            <td>{{ $r->lot?->lot_no ?? '—' }}</td>
            <td class="num" data-sys="{{ $r->qty_on_hand }}"><b>{{ number_format($r->qty_on_hand) }}</b></td>
            <td class="num">
              <input type="hidden" name="counted[{{ $i }}][balance_id]" value="{{ $r->id }}">
              <input type="number" min="0" class="cnt" name="counted[{{ $i }}][qty]"
                     placeholder="ไม่ได้นับ" style="width:110px;text-align:right"
                     data-sys="{{ $r->qty_on_hand }}">
            </td>
            <td class="num diff" style="font-weight:700">—</td>
          </tr>
        @empty
          <tr><td colspan="6" class="empty">หน่วยงานนี้ยังไม่มีสต๊อก</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($rows->isNotEmpty())
    <div class="card">
      <div class="body">
        <div class="field">
          <label for="note">หมายเหตุการนับ</label>
          <input type="text" id="note" name="note"
                 placeholder="เช่น นับประจำเดือน ก.ย. 2569 โดยหัวหน้าคลัง">
        </div>
        <button type="submit" class="btn btn-p">บันทึกและปรับยอด</button>
      </div>
    </div>
  @endif
</form>

<div class="card">
  <h3>ประวัติการปรับยอดล่าสุด</h3>
  <table>
    <thead><tr><th>เวลา</th><th>สินค้า</th><th>ประเภท</th><th class="num">จำนวน</th><th>ผู้ทำ</th><th>หมายเหตุ</th></tr></thead>
    <tbody>
      @forelse($recent as $m)
        <tr>
          <td>{{ $m->created_at?->format('d/m/y H:i') }}</td>
          <td>{{ $m->product?->name ?? '—' }}</td>
          <td>
            <span class="badge {{ $m->type === 'adjust_in' ? 'b-green' : 'b-red' }}">
              {{ $m->type === 'adjust_in' ? 'ปรับเพิ่ม' : 'ปรับลด' }}
            </span>
          </td>
          <td class="num">{{ number_format($m->qty) }}</td>
          <td>{{ $m->createdBy?->name ?? '—' }}</td>
          <td style="font-size:12px;color:var(--muted)">{{ $m->note }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="empty">ยังไม่เคยปรับยอดที่หน่วยงานนี้</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<script>
(function(){
  document.querySelectorAll('.cnt').forEach(function(inp){
    inp.addEventListener('input', function(){
      var cell = inp.closest('tr').querySelector('.diff');
      var sys  = parseInt(inp.dataset.sys, 10);
      if (inp.value === '') { cell.textContent = '—'; cell.style.color = ''; return; }
      var d = parseInt(inp.value, 10) - sys;
      cell.textContent = d === 0 ? 'ตรง' : (d > 0 ? '+' + d : d);
      cell.style.color = d === 0 ? 'var(--ok)' : (d > 0 ? 'var(--brand)' : 'var(--bad)');
    });
  });
})();
</script>

@endsection
