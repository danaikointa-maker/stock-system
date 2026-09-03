@extends('layouts.app')
@section('title', $product->name)
@section('crumb', 'จัดการสินค้า')

@section('content')

@php
  $qrLabel = ['created'=>'ออกแล้วรอส่ง','in_stock'=>'ในคลัง','sold'=>'ขายแล้ว','redeemed'=>'ใช้รับคะแนนแล้ว','void'=>'ยกเลิก'];
  $qrCls   = ['created'=>'b-gray','in_stock'=>'b-blue','sold'=>'b-amber','redeemed'=>'b-green','void'=>'b-gray'];
@endphp

<div class="card">
  <h3>
    {{ $product->name }} <code>{{ $product->sku }}</code>
    <a href="{{ route('products.edit', $product) }}" class="btn btn-sm">แก้ไข</a>
  </h3>
  <div class="body">
    <div class="grid g4">
      <div class="kpi"><div class="lbl">ราคาทุน</div><div class="val">{{ number_format($product->cost_price, 2) }}</div></div>
      <div class="kpi"><div class="lbl">ราคาขายปลีก</div><div class="val">{{ number_format($product->retail_price, 2) }}</div></div>
      <div class="kpi"><div class="lbl">คะแนนต่อชิ้น</div><div class="val">{{ $product->points_per_unit }}</div></div>
      <div class="kpi"><div class="lbl">คงเหลือรวม</div><div class="val">{{ number_format($stock->sum('qty_on_hand')) }}</div></div>
    </div>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <h3>เปิดล็อตการผลิตใหม่</h3>
    <div class="body">
      <form method="POST" action="{{ route('products.lots.store', $product) }}">
        @csrf
        <div class="field">
          <label for="lot_no">เลขล็อต *</label>
          <input type="text" id="lot_no" name="lot_no" value="{{ old('lot_no') }}"
                 placeholder="เช่น L2601" required>
          @error('lot_no')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="field">
          <label for="qty_produced">จำนวนที่ผลิต *</label>
          <input type="number" min="1" id="qty_produced" name="qty_produced"
                 value="{{ old('qty_produced') }}" required>
          @error('qty_produced')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="field">
          <label for="mfg_date">วันที่ผลิต</label>
          <input type="date" id="mfg_date" name="mfg_date" value="{{ old('mfg_date') }}">
        </div>
        <div class="field">
          <label for="expiry_date">วันหมดอายุ</label>
          <input type="date" id="expiry_date" name="expiry_date" value="{{ old('expiry_date') }}">
          @error('expiry_date')<div class="err">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-p">เปิดล็อต</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h3>สต๊อกคงเหลือแยกตามหน่วยงาน</h3>
    <table>
      <thead><tr><th>หน่วยงาน</th><th>ล็อต</th><th class="num">คงเหลือ</th><th class="num">จอง</th></tr></thead>
      <tbody>
        @forelse($stock as $s)
          <tr>
            <td>{{ $s->node?->name ?? '—' }} <code>{{ $s->node?->code }}</code></td>
            <td>{{ $s->lot_id ? ($s->lot?->lot_no ?? $s->lot_id) : '—' }}</td>
            <td class="num"><b>{{ number_format($s->qty_on_hand) }}</b></td>
            <td class="num">{{ number_format($s->qty_reserved) }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="empty">ยังไม่มีสต๊อกในระบบ</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3>ล็อตการผลิตและ QR code</h3>
  <table>
    <thead>
      <tr>
        <th>ล็อต</th><th>ผลิต</th><th>หมดอายุ</th>
        <th class="num">จำนวนผลิต</th><th class="num">ออก QR แล้ว</th>
        <th>สถานะ QR</th><th>ออก QR เพิ่ม</th>
      </tr>
    </thead>
    <tbody>
      @forelse($product->lots as $lot)
        @php
          $stats = $qrStats[$lot->id] ?? collect();
          $issued = $stats->sum('c');
          $left = max(0, $lot->qty_produced - $issued);
        @endphp
        <tr>
          <td>
            <code>{{ $lot->lot_no }}</code>
            @if($lot->isExpired())<span class="badge b-red">หมดอายุ</span>@endif
          </td>
          <td>{{ $lot->mfg_date?->format('d/m/y') ?? '—' }}</td>
          <td>{{ $lot->expiry_date?->format('d/m/y') ?? '—' }}</td>
          <td class="num">{{ number_format($lot->qty_produced) }}</td>
          <td class="num">
            {{ number_format($issued) }}
            @if($issued > 0)
              <div style="font-size:11px">
                <a href="{{ route('products.lots.csv', [$product, $lot]) }}">ไฟล์พิมพ์ QR</a>
              </div>
            @endif
          </td>
          <td>
            @forelse($stats as $s)
              @php $st = $s->status instanceof \App\Enums\QrStatus ? $s->status->value : $s->status; @endphp
              <span class="badge {{ $qrCls[$st] ?? 'b-gray' }}">
                {{ $qrLabel[$st] ?? $st }} {{ $s->c }}
              </span>
            @empty
              <span style="color:var(--muted)">ยังไม่ได้ออก QR</span>
            @endforelse
          </td>
          <td>
            @if($left > 0)
              <form method="POST" action="{{ route('products.lots.qr', [$product, $lot]) }}"
                    style="display:flex;gap:6px;align-items:center">
                @csrf
                <input type="number" name="qty" min="1" max="{{ $left }}" value="{{ $left }}"
                       style="width:90px" required>
                <button type="submit" class="btn btn-p btn-sm">ออก QR</button>
              </form>
              <div style="font-size:11px;color:var(--muted);margin-top:3px">ออกได้อีก {{ number_format($left) }} ใบ</div>
            @else
              <span style="color:var(--muted)">ออกครบแล้ว</span>
            @endif
            @error('qty')<div class="err">{{ $message }}</div>@enderror
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="empty">ยังไม่มีล็อตการผลิต — เปิดล็อตใหม่ที่ฟอร์มด้านบน</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<a href="{{ route('products.index') }}" class="btn">← กลับรายการสินค้า</a>

@endsection
