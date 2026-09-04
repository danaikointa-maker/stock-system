@extends('layouts.app')
@section('title', '⚖️ งบทดลอง')
@section('crumb', 'บัญชี · งบทดลอง (Trial Balance)')

@section('content')
<div class="card">
  <form method="GET" class="filters">
    <div class="field"><label> ณ วันที่</label><input type="date" name="as_of" value="{{ $asOf }}"></div>
    <button class="btn btn-p">🔍 แสดง</button>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <div style="text-align:center;padding:16px;border-bottom:2px solid var(--line)">
    <h3>⚖️ งบทดลอง (Trial Balance)</h3>
    <div style="color:var(--muted);font-size:13px">ณ วันที่ {{ $asOf }}</div>
  </div>
  <table>
    <thead><tr><th>รหัส</th><th>ชื่อบัญชี</th><th>หมวด</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
    <tbody>
    @forelse($results as $r)
      <tr>
        <td><code>{{ $r['code'] }}</code></td>
        <td><b>{{ $r['name'] }}</b></td>
        <td>{{ ucfirst($r['category']) }}</td>
        <td class="num">{{ $r['debit'] > 0 ? number_format($r['debit'],2) : '' }}</td>
        <td class="num">{{ $r['credit'] > 0 ? number_format($r['credit'],2) : '' }}</td>
      </tr>
    @empty
      <tr><td colspan="5" class="empty">ไม่มีข้อมูล</td></tr>
    @endforelse
    </tbody>
    <tfoot>
      <tr style="background:#f0fdf4;font-size:14px">
        <td colspan="3" style="text-align:right"><b>รวม</b></td>
        <td class="num"><b>{{ number_format($totalDebit,2) }}</b></td>
        <td class="num"><b>{{ number_format($totalCredit,2) }}</b></td>
      </tr>
      <tr>
        <td colspan="3" style="text-align:right"><b>ผลต่าง</b></td>
        <td colspan="2" class="num">
          @php $diff = bccomp($totalDebit, $totalCredit, 2); @endphp
          @if($diff === 0)
            <b style="color:var(--ok-dark)">✅ สมดุล (Dr = Cr)</b>
          @else
            <b style="color:var(--bad-dark)">❌ ไม่สมดุล ต่าง {{ number_format(abs(bcsub($totalDebit, $totalCredit, 2)), 2) }}</b>
          @endif
        </td>
      </tr>
    </tfoot>
  </table>
</div>
@endsection
