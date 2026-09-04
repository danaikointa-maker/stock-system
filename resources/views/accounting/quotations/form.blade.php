@extends('layouts.app')
@section('title', '📋 สร้างใบเสนอราคา')
@section('crumb', 'บัญชี · ใบเสนอราคา · สร้าง')

@section('content')
<form method="POST" action="{{ route('accounting.quotations.store') }}" id="qtForm">
@csrf
<div class="grid g2">
  <div class="card">
    <div class="section-bar">📋 ข้อมูลลูกค้า</div>
    <div class="body">
      <div class="field"><label>สาขา/คลัง *</label>
        <select name="org_node_id" required>
          <option value="">เลือก...</option>
          @foreach($nodes as $n)<option value="{{ $n->id }}">{{ $n->name }}</option>@endforeach
        </select>
      </div>
      <div class="field"><label>เลขที่เอกสาร</label><input type="text" value="{{ $docNo }}" readonly></div>
      <div class="field"><label>ชื่อลูกค้า *</label><input type="text" name="customer_name" required maxlength="200"></div>
      <div class="field"><label>ที่อยู่</label><textarea name="customer_address" rows="2"></textarea></div>
      <div class="field"><label>เลขผู้เสียภาษี</label><input type="text" name="customer_tax_id" maxlength="20"></div>
      <div class="field"><label>ผู้ติดต่อ</label><input type="text" name="customer_contact" maxlength="100"></div>
    </div>
  </div>

  <div class="card">
    <div class="section-bar">📅 วันที่</div>
    <div class="body">
      <div class="field"><label>วันที่ออก *</label><input type="date" name="issue_date" value="{{ now()->toDateString() }}" required></div>
      <div class="field"><label>ใช้ได้ถึง *</label><input type="date" name="valid_until" value="{{ now()->addDays(30)->toDateString() }}" required></div>
      <div class="field"><label>VAT (%)</label><input type="number" name="vat_rate" value="7" step="0.01" min="0" max="100"></div>
      <div class="field"><label>หมายเหตุ</label><textarea name="notes" rows="2"></textarea></div>
      <div class="field"><label>เงื่อนไข</label><textarea name="terms" rows="3" placeholder="เช่น ชำระภายใน 30 วัน"></textarea></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="section-bar">📦 รายการ</div>
  <div class="body">
    <table><thead><tr><th>รายละเอียด</th><th class="num">จำนวน</th><th class="num">ราคา/หน่วย</th><th class="num">ยอดรวม</th><th></th></tr></thead>
    <tbody id="itemsBody"></tbody>
    <tfoot><tr>
      <td colspan="2"><button type="button" class="btn btn-sm" onclick="addItem()">➕ เพิ่ม</button></td>
      <td class="num"><b>รวม:</b></td>
      <td class="num"><b id="grandTotal">0.00</b></td>
      <td></td>
    </tr></tfoot></table>
  </div>
</div>

<div style="margin-top:16px">
  <button type="submit" class="btn btn-p">💾 บันทึก</button>
  <a href="{{ route('accounting.quotations') }}" class="btn">❌ ยกเลิก</a>
</div>
</form>

<script>
let idx=0;
function addItem(){
  const i=idx++;
  const tr=document.createElement('tr');tr.id='item_'+i;
  tr.innerHTML=`
    <td><input type="text" name="items[${i}][description]" required style="width:100%"></td>
    <td><input type="number" name="items[${i}][qty]" min="0.01" step="0.01" required onchange="calc(${i})" style="width:80px"></td>
    <td><input type="number" name="items[${i}][unit_price]" min="0" step="0.01" required onchange="calc(${i})" style="width:100px"></td>
    <td class="num" id="total_${i}">0.00</td>
    <td><button type="button" class="btn btn-sm btn-del" onclick="document.getElementById('item_${i}').remove();calcG()">🗑️</button></td>`;
  document.getElementById('itemsBody').appendChild(tr);
}
function calc(i){
  const q=parseFloat(document.querySelector(`input[name="items[${i}][qty]"]`).value)||0;
  const p=parseFloat(document.querySelector(`input[name="items[${i}][unit_price]"]`).value)||0;
  document.getElementById('total_'+i).textContent=(q*p).toFixed(2);calcG();
}
function calcG(){
  let s=0;document.querySelectorAll('[id^="total_"]').forEach(e=>{s+=parseFloat(e.textContent)||0});
  document.getElementById('grandTotal').textContent=s.toFixed(2);
}
addItem();
</script>
@endsection
