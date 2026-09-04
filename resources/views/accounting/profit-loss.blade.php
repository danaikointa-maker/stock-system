@extends('layouts.app')
@section('title', '📈 งบกำไรขาดทุน')
@section('crumb', 'บัญชี · งบกำไรขาดทุน (Profit & Loss)')

@section('content')
<div class="card">
  <form method="GET" class="filters">
    <div class="field"><label>ตั้งแต่</label><input type="date" name="from" value="{{ $from }}"></div>
    <div class="field"><label>ถึง</label><input type="date" name="to" value="{{ $to }}"></div>
    <button class="btn btn-p">📊 แสดง</button>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <div style="text-align:center;padding:16px;border-bottom:2px solid var(--line)">
    <h3>📈 งบกำไรขาดทุน (Profit & Loss Statement)</h3>
    <div style="color:var(--muted);font-size:13px">ตั้งแต่วันที่ {{ $from }} ถึง {{ $to }}</div>
  </div>

  <div style="padding:20px">
    {{-- Revenue --}}
    <h4 style="color:var(--ok-dark);margin-bottom:10px">💰 รายได้</h4>
    <table>
      <tbody>
      @forelse($revenues as $r)
        <tr><td style="padding-left:20px">{{ $r['name'] }}</td><td class="num">{{ number_format($r['amount'],2) }}</td></tr>
      @empty
        <tr><td class="empty" colspan="2">ไม่มีรายได้</td></tr>
      @endforelse
      <tr style="background:#f0fdf4"><td style="text-align:right"><b>รวมรายได้</b></td><td class="num"><b style="color:var(--ok-dark)">{{ number_format($totalRevenue,2) }}</b></td></tr>
      </tbody>
    </table>

    {{-- Expenses --}}
    <h4 style="color:var(--bad-dark);margin:20px 0 10px">💸 ค่าใช้จ่าย</h4>
    <table>
      <tbody>
      @forelse($expenses as $e)
        <tr><td style="padding-left:20px">{{ $e['name'] }}</td><td class="num">{{ number_format($e['amount'],2) }}</td></tr>
      @empty
        <tr><td class="empty" colspan="2">ไม่มีค่าใช้จ่าย</td></tr>
      @endforelse
      <tr style="background:#fef2f2"><td style="text-align:right"><b>รวมค่าใช้จ่าย</b></td><td class="num"><b style="color:var(--bad-dark)">{{ number_format($totalExpense,2) }}</b></td></tr>
      </tbody>
    </table>

    {{-- Net Profit --}}
    <div style="margin-top:24px;padding:20px;border:3px double {{ $netProfit >= 0 ? 'var(--ok-dark)' : 'var(--bad-dark)' }};border-radius:12px;text-align:center">
      <div style="font-size:13px;color:var(--muted);font-weight:700;text-transform:uppercase">
        {{ $netProfit >= 0 ? 'กำไรสุทธิ (Net Profit)' : 'ขาดทุนสุทธิ (Net Loss)' }}
      </div>
      <div style="font-size:28px;font-weight:800;color:{{ $netProfit >= 0 ? 'var(--ok-dark)' : 'var(--bad-dark)' }}">
        {{ number_format(abs($netProfit),2) }} บาท
      </div>
    </div>
  </div>
</div>
@endsection
