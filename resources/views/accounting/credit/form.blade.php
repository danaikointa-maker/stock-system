@extends('layouts.app')
@section('title', '↩️ สร้างใบลดหนี้')
@section('crumb', 'บัญชี · ใบลดหนี้ · สร้าง')

@section('content')
<form method="POST" action="{{ route('accounting.credit.store') }}" id="creditForm">
@csrf

<div class="card">
  <div class="section-bar">📋 ข้อมูลใบลดหนี้</div>
  <div class="body">
    <div class="grid g3">
      <div class="field">
        <label>สาขา/คลัง *</label>
        <select name="org_node_id" required>
          <option value="">เลือก...</option>
          @foreach($nodes as $n)
            <option value="{{ $n->id }}" {{ ($deliveryNote && $deliveryNote->org_node_id == $n->id) ? 'selected' : '' }}>{{ $n->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>ประเภท *</label>
        <select name="type" required>
          <option value="return">↩️ คืนสินค้า</option>
          <option value="discount">💰 ส่วนลด/ลดราคา</option>
          <option value="cancel">❌ ยกเลิก</option>
          <option value="adjustment">🔧 ปรับปรุง</option>
        </select>
      </div>
      <div class="field">
        <label>ชื่อลูกค้า *</label>
        <input type="text" name="customer_name" required value="{{ $deliveryNote?->customer_name }}">
      </div>
    </div>
    <div class="grid g2" style="margin-top:12px">
      <div class="field">
        <label>อ้างอิงใบส่งของ</label>
        <select name="delivery_note_id">
          <option value="">ไม่มี</option>
          @foreach($deliveries as $d)
            <option value="{{ $d->id }}" {{ ($deliveryNote && $deliveryNote->id == $d->id) ? 'selected' : '' }}>{{ $d->doc_no }} - {{ $d->customer_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>เหตุผล / สาเหตุ *</label>
        <input type="text" name="reason" required placeholder="เช่น สินค้าชำรุด, ส่งผิดรายการ">
      </div>
    </div>
    <div class="grid g2" style="margin-top:12px">
      <div class="field">
        <label>อัตรา VAT (%)</label>
        <input type="number" name="vat_rate" value="7" step="0.01" min="0" max="100">
      </div>
      <div class="field">
        <label>หมายเหตุ</label>
        <input type="text" name="note">
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="section-bar">📦 รายการสินค้า (คืน/ลด)</div>
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
          <td colspan="2" class="num"><b>ยอดรวม:</b></td>
          <td class="num"><b id="grandTotal">0.00</b></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div style="margin-top:16px;padding:12px;background:#fef2f2;border:2px solid #fca5a5;border-radius:10px;font-size:13px">
  ⚠️ <b>คำเตือน:</b> เมื่อกดบันทึกจะสร้างเป็น <b>"ร่าง"</b> — ต้องกดยืนยันอีกครั้งเพื่อตัดสต๊อกกลับ + บันทึกลงบัญชี (แก้ไขไม่ได้อีก)
</div>

<div style="margin-top:16px">
  <button type="submit" class="btn btn-p">💾 บันทึก (ร่าง)</button>
  <a href="{{ route('accounting.credit') }}" class="btn">❌ ยกเลิก</a>
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
    <td><input type="number" name="items[${idx}][lot_id]" placeholder="lot_id"></td>
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
  document.getElementById('cost_' + idx).value = option.dataset.cost || 0;
  document.getElementById('price_' + idx).value = option.dataset.price || 0;
  calcTotal(idx);
}

function calcTotal(idx) {
  const qty = parseFloat(document.querySelector(`input[name="items[${idx}][qty]"]`).value) || 0;
  const price = parseFloat(document.getElementById('price_' + idx).value) || 0;
  document.getElementById('total_' + idx).textContent = (qty * price).toFixed(2);
  calcGrandTotal();
}

function calcGrandTotal() {
  let sum = 0;
  document.querySelectorAll('[id^="total_"]').forEach(el => { sum += parseFloat(el.textContent) || 0; });
  document.getElementById('grandTotal').textContent = sum.toFixed(2);
}

function removeItem(idx) {
  const row = document.getElementById('item_' + idx);
  if (row) row.remove();
  calcGrandTotal();
}

addItem();

// Pre-fill from delivery note if available
@if($deliveryNote)
document.querySelector('[name="customer_name"]').value = "{{ $deliveryNote->customer_name }}";
@endif
</script>
@endsection
