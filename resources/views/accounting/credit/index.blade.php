@extends('layouts.app')
@section('title', '↩️ ใบลดหนี้ / คืนสินค้า')
@section('crumb', 'บัญชี · ใบลดหนี้')

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('accounting.credit.create') }}" class="btn btn-p">➕ สร้างใบลดหนี้</a>
  <a href="{{ route('accounting.dashboard') }}" class="btn">⬅️ กลับ Dashboard</a>
</div>

<div class="card">
  <table>
    <thead><tr>
      <th>เลขที่</th>
      <th>ประเภท</th>
      <th>ลูกค้า</th>
      <th>เหตุผล</th>
      <th class="num">ยอดรวม</th>
      <th>สถานะ</th>
      <th>บัญชี</th>
      <th></th>
    </tr></thead>
    <tbody>
    @forelse($notes as $n)
      <tr>
        <td><code>{{ $n->doc_no }}</code></td>
        <td>{{ $n->type_label }}</td>
        <td>{{ $n->customer_name }}</td>
        <td>{{ Str::limit($n->reason, 30) }}</td>
        <td class="num"><b style="color:var(--bad-dark)">{{ number_format($n->total_amount, 2) }}</b></td>
        <td>
          <span class="badge {{ $n->status === 'confirmed' ? 'ok' : ($n->status === 'draft' ? 'warn' : 'bad') }}">
            {{ $n->status === 'confirmed' ? '✅ ยืนยัน' : ($n->status === 'draft' ? '📝 ร่าง' : '❌ ยกเลิก') }}
          </span>
        </td>
        <td>
          @if($n->posted_to_accounting)
            <span class="badge ok">✅ บันทึกลงบัญชีแล้ว</span>
          @else
            <span class="badge warn">⏳ ยังไม่บันทึก</span>
          @endif
        </td>
        <td><a href="{{ route('accounting.credit.show', $n) }}" class="btn btn-sm btn-view">👁️</a></td>
      </tr>
    @empty
      <tr><td colspan="8" class="empty">ไม่พบใบลดหนี้</td></tr>
    @endforelse
    </tbody>
  </table>
  @if($notes->hasPages()) <div class="pager">{{ $notes->links() }}</div> @endif
</div>
@endsection
