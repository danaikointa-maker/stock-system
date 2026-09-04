@extends('layouts.app')
@section('title', '📒 General Ledger')
@section('crumb', 'บัญชี · General Ledger')

@section('content')
<div class="card">
  <form method="GET" class="filters">
    <div class="field"><label>บัญชี</label>
      <select name="account_id"><option value="">เลือก...</option>
        @foreach($accounts as $a)<option value="{{ $a->id }}" {{ $accountId == $a->id ? 'selected' : '' }}>{{ $a->code }} - {{ $a->name }}</option>@endforeach
      </select>
    </div>
    <div class="field"><label>ตั้งแต่</label><input type="date" name="from" value="{{ $from }}"></div>
    <div class="field"><label>ถึง</label><input type="date" name="to" value="{{ $to }}"></div>
    <button class="btn btn-p">🔍 แสดง</button>
  </form>
</div>

@if($accountId && !empty($entries))
<div class="card" style="margin-top:16px">
  <div class="section-bar">{{ $accounts->firstWhere('id',$accountId)?->code }} — {{ $accounts->firstWhere('id',$accountId)?->name }}</div>
  <table>
    <thead><tr><th>วันที่</th><th>อ้างอิง</th><th>รายละเอียด</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">ยอดคงเหลือ</th></tr></thead>
    <tbody>
    @foreach($entries as $e)
      <tr>
        <td>{{ $e['date'] }}</td>
        <td><code>{{ $e['reference'] }}</code></td>
        <td>{{ $e['description'] }}</td>
        <td class="num">{{ $e['debit'] > 0 ? number_format($e['debit'],2) : '' }}</td>
        <td class="num">{{ $e['credit'] > 0 ? number_format($e['credit'],2) : '' }}</td>
        <td class="num"><b>{{ number_format($e['balance'],2) }}</b></td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@elseif($accountId)
  <div class="empty" style="margin-top:16px">ไม่มีรายการในช่วงวันที่เลือก</div>
@endif
@endsection
