@extends('layouts.app')
@section('title', '📄 สร้างบิลเรียกเก็บ')
@section('crumb', 'บัญชี · สร้างบิลใหม่')

@section('content')

<form method="POST" action="{{ route('accounting.invoices.store') }}" id="invoiceForm">
@csrf

<div class="grid g2">
  <div class="card">
    <h3>📋 ข้อมูลบิล</h3>
    <div class="body">
      <div class="field"><label>เลขที่บิล</label><input type="text" value="{{ $docNo }}" readonly style="background:#f8fafc"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="field"><label>วันที่ออกบิล *</label><input type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}" required></div>
        <div class="field"><label>ครบกำหนด *</label><input type="date" name="due_date" value="{{ old('due_date', now()->addDays(30)->toDateString()) }}" required></div>
      </div>
      <div class="field"><label>อัตรา VAT (%)</label><input type="number" name="vat_rate" value="{{ old('vat_rate', 7) }}" step="0.01" min="0" max="100" required></div>
      <div class="field"><label>หมายเหตุ</label><textarea name="notes" rows="2">{{ old('notes') }}</textarea></div>
    </div>
  </div>

  <div class="card">
    <h3>👤 ข้อมูลลูกค้า</h3>
    <div class="body">
      <div class="field"><label>ชื่อลูกค้า *</label><input type="text" name="customer_name" value="{{ old('customer_name') }}" required placeholder="ชื่อบริษัท / บุคคล"></div>
      <div class="field"><label>ที่อยู่</label><textarea name="customer_address" rows="2">{{ old('customer_address') }}</textarea></div>
      <div class="field"><label>เลขผู้เสียภาษี</label><input type="text" name="customer_tax_id" value="{{ old('customer_tax_id') }}" placeholder="X-XXXX-XXXXX-XX-X" maxlength="20"></div>
    </div>
  </div>
</div>

<div class="card">
  <h3>📦 รายการสินค้า/บริการ
    <button type="button" class="btn btn-sm btn-create" id="addItemBtn">➕ เพิ่มรายการ</button>
  </h3>
  <div class="body">
    <table id="itemsTable">
      <thead><tr><th style="width:50%">รายการ</th><th class="num" style="width:12%">จำนวน</th><th class="num" style="width:15%">ราคา/หน่วย</th><th class="num" style="width:15%">จำนวนเงิน</th><th style="width:8%"></th></tr></thead>
      <tbody id="itemsBody"></tbody>
      <tfoot>
        <tr><td colspan="3" class="num"><b>รวมก่อน VAT</b></td><td class="num" id="subtotalCell"><b>0.00</b></td><td></td></tr>
        <tr><td colspan="3" class="num"><b>VAT <span id="vatRateLabel">7</span>%</b></td><td class="num" id="vatCell"><b>0.00</b></td><td></td></tr>
        <tr style="background:#f0fdf4"><td colspan="3" class="num" style="font-size:16px"><b>ยอดรวมทั้งสิ้น</b></td><td class="num" id="totalCell" style="font-size:16px;color:var(--ok-dark)"><b>0.00</b></td><td></td></tr>
      </tfoot>
    </table>
  </div>
</div>

<div style="text-align:right">
  <a href="{{ route('accounting.invoices') }}" class="btn">❌ ยกเลิก</a>
  <button type="submit" class="btn btn-create" style="padding:10px 24px;font-size:14px">💾 สร้างบิลเรียกเก็บ</button>
</div>

</form>

@push('scripts')
<script>
var rowId = 0;
function addItem(desc, qty, price) {
  rowId++;
  var tr = document.createElement('tr');
  tr.id = 'row-' + rowId;
  tr.innerHTML = '<td><input type="text" name="items['+rowId+'][description]" value="'+(desc||'')+'" placeholder="ชื่อสินค้า/บริการ" required></td>'
    +'<td><input type="number" name="items['+rowId+'][qty]" class="qty" value="'+(qty||1)+'" step="0.01" min="0.01" required style="text-align:right"></td>'
    +'<td><input type="number" name="items['+rowId+'][unit_price]" class="price" value="'+(price||0)+'" step="0.01" min="0" required style="text-align:right"></td>'
    +'<td class="num amountCell">0.00</td>'
    +'<td><button type="button" class="btn btn-sm btn-d rmBtn">🗑️</button></td>';
  document.getElementById('itemsBody').appendChild(tr);
  tr.querySelector('.rmBtn').addEventListener('click', function(){ tr.remove(); calcTotal(); });
  tr.querySelectorAll('.qty,.price').forEach(function(i){ i.addEventListener('input', calcTotal); });
  calcTotal();
}

function calcTotal() {
  var sub = 0;
  document.querySelectorAll('#itemsBody tr').forEach(function(tr) {
    var q = parseFloat(tr.querySelector('.qty').value) || 0;
    var p = parseFloat(tr.querySelector('.price').value) || 0;
    var amt = q * p;
    tr.querySelector('.amountCell').textContent = amt.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    sub += amt;
  });
  var vatRate = parseFloat(document.querySelector('[name="vat_rate"]').value) || 0;
  var vat = sub * vatRate / 100;
  document.getElementById('vatRateLabel').textContent = vatRate;
  document.getElementById('subtotalCell').innerHTML = '<b>'+sub.toLocaleString(undefined,{minimumFractionDigits:2})+'</b>';
  document.getElementById('vatCell').innerHTML = '<b>'+vat.toLocaleString(undefined,{minimumFractionDigits:2})+'</b>';
  document.getElementById('totalCell').innerHTML = '<b>'+(sub+vat).toLocaleString(undefined,{minimumFractionDigits:2})+'</b>';
}

document.getElementById('addItemBtn').addEventListener('click', function(){ addItem('', 1, 0); });
document.querySelector('[name="vat_rate"]').addEventListener('input', calcTotal);

// เพิ่ม 1 รายการเริ่มต้น
addItem('', 1, 0);
</script>
@endpush
@endsection
