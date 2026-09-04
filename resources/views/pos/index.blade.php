@extends('layouts.app')
@section('title', 'เปิดบิลขาย (POS)')
@section('crumb', $node->level_id->label() . ' · ' . $node->name)

@section('content')

@if($errors->has('pos'))
  <div class="alert a-bad">{{ $errors->first('pos') }}</div>
@endif

<div class="grid g4" style="margin-bottom:16px">
  <div class="kpi ok">
    <div class="lbl">ยอดขายวันนี้</div>
    <div class="val">{{ number_format($todayTotal, 0) }}</div>
    <div class="sub">{{ $todayBills }} บิล</div>
  </div>
  <div class="kpi">
    <div class="lbl">สินค้าพร้อมขาย</div>
    <div class="val">{{ $items->count() }}</div>
    <div class="sub">รายการที่มีของในสต๊อก</div>
  </div>
  <div class="card" style="grid-column:span 2;margin:0">
    <div class="body" style="padding:12px 14px">
      <form method="GET" class="filters" style="align-items:flex-end">
        <div class="field" style="flex:1">
          <label>ขายในนามหน่วยงาน</label>
          <select name="node" onchange="this.form.submit()">
            @foreach($nodes as $n)
              <option value="{{ $n->id }}" @selected($n->id === $node->id)>
                {{ $n->name }} ({{ $n->code }}) · {{ $n->level_id->label() }}
              </option>
            @endforeach
          </select>
        </div>
        <a href="{{ route('pos.history') }}" class="btn">📋 ประวัติการขาย</a>
      </form>
    </div>
  </div>
</div>

@if($items->isEmpty())
  <div class="card"><div class="empty">
    ไม่มีสินค้าพร้อมขายในหน่วยงานนี้ — กรุณาเบิกสินค้าจากต้นสังกัดก่อน
  </div></div>
@else

<form method="POST" action="{{ route('pos.store') }}" id="posForm">
  @csrf
  <input type="hidden" name="org_node_id" value="{{ $node->id }}">

  <div class="grid" style="grid-template-columns:1.35fr 1fr;gap:16px;align-items:start">

    {{-- เลือกสินค้า --}}
    <div class="card" style="margin:0">
      <h3>
        เลือกสินค้า
        <input type="search" id="q" placeholder="ค้นหาชื่อ หรือ SKU"
               style="width:210px;padding:5px 10px;font-size:12.5px">
      </h3>
      <table id="prodTable">
        <thead>
          <tr><th>SKU</th><th>สินค้า</th><th class="num">คงเหลือ</th><th class="num">ราคา</th><th style="width:80px"></th></tr>
        </thead>
        <tbody>
        @foreach($items as $b)
          <tr data-search="{{ strtolower($b->product->sku . ' ' . $b->product->name) }}">
            <td><code>{{ $b->product->sku }}</code></td>
            <td>
              {{ $b->product->name }}
              @if($b->product->points_per_unit)
                <div style="font-size:11px;color:var(--muted)">{{ $b->product->points_per_unit }} คะแนน/ชิ้น</div>
              @endif
            </td>
            <td class="num"><span class="badge b-green">{{ number_format($b->qty_on_hand - $b->qty_reserved) }}</span></td>
            <td class="num">{{ number_format($b->product->retail_price, 2) }}</td>
            <td class="num">
              <button type="button" class="btn btn-sm btn-p addBtn" title="เพิ่มสินค้า"
                      data-id="{{ $b->product->id }}"
                      data-sku="{{ $b->product->sku }}"
                      data-name="{{ $b->product->name }}"
                      data-price="{{ $b->product->retail_price }}"
                      data-max="{{ $b->qty_on_hand - $b->qty_reserved }}">เพิ่ม</button>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

    {{-- ตะกร้า --}}
    <div class="card" style="margin:0;position:sticky;top:16px">
      <h3>รายการในบิล <span id="cartCount" class="badge b-gray">0 รายการ</span></h3>

      <table id="cartTable">
        <thead>
          <tr><th>สินค้า</th><th style="width:74px" class="num">จำนวน</th><th class="num">รวม</th><th style="width:34px"></th></tr>
        </thead>
        <tbody id="cartBody"></tbody>
      </table>

      <div id="cartEmpty" class="empty" style="padding:26px">ยังไม่มีสินค้าในบิล</div>

      <div class="body" style="border-top:1px solid var(--line)">
        <div class="field">
          <label for="customer_phone">เบอร์โทรลูกค้า (สำหรับสะสมคะแนน)</label>
          <input type="text" id="customer_phone" name="customer_phone"
                 value="{{ old('customer_phone') }}" placeholder="ไม่กรอกก็ได้">
        </div>

        <div class="filters" style="gap:10px">
          <div class="field" style="flex:1">
            <label for="payment_method">วิธีชำระเงิน</label>
            <select id="payment_method" name="payment_method">
              <option value="cash">เงินสด</option>
              <option value="qr">สแกน QR</option>
              <option value="transfer">โอนเงิน</option>
              <option value="credit">เครดิต</option>
            </select>
          </div>
          <div class="field" style="flex:1">
            <label for="discount">ส่วนลดท้ายบิล</label>
            <input type="number" step="0.01" min="0" id="discount" name="discount" value="0">
          </div>
        </div>

        <table style="margin-top:6px">
          <tbody>
            <tr><th>ยอดรวม</th><td class="num" id="subtotalCell">0.00</td></tr>
            <tr><th>ส่วนลด</th><td class="num" id="discountCell">0.00</td></tr>
            <tr style="background:#f7f9fc">
              <th style="font-size:14px">ยอดสุทธิ</th>
              <td class="num" style="font-size:19px;font-weight:700;color:var(--brand)" id="totalCell">0.00</td>
            </tr>
          </tbody>
        </table>

        <button type="submit" class="btn btn-p" id="submitBtn" disabled
                style="width:100%;margin-top:13px;padding:11px;font-size:15px;font-weight:600">
          💾 บันทึกบิลขาย
        </button>
      </div>
    </div>
  </div>
