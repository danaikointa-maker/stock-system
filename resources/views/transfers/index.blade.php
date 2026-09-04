@extends('layouts.app')
@section('title', 'ใบโอนสินค้า')
@section('crumb', 'จัดการการโอนสินค้าระหว่างหน่วยงาน')

@section('content')

@php
  $statusMeta = [
    'draft' => ['ร่าง','b-gray'], 'pending_approve' => ['รออนุมัติ','b-amber'],
    'approved' => ['อนุมัติแล้ว','b-blue'], 'rejected' => ['ปฏิเสธ','b-red'],
    'shipped' => ['ส่งแล้ว (ระหว่างทาง)','b-amber'], 'received' => ['รับของแล้ว','b-green'],
    'cancelled' => ['ยกเลิก','b-red'],
  ];
  $tab = request('tab');
@endphp

<div class="card">
  <h3>
    ใบโอนสินค้า
    @can('create', App\Models\Transfer::class)
      <a href="{{ route('transfers.create') }}" class="btn btn-p btn-sm">📋 สร้างใบโอน</a>
    @endcan
  </h3>

  <div class="body">
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
      <a href="{{ route('transfers.index') }}"
         class="btn btn-sm {{ ! $tab ? 'btn-p' : '' }}">📋 ทั้งหมด</a>
      <a href="{{ route('transfers.index', ['tab' => 'approve']) }}"
         class="btn btn-sm {{ $tab === 'approve' ? 'btn-p' : '' }}">
        รอฉันอนุมัติ
        @if($countApprove)<span class="badge b-amber" style="margin-left:4px">{{ $countApprove }}</span>@endif
      </a>
      <a href="{{ route('transfers.index', ['tab' => 'receive']) }}"
         class="btn btn-sm {{ $tab === 'receive' ? 'btn-p' : '' }}">
        รอฉันรับของ
        @if($countReceive)<span class="badge b-amber" style="margin-left:4px">{{ $countReceive }}</span>@endif
      </a>
    </div>

    <form method="GET" class="filters">
      @if($tab)<input type="hidden" name="tab" value="{{ $tab }}">@endif
      <div class="field"><label>เลขที่เอกสาร</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="TRF-..."></div>
      @unless($tab)
        <div class="field"><label>สถานะ</label>
          <select name="status">
            <option value="">ทั้งหมด</option>
            @foreach($statuses as $s)
              <option value="{{ $s->value }}" @selected(request('status') === $s->value)>
                {{ $statusMeta[$s->value][0] ?? $s->value }}
              </option>
            @endforeach
          </select>
        </div>
      @endunless
      <button class="btn btn-p">🔍 ค้นหา</button>
    </form>
  </div>
</div>

<div class="card">
  <h3>รายการ ({{ number_format($transfers->total()) }} ใบ)</h3>

  @if($transfers->isEmpty())
    <div class="empty">
      @if($tab === 'approve') ไม่มีใบโอนรออนุมัติ
      @elseif($tab === 'receive') ไม่มีสินค้าระหว่างทางรอรับ
      @else ยังไม่มีใบโอนสินค้า @endif
    </div>
  @else
    <table>
      <thead>
        <tr><th>เลขที่</th><th>ต้นทาง</th><th>ปลายทาง</th>
            <th class="num">จำนวน</th><th class="num">มูลค่า</th>
            <th>สถานะ</th><th>อัปเดต</th><th></th></tr>
      </thead>
      <tbody>
      @foreach($transfers as $t)
        @php $meta = $statusMeta[$t->status->value] ?? [$t->status->value, 'b-gray']; @endphp
        <tr>
          <td><code>{{ $t->doc_no }}</code></td>
          <td>{{ $t->fromNode->name }}<div style="font-size:11px"><code>{{ $t->fromNode->code }}</code></div></td>
          <td>{{ $t->toNode->name }}<div style="font-size:11px"><code>{{ $t->toNode->code }}</code></div></td>
          <td class="num">{{ number_format($t->total_qty) }}</td>
          <td class="num">{{ number_format($t->total_amount, 2) }}</td>
          <td><span class="badge {{ $meta[1] }}">{{ $meta[0] }}</span></td>
          <td style="font-size:12px;color:var(--muted);white-space:nowrap">
            {{ $t->updated_at?->format('d/m/y H:i') }}
          </td>
          <td class="num"><a href="{{ route('transfers.show', $t) }}" class="btn btn-sm btn-view">👁️ จัดการ</a></td>
        </tr>
      @endforeach
      </tbody>
    </table>
    <div class="pager">{{ $transfers->links('partials.pagination') }}</div>
  @endif
</div>

@endsection
