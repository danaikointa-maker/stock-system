@extends('layouts.app')
@section('title', '📒 ' . $journal->doc_no)
@section('crumb', 'บัญชี · รายการบัญชี · ' . $journal->doc_no)

@section('content')
<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <a href="{{ route('accounting.journals') }}" class="btn">⬅️ กลับ</a>
  @if($journal->status === 'draft')
    <form method="POST" action="{{ route('accounting.journals.post', $journal) }}" style="display:inline">
      @csrf @method('PATCH')
      <button class="btn btn-ok" onclick="return confirm('✅ โพสต์รายการบัญชี? จะลงบัญชีแยกประเภททันที')">✅ โพสต์</button>
    </form>
  @endif
  @if($journal->status === 'posted')
    <form method="POST" action="{{ route('accounting.journals.reverse', $journal) }}" style="display:inline">
      @csrf @method('PATCH')
      <button class="btn" style="background:#fef2f2;color:#b91c1c" onclick="return confirm('🔄 สร้างรายการกลับทาง?')">🔄 กลับรายการ</button>
    </form>
  @endif
</div>

<div class="card"><div style="padding:24px">
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px">
    <div><div style="font-size:11px;color:var(--muted)">เลขที่</div><b>{{ $journal->doc_no }}</b></div>
    <div><div style="font-size:11px;color:var(--muted)">วันที่</div><b>{{ $journal->entry_date->format('d/m/Y') }}</b></div>
    <div><div style="font-size:11px;color:var(--muted)">สถานะ</div>
      <span class="badge {{ $journal->status === 'posted' ? 'ok' : ($journal->status === 'draft' ? 'warn' : 'bad') }}">
        {{ $journal->status === 'posted' ? '✅ โพสต์แล้ว' : ($journal->status === 'draft' ? '📝 ร่าง' : '🔄 กลับรายการ') }}
      </span>
    </div>
  </div>
  <div style="margin-bottom:16px"><b>รายละเอียด:</b> {{ $journal->description }}</div>

  <table>
    <thead><tr><th>บัญชี</th><th>ประเภทย่อย</th><th class="num">Debit</th><th class="num">Credit</th><th>คำอธิบาย</th></tr></thead>
    <tbody>
    @foreach($journal->lines as $line)
      <tr>
        <td><b>{{ $line->account->code }}</b> {{ $line->account->name }}</td>
        <td>{{ $line->account->sub_type ?? '-' }}</td>
        <td class="num" style="{{ $line->debit > 0 ? 'color:var(--ok-dark);font-weight:700' : '' }}">{{ $line->debit > 0 ? number_format($line->debit,2) : '-' }}</td>
        <td class="num" style="{{ $line->credit > 0 ? 'color:var(--bad-dark);font-weight:700' : '' }}">{{ $line->credit > 0 ? number_format($line->credit,2) : '-' }}</td>
        <td>{{ $line->description ?? '-' }}</td>
      </tr>
    @endforeach
    </tbody>
    <tfoot>
      <tr style="background:#f0fdf4">
        <td colspan="2" style="text-align:right"><b>รวม</b></td>
        <td class="num"><b>{{ number_format($journal->lines->sum('debit'),2) }}</b></td>
        <td class="num"><b>{{ number_format($journal->lines->sum('credit'),2) }}</b></td>
        <td>
          @if($journal->isBalanced())<span style="color:var(--ok-dark)">✅ สมดุล</span>
          @else <span style="color:var(--bad-dark)">❌ ไม่สมดุล</span>@endif
        </td>
      </tr>
    </tfoot>
  </table>

  @if($journal->reversedBy)
  <div style="margin-top:12px;padding:10px;background:#eff6ff;border-radius:8px;font-size:13px">
    🔄 ถูกกลับรายการโดย: <a href="{{ route('accounting.journals.show', $journal->reversedBy) }}"><code>{{ $journal->reversedBy->doc_no }}</code></a>
  </div>
  @endif
</div></div>
@endsection