</form>
@endif

@if($recentSales->isNotEmpty())
<div class="card">
  <h3>🧾 บิลล่าสุดของหน่วยงานนี้ <a href="{{ route('pos.history') }}" class="btn btn-sm">📋 ดูทั้งหมด</a></h3>
  <table>
    <thead><tr><th>เลขที่</th><th>เวลา</th><th class="num">รายการ</th><th class="num">ยอด</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    @foreach($recentSales as $s)
      <tr>
        <td><code>{{ $s->doc_no }}</code></td>
        <td style="font-size:12.5px">{{ $s->sold_at->format('d/m/y H:i') }}</td>
        <td class="num">{{ $s->items->count() }}</td>
        <td class="num"><b>{{ number_format($s->total, 2) }}</b></td>
        <td>
          @if($s->status === 'completed')<span class="badge b-green">สำเร็จ</span>
          @else<span class="badge b-red">ยกเลิกแล้ว</span>@endif
        </td>
        <td class="num"><a href="{{ route('pos.receipt', $s) }}" class="btn btn-sm">🧾 ใบเสร็จ</a></td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif

<script>
(function () {
  var cart = {};
  var body = document.getElementById('cartBody');
  if (!body) return;

  var fmt = function (n) {
    return n.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  function render() {
    body.innerHTML = '';
    var keys = Object.keys(cart), subtotal = 0;

    keys.forEach(function (id) {
      var it = cart[id];
      var line = it.qty * it.price;
      subtotal += line;

      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td><div style="font-size:12.5px">' + it.name + '</div>' +
        '<div style="font-size:11px;color:var(--muted)">' + fmt(it.price) + ' × ' + it.qty + '</div></td>' +
        '<td class="num"><input type="number" min="1" max="' + it.max + '" value="' + it.qty +
        '" data-id="' + id + '" class="qtyInput" style="width:64px;padding:4px 6px;text-align:right"></td>' +
        '<td class="num"><b>' + fmt(line) + '</b></td>' +
        '<td class="num"><button type="button" class="btn btn-sm btn-d rmBtn" data-id="' + id + '" title="ลบรายการ">🗑️</button></td>' +
        '<input type="hidden" name="items[' + id + '][product_id]" value="' + id + '">' +
        '<input type="hidden" name="items[' + id + '][qty]" value="' + it.qty + '">' +
        '<input type="hidden" name="items[' + id + '][unit_price]" value="' + it.price + '">';
      body.appendChild(tr);
    });

    var discount = parseFloat(document.getElementById('discount').value) || 0;
    if (discount > subtotal) { discount = subtotal; document.getElementById('discount').value = discount; }

    document.getElementById('subtotalCell').textContent = fmt(subtotal);
    document.getElementById('discountCell').textContent = fmt(discount);
    document.getElementById('totalCell').textContent = fmt(subtotal - discount);
    document.getElementById('cartCount').textContent = keys.length + ' รายการ';
    document.getElementById('cartEmpty').style.display = keys.length ? 'none' : 'block';
    document.getElementById('submitBtn').disabled = keys.length === 0;
  }

  document.querySelectorAll('.addBtn').forEach(function (b) {
    b.addEventListener('click', function () {
      var id = b.dataset.id;
      var max = parseInt(b.dataset.max, 10);
      if (!cart[id]) {
        cart[id] = { name: b.dataset.name, price: parseFloat(b.dataset.price), qty: 1, max: max };
      } else if (cart[id].qty < max) {
        cart[id].qty++;
      } else {
        alert('สินค้าคงเหลือไม่พอ (มี ' + max + ' ชิ้น)');
        return;
      }
      render();
    });
  });

  body.addEventListener('input', function (e) {
    if (!e.target.classList.contains('qtyInput')) return;
    var id = e.target.dataset.id;
    var v = parseInt(e.target.value, 10) || 1;
    if (v > cart[id].max) { v = cart[id].max; alert('สินค้าคงเหลือไม่พอ (มี ' + cart[id].max + ' ชิ้น)'); }
    if (v < 1) v = 1;
    cart[id].qty = v;
    render();
  });

  body.addEventListener('click', function (e) {
    if (!e.target.classList.contains('rmBtn')) return;
    delete cart[e.target.dataset.id];
    render();
  });

  document.getElementById('discount').addEventListener('input', render);

  var q = document.getElementById('q');
  if (q) {
    q.addEventListener('input', function () {
      var term = q.value.toLowerCase();
      document.querySelectorAll('#prodTable tbody tr').forEach(function (tr) {
        tr.style.display = tr.dataset.search.indexOf(term) > -1 ? '' : 'none';
      });
    });
  }

  document.getElementById('posForm').addEventListener('submit', function () {
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').textContent = 'กำลังบันทึก...';
  });

  render();
})();
</script>

@endsection
