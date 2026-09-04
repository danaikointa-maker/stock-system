@extends('layouts.app')
@section('title', 'ใบโอน ' . $transfer->doc_no)
@section('crumb', $transfer->fromNode->name . ' → ' . $transfer->toNode->name)

@section('content')

@php
  $statusMeta = [
    'draft' => ['ร่าง','b-gray'], 'pending_approve' => ['รออนุมัติ','b-amber'],
    'approved' => ['อนุมัติแล้ว (จองของไว้)','b-blue'], 'rejected' => ['ปฏิเสธ','b-red'],
    'shipped' => ['ส่งแล้ว (ระหว่างทาง)','b-amber'], 'received' => ['รับของแล้ว','b-green'],
    'cancelled' => ['ยกเลิก','b-red'],
  ];
  $meta = $statusMeta[$transfer->status->value] ?? [$transfer->status->value,'b-gray'];
  $steps = ['pending_approve'=>'รออนุมัติ','approved'=>'อนุมัติ','shipped'=>'ส่งของ','received'=>'รับของ'];
  $order = array_keys($steps);
  $cur = array_search($transfer->status->value, $order, true);
@endphp

@if($errors->has('transfer'))
  <div class="alert a-bad">{{ $errors->first('transfer') }}</div>
@endif

{{-- แถบสถานะ --}}
<div class="card">
  <div class="body">
    <div style="display:flex;align-items:center;gap:0;flex-wrap:wrap">
      @foreach($steps as $key => $label)
        @php
          $idx = array_search($key, $order, true);
          $done = $cur !== false && $idx <= $cur;
          $isCancel = in_array($transfer->status->value, ['rejected','cancelled'], true);
        @endphp
        <div style="display:flex;align-items:center">
          <div style="display:flex;flex-direction:column;align-items:center;min-width:104px">
            <div style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;
                        justify-content:center;font-size:13px;font-weight:700;
                        background:{{ $isCancel ? '#fee2e2' : ($done ? 'var(--brand)' : '#eef1f6') }};
                        color:{{ $isCancel ? '#b91c1c' : ($done ? '#fff' : '#94a3b8') }}">
              {{ $done && ! $isCancel ? '✓' : $loop->iteration }}
            </div>
            <div style="font-size:11.5px;margin-top:5px;color:{{ $done ? 'var(--ink)' : 'var(--muted)' }}">
              {{ $label }}
            </div>
          </div>
          @unless($loop->last)
            <div style="width:44px;height:2px;margin-bottom:18px;
                        background:{{ $cur !== false && $idx < $cur ? 'var(--brand)' : '#e3e8f0' }}"></div>
          @endunless
        </div>
      @endforeach

      <span style="flex:1"></span>
      <span class="badge {{ $meta[1] }}" style="font-size:12.5px;padding:5px 12px">{{ $meta[0] }}</span>
    </div>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <h3>ข้อมูลเอกสาร</h3>
    <div class="body">
      <table>
        <tbody>
          <tr><th style="width:120px">เลขที่</th><td><code>{{ $transfer->doc_no }}</code></td></tr>
          <tr><th>ต้นทาง</th><td>{{ $transfer->fromNode->name }} <code>{{ $transfer->fromNode->code }}</code></td></tr>
          <tr><th>ปลายทาง</th><td>{{ $transfer->toNode->name }} <code>{{ $transfer->toNode->code }}</code></td></tr>
          <tr><th>จำนวนรวม</th><td class="num">{{ number_format($transfer->total_qty) }} ชิ้น</td></tr>
          <tr><th>มูลค่า</th><td class="num">{{ number_format($transfer->total_amount, 2) }} บาท</td></tr>
          @if($transfer->note)<tr><th>หมายเหตุ</th><td>{{ $transfer->note }}</td></tr>@endif
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h3>ประวัติการดำเนินการ</h3>
    <div class="body">
      <table>
        <tbody>
          <tr><th style="width:120px">สร้างเมื่อ</th><td>{{ $transfer->created_at?->format('d/m/Y H:i') }}</td></tr>
          <tr><th>อนุมัติเมื่อ</th><td>{{ $transfer->approved_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
          <tr><th>ส่งของเมื่อ</th><td>{{ $transfer->shipped_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
          <tr><th>รับของเมื่อ</th><td>{{ $transfer->received_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- การดำเนินการ --}}
@canany(['approve','ship','receive','cancel'], $transfer)
<div class="card" style="border-color:#bfd2fa">
  <h3 style="color:var(--brand)">การดำเนินการ</h3>
  <div class="body">

    @can('approve', $transfer)
      <div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--line)">
        <div style="font-size:12.5px;color:var(--muted);margin-bottom:9px">
          อนุมัติแล้วระบบจะ<b>จองสินค้า</b>ที่ต้นทางทันที (ยอดคงเหลือยังไม่ลด แต่จะขายออกไม่ได้)
        </div>
        <div style="display:flex;gap:9px;flex-wrap:wrap">
          <form method="POST" action="{{ route('transfers.approve', $transfer) }}">
            @csrf @method('PATCH')
            <button class="btn btn-approve">✅ อนุมัติใบโอน</button>
          </form>
          <form method="POST" action="{{ route('transfers.reject', $transfer) }}"
                style="display:flex;gap:7px;flex:1;min-width:280px">
            @csrf @method('PATCH')
            <input type="text" name="reason" placeholder="เหตุผลที่ปฏิเสธ" style="flex:1">
            <button class="btn btn-d">❌ ปฏิเสธ</button>
          </form>
        </div>
      </div>
    @endcan

    @can('ship', $transfer)
      <form method="POST" action="{{ route('transfers.ship', $transfer) }}">
        @csrf @method('PATCH')
        <div style="font-size:12.5px;color:var(--muted);margin-bottom:9px">
          ระบุจำนวนที่ส่งจริง (ถ้าส่งไม่ครบ ส่วนที่เหลือจะถูกปลดการจองคืนเข้าคลัง)
        </div>
        <table style="margin-bottom:11px">
          <thead><tr><th>สินค้า</th><th>ล็อต</th><th class="num">ขอเบิก</th><th style="width:110px" class="num">ส่งจริง</th></tr></thead>
          <tbody>
          @foreach($transfer->items as $it)
            <tr>
              <td>{{ $it->product->name }}</td>
              <td style="font-size:12px">{{ $it->lot?->lot_no ?? '—' }}</td>
              <td class="num">{{ number_format($it->qty_requested) }}</td>
              <td class="num">
                <input type="number" name="qty[{{ $it->id }}]" min="0" max="{{ $it->qty_requested }}"
                       value="{{ $it->qty_requested }}" style="width:92px;text-align:right;padding:5px 8px">
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
        <button class="btn btn-ship">📤 ยืนยันส่งสินค้า</button>
      </form>
    @endcan

    @can('receive', $transfer)
      <form method="POST" action="{{ route('transfers.receive', $transfer) }}">
        @csrf @method('PATCH')
        <div style="font-size:12.5px;color:var(--muted);margin-bottom:9px">
          ตรวจนับแล้วระบุจำนวนที่ได้รับจริง — ส่วนที่ขาดจะถูกบันทึกเป็น<b>ของเสียหาย/สูญหายระหว่างขนส่ง</b>
        </div>
        <table style="margin-bottom:11px">
          <thead><tr><th>สินค้า</th><th>ล็อต</th><th class="num">ส่งมา</th><th style="width:110px" class="num">รับจริง</th></tr></thead>
          <tbody>
          @foreach($transfer->items as $it)
            <tr>
              <td>{{ $it->product->name }}</td>
              <td style="font-size:12px">{{ $it->lot?->lot_no ?? '—' }}</td>
              <td class="num">{{ number_format($it->qty_shipped) }}</td>
              <td class="num">
                <input type="number" name="qty[{{ $it->id }}]" min="0" max="{{ $it->qty_shipped }}"
                       value="{{ $it->qty_shipped }}" style="width:92px;text-align:right;padding:5px 8px">
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
        <button class="btn btn-receive">📥 ยืนยันรับสินค้าเข้าคลัง</button>
      </form>
    @endcan

    @can('cancel', $transfer)
      <form method="POST" action="{{ route('transfers.cancel', $transfer) }}"
            style="margin-top:13px;padding-top:13px;border-top:1px solid var(--line)"
            onsubmit="return confirm('ยกเลิกใบโอนนี้? สินค้าที่จองไว้จะถูกปลดคืน')">
        @csrf @method('PATCH')
        <button class="btn btn-d btn-sm">🚫 ยกเลิกใบโอนนี้</button>
      </form>
    @endcan

  </div>
</div>
@endcanany

<div class="card">
  <h3>รายการสินค้า</h3>
  <table>
    <thead>
      <tr><th>SKU</th><th>สินค้า</th><th>ล็อต</th>
          <th class="num">ขอเบิก</th><th class="num">ส่งจริง</th><th class="num">รับจริง</th>
          <th class="num">ราคา/หน่วย</th><th class="num">รวม</th></tr>
    </thead>
    <tbody>
    @foreach($transfer->items as $it)
      <tr>
        <td><code>{{ $it->product->sku }}</code></td>
        <td>{{ $it->product->name }}</td>
        <td style="font-size:12px">{{ $it->lot?->lot_no ?? '—' }}</td>
        <td class="num">{{ number_format($it->qty_requested) }}</td>
        <td class="num">{{ $it->qty_shipped ? number_format($it->qty_shipped) : '—' }}</td>
        <td class="num">
          @if($it->qty_received)
            <b>{{ number_format($it->qty_received) }}</b>
            @if($it->qty_shipped > $it->qty_received)
              <span class="badge b-red" style="margin-left:4px">ขาด {{ $it->qty_shipped - $it->qty_received }}</span>
            @endif
          @else — @endif
        </td>
        <td class="num">{{ number_format($it->unit_price, 2) }}</td>
        <td class="num"><b>{{ number_format($it->qty_requested * $it->unit_price, 2) }}</b></td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>

<a href="{{ route('transfers.index') }}" class="btn">⬅️ กลับรายการใบโอน</a>

@endsection
