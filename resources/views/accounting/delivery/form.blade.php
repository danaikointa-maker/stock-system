@extends('layouts.app')
@section('title', '🚚 สร้างใบส่งของ')
@section('crumb', 'บัญชี · ใบส่งของ · สร้าง')

@section('content')
<form method="POST" action="{{ route('accounting.delivery.store') }}" id="deliveryForm">
@csrf

<div class="grid g2">
  <div class="card">
    <div class="section-bar">📋 ข้อมูลการส่ง</div>
    <div class="body">
      <div class="field">
        <label>สาขา/คลัง *</label>
        <select name="org_node_id" required>
          <option value="">เลือก...</option>
          @foreach($nodes as $n)
            <option value="{{ $n->id }}">{{ $n->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>อ้างอิงการขาย</label>
        <select name="sale_id">
          <option value="">ไม่มี</option>
          @foreach($sales as $s)
            <option value="{{ $s->id }}">{{ $s->doc_no }} - {{ $s->customer->name ?? 'ลูกค้าทั่วไป' }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>ชื่อลูกค้า *</label>
        <input type="text" name="customer_name" required maxlength="150">
      </div>
      <div class="field">
        <label>ที่อยู่จัดส่ง</label>
        <textarea name="delivery_address" rows="2"></textarea>
      </div>
      <div class="field">
        <label>ผู้รับ</label>
        <input type="text" name="recipient_name" maxlength="100">
      </div>
      <div class="field">
        <label>เบอร์โทรผู้รับ</label>
        <input type="text" name="recipient_phone" maxlength="30">
      </div>
    </div>
  </div>

  <div class="card">
    <div class="section-bar">🚚 ข้อมูลขนส่ง</div>
    <div class="body">
      <div class="field">
        <label>บริษัทขนส่ง</label>
        <input type="text" name="carrier" maxlength="100" placeholder="เช่น Kerry, Flash, J&T">
      </div>
      <div class="field">
        <label>เลข Tracking</label>
        <input type="text" name="tracking_no" maxlength="50">
      </div>
      <div class="field">
        <label>หมายเหตุ</label>
        <textarea name="note" rows="2"></textarea>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="section-bar">📦 รายการสินค้า</div>
  <div class="body">
    <table id="itemsTable">
      <thead>
        <tr>
          <th>สินค้า</th>
          <th>ล็อต</th>
          <th class="num">จำนวน</th>
          <th class="num">ต้นทุน</th>
          <th class="num">ราคาขาย</th>
          <th class="num">ยอดรวม</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="itemsBody"></tbody>
      <tfoot>
        <tr>
          <td colspan="3">
            <button type="button" class="btn btn-sm" onclick="addItem()">➕ เพิ่มรายการ</button>
          </td>
          <td colspan="2" class="num"><b>รวม:</b></td>
          <td class="num"><b id="grandTotal">0.00</b></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div style="margin-top:16px">
  <button type="submit" class="btn btn-p">💾 บันทึกใบส่งของ</button>
  <a href="{{ route('accounting.delivery') }}" class="btn">❌ ยกเลิก</a>
</div>

</form>

<script>
let itemCount = 0;
const products = @json($products);

function addItem() {
  const idx = itemCount++;
  const tbody = document.getElementById('itemsBody');
  const tr = document.createElement('tr');
  tr.id = 'item_' + idx;
  tr.innerHTML = `
    <td>
      <select name="items[${idx}][product_id]" required onchange="updateCost(${idx}, this.value)">
        <option value="">เลือกสินค้า...</option>
        ${products.map(p => `<option value="${p.id}" data-cost="${p.cost_price}" data-price="${p.retail_price}">${p.name}</option>`).join('')}
      </select>
    </td>
    <td><input type="number" name="items[${idx}][lot_id]" placeholder="lot_id (ถ้ามี)"></td>
    <td><input type="number" name="items[${idx}][qty]" min="1" required onchange="calcTotal(${idx})"></td>
    <td><input type="number" name="items[${idx}][unit_cost]" step="0.01" min="0" required id="cost_${idx}" onchange="calcTotal(${idx})"></td>
    <td><input type="number" name="items[${idx}][unit_price]" step="0.01" min="0" id="price_${idx}" onchange="calcTotal(${idx})"></td>
    <td class="num" id="total_${idx}">0.00</td>
    <td><button type="button" class="btn btn-sm btn-del" onclick="removeItem(${idx})">🗑️</button></td>
  `;
  tbody.appendChild(tr);
}

function updateCost(idx, productId) {
  const select = document.querySelector(`select[name="items[${idx}][product_id]"]`);
  const option = select.options[select.selectedIndex];
  const cost = option.dataset.cost || 0;
  const price = option.dataset.price || 0;
  document.getElementById('cost_' + idx).value = cost;
  document.getElementById('price_' + idx).value = price;
  calcTotal(idx);
}

function calcTotal(idx) {
  const qty = parseFloat(document.querySelector(`input[name="items[${idx}][qty]"]`).value) || 0;
  const price = parseFloat(document.getElementById('price_' + idx).value) || 0;
  const total = qty * price;
  document.getElementById('total_' + idx).textContent = total.toFixed(2);
  calcGrandTotal();
}

function calcGrandTotal() {
  let sum = 0;
  document.querySelectorAll('[id^="total_"]').forEach(el => {
    sum += parseFloat(el.textContent) || 0;
  });
  document.getElementById('grandTotal').textContent = sum.toFixed(2);
}

function removeItem(idx) {
  const row = document.getElementById('item_' + idx);
  if (row) row.remove();
  calcGrandTotal();
}

// เพิ่ม 1 รายการเริ่มต้น
addItem();
</script>
@endsection
