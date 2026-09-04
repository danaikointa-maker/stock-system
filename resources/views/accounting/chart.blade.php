@extends('layouts.app')
@section('title', '📒 ผังบัญชี')
@section('crumb', 'บัญชี · ผังบัญชี')

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('accounting.dashboard') }}" class="btn">⬅️ กลับ Dashboard</a>
</div>

@php
  $categories = [
    'asset' => ['label'=>'สินทรัพย์','icon'=>'🏦','color'=>'#10b981'],
    'liability' => ['label'=>'หนี้สิน','icon'=>'📋','color'=>'#f59e0b'],
    'equity' => ['label'=>'ทุน','icon'=>'💎','color'=>'#8b5cf6'],
    'revenue' => ['label'=>'รายได้','icon'=>'💰','color'=>'#059669'],
    'expense' => ['label'=>'ค่าใช้จ่าย','icon'=>'💸','color'=>'#dc2626'],
  ];
@endphp

@forelse($categories as $cat => $meta)
  @php $items = $accounts->where('category',$cat); @endphp
  @if($items->count())
  <div class="card" style="margin-bottom:16px">
    <div class="section-bar" style="background:{{ $meta['color'] }}15;color:{{ $meta['color'] }};border-bottom:2px solid {{ $meta['color'] }}30">
      {{ $meta['icon'] }} {{ $meta['label'] }} ({{ $items->count() }} รายการ)
    </div>
    <table>
      <thead><tr><th>รหัส</th><th>ชื่อบัญชี</th><th>ประเภทย่อย</th><th class="num">ยอดยกมา</th><th></th></tr></thead>
      <tbody>
      @foreach($items as $a)
        <tr>
          <td><code>{{ $a->code }}</code></td>
          <td><b>{{ $a->name }}</b></td>
          <td>{{ $a->sub_type ?? '-' }}</td>
          <td class="num">{{ number_format($a->opening_balance, 2) }}</td>
          <td class="num">
            <span class="badge {{ $a->is_active ? 'ok' : 'bad' }}">{{ $a->is_active ? '✅ ใช้' : '❌ ปิด' }}</span>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  @endif
@empty
  <div class="card"><div class="empty">📭 ยังไม่มีข้อมูลผังบัญชี — ระบบจะสร้างอัตโนมัติเมื่อรัน migration</div></div>
@endforelse

@endsection
