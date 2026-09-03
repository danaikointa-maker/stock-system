@extends('layouts.app')
@section('title', 'ใบเสร็จการแลกแต้ม')

@push('head')
<style>
  .slip{
    max-width:420px;margin:0 auto;background:#fff;border:2px dashed var(--line);
    border-radius:16px;padding:24px;
  }
  .slip .top{text-align:center;border-bottom:2px dashed var(--line);padding-bottom:16px;margin-bottom:16px}
  .slip .top .ok{
    width:64px;height:64px;margin:0 auto 10px;border-radius:50%;
    background:#E8F5E9;color:#12A150;display:grid;place-items:center;font-size:30px;
  }
  .slip .top h2{margin:0 0 4px;font-size:19px}
  .slip .top .code{font-size:12px;color:var(--muted);font-family:monospace}
  .line{display:flex;justify-content:space-between;padding:9px 0;font-size:13.5px}
  .line span:first-child{color:var(--muted)}
  .line span:last-child{font-weight:700;text-align:right}
  .big{
    text-align:center;padding:16px 0;margin:12px 0;
    border-top:2px dashed var(--line);border-bottom:2px dashed var(--line);
  }
  .big .n{font-size:36px;font-weight:800;color:var(--brand);line-height:1.15}
  .big .l{font-size:12px;color:var(--muted)}
  .items{margin-top:12px;font-size:12.5px}
  .items .it{
    display:flex;justify-content:space-between;padding:7px 0;
    border-bottom:1px dotted var(--line);
  }
  .items .lot{font-size:11px;color:var(--muted)}
  @media print{
    .noprint{display:none}
    .slip{border:none;box-shadow:none}
  }
</style>
@endpush

@section('content')
<div class="noprint" style="margin-bottom:16px;display:flex;gap:9px;justify-content:center">
  <button class="btn btn-p" onclick="window.print()">พิมพ์ใบเสร็จ</button>
  <a href="{{ route('redeem.desk') }}" class="btn">รับแลกรายการถัดไป</a>
</div>

<div class="slip">
  <div class="top">
    <div class="ok">✓</div>
    <h2>แลกแต้มสำเร็จ</h2>
    <div class="code">{{ $r->code }}</div>
  </div>

  <div class="line"><span>ร้าน</span><span>{{ $shop->name }}</span></div>
  <div class="line"><span>ลูกค้า</span><span>{{ $customer->name ?? '—' }}<br>
    <small style="font-weight:400;color:var(--muted)">{{ $customer->phone ?? '' }}</small></span></div>
  <div class="line"><span>รายการ</span><span>{{ $r->reward_name }}</span></div>
  <div class="line"><span>ประเภท</span><span>
    @switch($r->redeem_type)
      @case('goods') สินค้า @break
      @case('service') บริการ @break
      @case('discount') ส่วนลด @break
      @case('cash') เงินสด @break
    @endswitch
  </span></div>

  <div class="big">
    <div class="n">-{{ number_format($r->points_used) }}</div>
    <div class="l">แต้มที่ใช้ · มูลค่า {{ number_format($r->cash_value, 2) }} บาท</div>
  </div>

  @if($items->isNotEmpty())
    <div class="items">
      <b style="font-size:13px">สินค้าที่จ่ายออก</b>
      @foreach($items as $it)
        <div class="it">
          <div>
            {{ $it->name_snapshot }}
            <div class="lot">
              {{ $it->sku_snapshot }}
              @if($it->lot_no_snapshot) · ล็อต {{ $it->lot_no_snapshot }} @endif
              @if($it->expiry_snapshot) · หมดอายุ {{ \Carbon\Carbon::parse($it->expiry_snapshot)->format('j M y') }} @endif
            </div>
          </div>
          <div style="font-weight:700;white-space:nowrap">x{{ $it->qty }}</div>
        </div>
      @endforeach
    </div>
  @endif

  <div class="line" style="margin-top:12px;border-top:2px dashed var(--line);padding-top:12px">
    <span>เวลา</span><span>{{ \Carbon\Carbon::parse($r->redeemed_at)->format('j M Y · H:i') }}</span>
  </div>

  @if($allowance)
    <div class="line">
      <span>วงเงินร้านคงเหลือ</span>
      <span>{{ number_format($allowance->remaining_points) }} แต้ม</span>
    </div>
  @endif

  <p style="text-align:center;font-size:11px;color:#9E9E9E;margin:16px 0 0;line-height:1.7">
    RoaMembers · เก็บใบเสร็จนี้ไว้เป็นหลักฐาน<br>
    ร้านค้าใช้ยอดนี้ยื่นเบิกเงินคืนจากเจ้าของระบบ
  </p>
</div>
@endsection
