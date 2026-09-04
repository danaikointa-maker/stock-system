@extends('layouts.app')
@section('title', '📋 ' . $wht->wht_no)
@section('crumb', 'บัญชี · ใบหัก ณ ที่จ่าย · ' . $wht->wht_no)

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('accounting.withholding-taxes') }}" class="btn">⬅️ กลับ</a>
  <button class="btn btn-blue" onclick="window.print()">🖨️ พิมพ์</button>
</div>
<div class="card">
  <div style="padding:24px">
    <div style="text-align:center;margin-bottom:24px;border-bottom:3px double var(--line);padding-bottom:16px">
      <div style="font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);font-weight:700">หนังสือรับรองการหักภาษี ณ ที่จ่าย</div>
      <div style="font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)">WITHHOLDING TAX CERTIFICATE</div>
      <h2 style="margin-top:8px">{{ $wht->wht_no }}</h2>
      <div style="font-size:13px;color:var(--muted)">วันที่: {{ $wht->issue_date->format('d/m/Y') }}</div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
      <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
        <div style="font-size:11px;color:var(--muted);font-weight:700">ผู้จ่ายเงิน (ผู้ออกหนังสือ)</div>
        <div style="font-size:14px;font-weight:700;margin-top:4px">{{ $wht->node->name ?? '' }}</div>
      </div>
      <div style="background:#f8fafc;border-radius:10px;padding:14px 18px">
        <div style="font-size:11px;color:var(--muted);font-weight:700">ผู้รับเงิน (ผู้ถูกหัก)</div>
        <div style="font-size:14px;font-weight:700;margin-top:4px">{{ $wht->payee_name }}</div>
        @if($wht->payee_tax_id)<div style="font-size:12px;color:var(--muted)">เลขผู้เสียภาษี: {{ $wht->payee_tax_id }}</div>@endif
      </div>
    </div>

    <table style="width:100%;max-width:500px;margin:0 auto">
      <tr><td style="padding:10px 16px 10px 0;color:var(--muted)">ประเภทเงินได้</td><td style="font-weight:700;font-size:14px">{{ $wht->income_type ?? 'บริการ' }}</td></tr>
      <tr><td style="padding:10px 16px 10px 0;color:var(--muted)">เงื่อนไข</td><td style="font-weight:700">{{ $wht->condition }}</td></tr>
      <tr style="border-top:2px solid var(--line)"><td style="padding:10px 16px 10px 0">จำนวนเงินก่อนหัก</td><td style="font-weight:700;font-size:16px">{{ number_format($wht->income_amount, 2) }}</td></tr>
      <tr><td style="padding:10px 16px 10px 0">อัตราหัก</td><td style="font-weight:700">{{ $wht->wht_rate }}%</td></tr>
      <tr style="background:#fef2f2"><td style="padding:10px 16px 10px 0;color:#b91c1c"><b>จำนวนเงินที่หัก</b></td><td style="font-weight:800;font-size:18px;color:#dc2626">{{ number_format($wht->wht_amount, 2) }}</td></tr>
      <tr style="background:#f0fdf4"><td style="padding:10px 16px 10px 0;color:#15803d"><b>จ่ายสุทธิ</b></td><td style="font-weight:800;font-size:18px;color:#059669">{{ number_format($wht->net_amount, 2) }}</td></tr>
    </table>

    <div style="text-align:center;margin-top:30px;font-size:11px;color:var(--muted)">
      หนังสือรับรองนี้ ออกให้ ณ วันที่ {{ $wht->issue_date->format('j F Y') }}
    </div>
  </div>
</div>
@endsection
