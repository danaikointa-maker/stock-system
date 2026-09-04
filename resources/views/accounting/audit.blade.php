@extends('layouts.app')
@section('title', '🔍 Audit — ตรวจสอบยอดตรง')
@section('crumb', 'บัญชี · Audit (ตรวจสอบยอดตรง)')

@section('content')
<div class="card">
  <div class="body">
    <form method="GET" class="filters">
      <div class="field">
        <label>สาขา</label>
        <select name="node_id">
          <option value="">ทั้งหมด</option>
          @foreach($nodes as $n)
            <option value="{{ $n->id }}" {{ $nodeId == $n->id ? 'selected' : '' }}>{{ $n->name }}</option>
          @endforeach
        </select>
      </div>
      <button class="btn btn-p">🔍 ตรวจสอบ</button>
    </form>
  </div>
</div>

{{-- Stock Balance Verification --}}
<div class="card" style="margin-top:16px">
  <div class="section-bar {{ $stockResult['ok'] ? 'ok' : 'bad' }}">
    📦 ตรวจสอบ Stock Ledger vs StockBalance
  </div>
  @if($stockResult['ok'])
    <div style="padding:24px;text-align:center">
      <div style="font-size:48px">✅</div>
      <div style="font-size:16px;font-weight:800;color:var(--ok-dark)">ยอดตรงทั้งหมด!</div>
      <div style="color:var(--muted);font-size:13px;margin-top:4px">Stock Ledger สมดุลกับ StockBalance ทุกรายการ</div>
    </div>
  @else
    <div style="padding:16px">
      <div style="color:var(--bad-dark);font-weight:800;font-size:16px;margin-bottom:12px">⚠️ พบยอดไม่ตรง {{ count($stockResult['mismatches']) }} รายการ</div>
      <table>
        <thead><tr><th>สินค้า</th><th>สาขา</th><th class="num">StockBalance</th><th class="num">Ledger</th><th class="num">ผลต่าง</th></tr></thead>
        <tbody>
        @foreach($stockResult['mismatches'] as $m)
          <tr>
            <td>{{ \App\Models\Product::find($m['product_id'])?->name ?? $m['product_id'] }}</td>
            <td>{{ \App\Models\OrgNode::find($m['node_id'])?->name ?? $m['node_id'] }}</td>
            <td class="num">{{ number_format($m['balance_qty']) }}</td>
            <td class="num">{{ number_format($m['ledger_qty']) }}</td>
            <td class="num"><b style="color:var(--bad-dark)">{{ $m['diff'] > 0 ? '+' : '' }}{{ number_format($m['diff']) }}</b></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

{{-- Journal Entry Verification --}}
<div class="card" style="margin-top:16px">
  <div class="section-bar {{ $journalResult['ok'] ? 'ok' : 'bad' }}">
    📒 ตรวจสอบ Journal Entries (Dr = Cr)
  </div>
  @if($journalResult['ok'])
    <div style="padding:24px;text-align:center">
      <div style="font-size:48px">✅</div>
      <div style="font-size:16px;font-weight:800;color:var(--ok-dark)">สมดุลทั้งหมด!</div>
      <div style="color:var(--muted);font-size:13px;margin-top:4px">ทุก Journal Entry มี Debit = Credit ครบถ้วน</div>
    </div>
  @else
    <div style="padding:16px">
      <div style="color:var(--bad-dark);font-weight:800;font-size:16px;margin-bottom:12px">⚠️ พบ Journal ไม่สมดุล {{ count($journalResult['mismatches']) }} รายการ</div>
      <table>
        <thead><tr><th>Reference</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">ผลต่าง</th></tr></thead>
        <tbody>
        @foreach($journalResult['mismatches'] as $m)
          <tr>
            <td><code>{{ $m['reference'] }}</code></td>
            <td class="num">{{ number_format($m['total_debit'], 2) }}</td>
            <td class="num">{{ number_format($m['total_credit'], 2) }}</td>
            <td class="num"><b style="color:var(--bad-dark)">{{ number_format($m['diff'], 2) }}</b></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<div style="margin-top:16px">
  <a href="{{ route('accounting.stock-ledger') }}" class="btn btn-blue">📋 ดู Stock Ledger</a>
  <a href="{{ route('accounting.dashboard') }}" class="btn">⬅️ กลับ Dashboard</a>
</div>
@endsection
