@extends('layouts.app')
@section('title', 'สร้างใบโอนสินค้า')
@section('crumb', 'โอนสินค้าให้หน่วยงานใต้สังกัดโดยตรง')

@section('content')

@if($errors->has('transfer'))
  <div class="alert a-bad">{{ $errors->first('transfer') }}</div>
@endif

<div class="card">
  <div class="body">
    <form method="GET" class="filters">
      <div class="field" style="flex:1">
        <label>โอนออกจากหน่วยงาน</label>
        <select name="from" onchange="this.form.submit()">
          @foreach($fromNodes as $n)
            <option value="{{ $n->id }}" @selected($n->id === $fromNode->id)>
              {{ str_repeat('— ', $n->depth) }}{{ $n->name }} ({{ $n->code }}) · {{ $n->level_id->label() }}
            </option>
          @endforeach
        </select>
      </div>
    </form>
  </div>
</div>

@if($toNodes->isEmpty())
  <div class="card"><div class="empty">
    หน่วยงานนี้ยังไม่มีหน่วยงานลูก — กรุณาเปิดหน่วยงานใต้สังกัดก่อนจึงจะโอนสินค้าได้
  </div></div>
@elseif($stock->isEmpty())
  <div class="card"><div class="empty">ไม่มีสินค้าคงเหลือพร้อมโอนในหน่วยงานนี้</div></div>
@else

<form method="POST" action="{{ route('transfers.store') }}" id="trfForm">
  @csrf
  <input type="hidden" name="from_node_id" value="{{ $fromNode->id }}">

  <div class="grid" style="grid-template-columns:1.35fr 1fr;gap:16px;align-items:start">

    <div class="card" style="margin:0">
      <h3>
        เลือกสินค้าที่จะโอน
        <input type="search" id="q" placeholder="ค้นหาชื่อ หรือ SKU"
               style="width:200px;padding:5px 10px;font-size:12.5px">
      </h3>
      <table id="prodTable">
        <thead><tr><th>SKU</th><th>สินค้า</th><th class="num">พร้อมโอน</th><th style="width:80px"></th></tr></thead>
        <tbody>
        @foreach($stock as $b)
          <tr data-search="{{ strtolower($b->product->sku . ' ' . $b->product->name) }}">
            <td><code>{{ $b->product->sku }}</code></td>
            <td>{{ $b->product->name }}</td>
            <td class="num"><span class="badge b-green">{{ number_format($b->qty_on_hand - $b->qty_reserved) }}</span></td>
            <td class="num">
              <button type="button" class="btn btn-sm btn-p addBtn"
                      data-id="{{ $b->product->id }}"
                      data-name="{{ $b->product->name }}"
                      data-max="{{ $b->qty_on_hand - $b->qty_reserved }}">เพิ่ม</button>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

    <div class="card" style="margin:0;position:sticky;top:16px">
      <h3>รายการที่จะโอน <span id="cnt" class="badge b-gray">0 รายการ</span></h3>

      <table>
        <thead><tr><th>สินค้า</th><th style="width:88px" class="num">จำนวน</th><th style="width:34px"></th></tr></thead>
        <tbody id="cartBody"></tbody>
      </table>
      <div id="cartEmpty" class="empty" style="padding:24px">ยังไม่ได้เลือกสินค้า</div>

      <div class="body" style="border-top:1px solid var(--line)">
        <div class="field">
          <label for="to_node_id">โอนไปยัง (หน่วยงานลูกโดยตรง) *</label>
          <select id="to_node_id" name="to_node_id" required>
            <option value="">— เลือกปลายทาง —</option>
            @foreach($toNodes as $n)
              <option value="{{ $n->id }}" @selected(old('to_node_id') == $n->id)>
                {{ $n->name }} ({{ $n->code }}) · {{ $n->level_id->label() }}
              </option>
            @endforeach
          </select>
          <div style="font-size:11.5px;color:var(--muted);margin-top:3px">
            ระบบอนุญาตให้โอนลงหน่วยงานลูกโดยตรงเท่านั้น ข้ามระดับไม่ได้
          </div>
        </div>

        <div class="field">
          <label for="note">หมายเหตุ</label>
          <textarea id="note" name="note" rows="2">{{ old('note') }}</textarea>
        </div>

        <div style="background:#f7f9fc;border:1px solid var(--line);border-radius:8px;
                    padding:11px;font-size:12px;color:var(--muted);margin-bottom:12px">
          เมื่อสร้างแล้วใบโอนจะอยู่สถานะ <b>รออนุมัติ</b>
          ระบบจะยังไม่ตัดสต๊อกจนกว่าจะกดส่งของ
        </div>

        <button type="submit" class="btn btn-p" id="submitBtn" disabled
                style="width:100%;padding:11px;font-size:14.5px;font-weight:600">
          สร้างใบโอน
        </button>
      </div>
    </div>
  </div>
</form>
@endif

<script>
(function () {
  var cart = {}, body = document.getElementById('cartBody');
  if (!body) return;

  function render() {
    body.innerHTML = '';
    var keys = Object.keys(cart);
    keys.forEach(function (id) {
      var it = cart[id];
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td style="font-size:12.5px">' + it.name +
        '<div style="font-size:11px;color:var(--muted)">สูงสุด ' + it.max + '</div></td>' +
        '<td class="num"><input type="number" min="1" max="' + it.max + '" value="' + it.qty +
        '" data-id="' + id + '" class="qtyInput" style="width:78px;padding:4px 6px;text-align:right"></td>' +
        '<td class="num"><button type="button" class="btn btn-sm btn-d rmBtn" data-id="' + id + '">×</button></td>' +
        '<input type="hidden" name="items[' + id + '][product_id]" value="' + id + '">' +
        '<input type="hidden" name="items[' + id + '][qty]" value="' + it.qty + '">';
      body.appendChild(tr);
    });
    document.getElementById('cnt').textContent = keys.length + ' รายการ';
    document.getElementById('cartEmpty').style.display = keys.length ? 'none' : 'block';
    document.getElementById('submitBtn').disabled = keys.length === 0;
  }

  document.querySelectorAll('.addBtn').forEach(function (b) {
    b.addEventListener('click', function () {
      var id = b.dataset.id, max = parseInt(b.dataset.max, 10);
      if (!cart[id]) cart[id] = { name: b.dataset.name, qty: 1, max: max };
      else if (cart[id].qty < max) cart[id].qty++;
      else { alert('สินค้าพร้อมโอนไม่พอ (มี ' + max + ' ชิ้น)'); return; }
      render();
    });
  });

  body.addEventListener('input', function (e) {
    if (!e.target.classList.contains('qtyInput')) return;
    var id = e.target.dataset.id, v = parseInt(e.target.value, 10) || 1;
    if (v > cart[id].max) { v = cart[id].max; alert('สินค้าพร้อมโอนไม่พอ (มี ' + cart[id].max + ' ชิ้น)'); }
    if (v < 1) v = 1;
    cart[id].qty = v;
    render();
  });

  body.addEventListener('click', function (e) {
    if (!e.target.classList.contains('rmBtn')) return;
    delete cart[e.target.dataset.id];
    render();
  });

  var q = document.getElementById('q');
  q.addEventListener('input', function () {
    var t = q.value.toLowerCase();
    document.querySelectorAll('#prodTable tbody tr').forEach(function (tr) {
      tr.style.display = tr.dataset.search.indexOf(t) > -1 ? '' : 'none';
    });
  });

  render();
})();
</script>

@endsection
