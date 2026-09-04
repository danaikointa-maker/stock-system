@extends('layouts.app')
@section('title', '🏦 งบแสดงฐานะการเงิน')
@section('crumb', 'บัญชี · งบแสดงฐานะการเงิน (Balance Sheet)')

@section('content')
<div class="card">
  <form method="GET" class="filters">
    <div class="field"><label> ณ วันที่</label><input type="date" name="as_of" value="{{ $asOf }}"></div>
    <button class="btn btn-p">🔍 แสดง</button>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <div style="text-align:center;padding:16px;border-bottom:2px solid var(--line)">
    <h3>🏦 งบแสดงฐานะการเงิน (Balance Sheet)</h3>
    <div style="color:var(--muted);font-size:13px">ณ วันที่ {{ $asOf }}</div>
  </div>

  <div style="padding:20px">
    <div class="grid g2">
      {{-- Assets --}}
      <div>
        <h4 style="color:var(--ok-dark);margin-bottom:10px">🏦 สินทรัพย์ (Assets)</h4>
        <table>
          <tbody>
          @foreach($sections['asset']['items'] as $item)
            <tr><td>{{ $item['name'] }}</td><td class="num">{{ number_format($item['amount'],2) }}</td></tr>
          @endforeach
          @if(empty($sections['asset']['items']))<tr><td class="empty" colspan="2">ไม่มีรายการ</td></tr>@endif
          <tr style="background:#f0fdf4"><td style="text-align:right"><b>รวมสินทรัพย์</b></td><td class="num"><b style="color:var(--ok-dark)">{{ number_format($sections['asset']['total'],2) }}</b></td></tr>
          </tbody>
        </table>
      </div>

      {{-- Liabilities + Equity --}}
      <div>
        <h4 style="color:var(--bad-dark);margin-bottom:10px">📋 หนี้สิน (Liabilities)</h4>
        <table>
          <tbody>
          @foreach($sections['liability']['items'] as $item)
            <tr><td>{{ $item['name'] }}</td><td class="num">{{ number_format($item['amount'],2) }}</td></tr>
          @endforeach
          @if(empty($sections['liability']['items']))<tr><td class="empty" colspan="2">ไม่มีรายการ</td></tr>@endif
          <tr style="background:#fef2f2"><td style="text-align:right"><b>รวมหนี้สิน</b></td><td class="num"><b>{{ number_format($sections['liability']['total'],2) }}</b></td></tr>
          </tbody>
        </table>

        <h4 style="color:#8b5cf6;margin:20px 0 10px">💎 ทุน (Equity)</h4>
        <table>
          <tbody>
          @foreach($sections['equity']['items'] as $item)
            <tr><td>{{ $item['name'] }}</td><td class="num">{{ number_format($item['amount'],2) }}</td></tr>
          @endforeach
          <tr><td>กำไรสะสม (Retained Earnings)</td><td class="num">{{ number_format($retainedEarnings,2) }}</td></tr>
          <tr style="background:#f5f3ff"><td style="text-align:right"><b>รวมทุน</b></td><td class="num"><b>{{ number_format(bcadd($sections['equity']['total'], $retainedEarnings, 2),2) }}</b></td></tr>
          </tbody>
        </table>

        <div style="margin-top:12px;padding:10px;background:#f0fdf4;border-radius:8px;text-align:right">
          <b>รวมหนี้สิน + ทุน:</b> <b style="color:var(--ok-dark)">{{ number_format($totalLiabilitiesAndEquity,2) }}</b>
        </div>
      </div>
    </div>

    {{-- Balance check --}}
    <div style="margin-top:24px;padding:16px;text-align:center;border:3px double {{ bccomp($totalAssets, $totalLiabilitiesAndEquity, 2) === 0 ? 'var(--ok-dark)' : 'var(--bad-dark)' }};border-radius:12px">
      @if(bccomp($totalAssets, $totalLiabilitiesAndEquity, 2) === 0)
        <div style="font-size:18px;color:var(--ok-dark);font-weight:800">✅ สมดุล: สินทรัพย์ = หนี้สิน + ทุน</div>
      @else
        <div style="font-size:18px;color:var(--bad-dark);font-weight:800">⚠️ ไม่สมดุล</div>
        <div style="color:var(--muted);font-size:13px">สินทรัพย์: {{ number_format($totalAssets,2) }} | หนี้สิน+ทุน: {{ number_format($totalLiabilitiesAndEquity,2) }} | ต่าง: {{ number_format(bcsub($totalAssets, $totalLiabilitiesAndEquity, 2),2) }}</div>
      @endif
    </div>
  </div>
</div>
@endsection
