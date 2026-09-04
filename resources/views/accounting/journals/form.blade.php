@extends('layouts.app')
@section('title', '📒 ลงบัญชีแยก')
@section('crumb', 'บัญชี · ลงบัญชีแยก')

@section('content')
<form method="POST" action="{{ route('accounting.journals.store') }}" id="jvForm">
@csrf
<div class="card">
  <div class="section-bar">📋 ข้อมูลรายการ</div>
  <div class="body">
    <div class="grid g3">
      <div class="field"><label>สาขา *</label>
        <select name="org_node_id" required><option value="">เลือก...</option>
          @foreach($nodes as $n)<option value="{{ $n->id }}">{{ $n->name }}</option>@endforeach
        </select>
      </div>
      <div class="field"><label>เลขที่</label><input type="text" value="{{ $docNo }}" readonly></div>
      <div class="field"><label>วันที่ *</label><input type="date" name="entry_date" value="{{ now()->toDateString() }}" required></div>
    </div>
    <div class="field" style="margin-top:12px"><label>รายละเอียด *</label><input type="text" name="description" required maxlength="500"></div>
    <div class="field"><label>หมายเหตุ</label><textarea name="notes" rows="2"></textarea></div>
  </div>
</div>

<div class="card">
  <div class="section-bar">📒 รายการเดินบัญชี (Debit = Credit)</div>
  <div class="body">
    <table><thead><tr><th>บัญชี</th><th class="num">Debit</th><th class="num">Credit</th><th>รายละเอียด</th><th></th></tr></thead>
    <tbody id="linesBody"></tbody>
    <tfoot><tr>
      <td><button type="button" class="btn btn-sm" onclick="addLine()">➕ เพิ่มบรรทัด</button></td>
      <td class="num"><b id="sumDebit">0.00</b></td>
      <td class="num"><b id="sumCredit">0.00</b></td>
      <td id="balanceCheck"></td>
      <td></td>
    </tr></tfoot></table>
  </div>
</div>

<div style="margin-top:16px">
  <button type="submit" class="btn btn-p">💾 บันทึก (ร่าง)</button>
  <a href="{{ route('accounting.journals') }}" class="btn">❌ ยกเลิก</a>
</div>
</form>

<script>
const accounts = @json($accounts);
let idx=0;
function addLine(){
  const i=idx++;
  const tr=document.createElement('tr');tr.id='line_'+i;
  tr.innerHTML=`
    <td><select name="lines[${i}][account_id]" required>
      <option value="">เลือกบัญชี...</option>
      ${accounts.map(a=>`<option value="${a.id}">${a.code} - ${a.name}</option>`).join('')}
    </select></td>
    <td><input type="number" name="lines[${i}][debit]" min="0" step="0.01" value="0" onchange="calcSums()" style="width:120px"></td>
    <td><input type="number" name="lines[${i}][credit]" min="0" step="0.01" value="0" onchange="calcSums()" style="width:120px"></td>
    <td><input type="text" name="lines[${i}][description]" style="width:100%"></td>
    <td><button type="button" class="btn btn-sm btn-del" onclick="document.getElementById('line_${i}').remove();calcSums()">🗑️</button></td>`;
  document.getElementById('linesBody').appendChild(tr);
}
function calcSums(){
  let d=0,c=0;
  document.querySelectorAll('input[name$="[debit]"]').forEach(e=>{d+=parseFloat(e.value)||0});
  document.querySelectorAll('input[name$="[credit]"]').forEach(e=>{c+=parseFloat(e.value)||0});
  document.getElementById('sumDebit').textContent=d.toFixed(2);
  document.getElementById('sumCredit').textContent=c.toFixed(2);
  const diff=Math.abs(d-c);
  document.getElementById('balanceCheck').innerHTML = diff < 0.01
    ? '<span style="color:var(--ok-dark);font-weight:700">✅ สมดุล</span>'
    : `<span style="color:var(--bad-dark);font-weight:700">❌ ต่าง ${diff.toFixed(2)}</span>`;
}
addLine();addLine();
</script>
@endsection
