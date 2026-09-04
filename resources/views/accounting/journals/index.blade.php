@extends('layouts.app')
@section('title', '📒 ลงบัญชีแยก')
@section('crumb', 'บัญชี · รายการบัญชีแยก')

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('accounting.journals.create') }}" class="btn btn-p">➕ ลงบัญชีแยก</a>
  <a href="{{ route('accounting.dashboard') }}" class="btn">⬅️ กลับ Dashboard</a>
</div>

<div class="card">
  <table>
    <thead><tr><th>เลขที่</th><th>วันที่</th><th>รายละเอียด</th><th class="num">Debit</th><th class="num">Credit</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    @forelse($journals as $j)
      <tr>
        <td><code>{{ $j->doc_no }}</code></td>
        <td>{{ $j->entry_date->format('d/m/Y') }}</td>
        <td>{{ Str::limit($j->description, 40) }}</td>
        <td class="num">{{ number_format($j->lines->sum('debit'), 2) }}</td>
        <td class="num">{{ number_format($j->lines->sum('credit'), 2) }}</td>
        <td><span class="badge {{ $j->status === 'posted' ? 'ok' : ($j->status === 'draft' ? 'warn' : 'bad') }}">
          {{ $j->status === 'posted' ? '✅ โพสต์แล้ว' : ($j->status === 'draft' ? '📝 ร่าง' : '🔄 กลับรายการ') }}
        </span></td>
        <td><a href="{{ route('accounting.journals.show', $j) }}" class="btn btn-sm btn-view">👁️</a></td>
      </tr>
    @empty
      <tr><td colspan="7" class="empty">ไม่พบรายการ</td></tr>
    @endforelse
    </tbody>
  </table>
  @if($journals->hasPages()) <div class="pager">{{ $journals->links() }}</div> @endif
</div>
@endsection
