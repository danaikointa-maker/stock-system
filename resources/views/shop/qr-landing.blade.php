@extends('layouts.public')

@section('title', $profile->display_name . ' — แลกของรางวัล')

@section('body')
{{-- ส่วนหัวร้าน --}}
<div class="hero" style="background:
    radial-gradient(circle at 18% 12%, rgba(255,255,255,.20) 0 2px, transparent 3px),
    radial-gradient(circle at 78% 26%, rgba(255,255,255,.16) 0 2px, transparent 3px),
    radial-gradient(ellipse at 50% -10%, {{ $colors['secondary'] }} 0%, {{ $colors['primary'] }} 45%, {{ $colors['primary'] }} 100%);">
    <div class="brandbar">
        @if($profile->logo_path)
            <img src="{{ Storage::url($profile->logo_path) }}" alt="">
        @else
            <div style="width:50px;height:50px;border-radius:14px;background:rgba(255,255,255,.96);display:grid;place-items:center;font-size:26px">🏪</div>
        @endif
        <div>
            <span class="name">{{ $profile->display_name }}</span>
            @if($profile->tagline)
                <span class="sub">{{ $profile->tagline }}</span>
            @endif
        </div>
    </div>
    <div class="headline">
        <h1>สะสมแต้ม<em>แลกของรางวัล</em></h1>
        <p>สแกน QR นี้เพื่อแลกของรางวัลที่ {{ $profile->display_name }}</p>
    </div>
</div>

<div class="sheet">
    <div class="grabber"></div>

    {{-- แสดงผลลัพธ์ --}}
    @if(session('status'))
        <div class="alert a-ok">✅ {{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert a-bad">
            @foreach($errors->all() as $err)
                <div>⚠️ {{ $err }}</div>
            @endforeach
        </div>
    @endif

    {{-- ข้อมูลแต้มของลูกค้า --}}
    @if($customer)
        <div style="background:#FFF9E6;border:1.5px solid #FFE9A8;border-radius:14px;padding:16px;margin-bottom:18px">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <div style="font-size:12px;color:#7A5C00;font-weight:600">แต้มสะสมที่ {{ $profile->display_name }}</div>
                    <div style="font-size:36px;font-weight:800;color:{{ $colors['primary'] }};line-height:1.1">
                        {{ number_format($wallet?->balance ?? 0) }}
                    </div>
                </div>
                <div style="text-align:right;font-size:12px;color:var(--muted)">
                    <div>{{ $customer->name }}</div>
                    <div>{{ $customer->phone }}</div>
                    <a href="{{ route('shop-qr.show', $profile->shop_qr_token) }}"
                       style="color:var(--brand);text-decoration:underline">เปลี่ยนเบอร์</a>
                </div>
            </div>
        </div>
    @endif

    {{-- ฟอร์มกรอกเบอร์ (ถ้ายังไม่กรอก) --}}
    @unless($customer)
        <form action="{{ route('shop-qr.show', $profile->shop_qr_token) }}" method="GET" style="margin-bottom:18px">
            <div class="field">
                <label>📱 กรอกเบอร์โทรเพื่อดูแต้ม</label>
                <div style="display:flex;gap:8px">
                    <input type="tel" name="phone" required pattern="0[0-9]{8,9}"
                           placeholder="08X-XXX-XXXX" inputmode="numeric" class="input"
                           style="flex:1">
                    <button type="submit" class="btn btn-main" style="width:auto;padding:15px 20px;white-space:nowrap">
                        ดูแต้ม
                    </button>
                </div>
            </div>
        </form>
    @endunless

    {{-- รายการของรางวัล --}}
    <h2 style="font-size:17px;font-weight:800;margin:0 0 14px">🎁 ของรางวัลที่แลกได้</h2>

    @forelse($rewards as $reward)
        @php
            $canRedeem = $customer && ($wallet?->balance ?? 0) >= $reward->points_cost;
            $needMore  = $customer ? max(0, $reward->points_cost - ($wallet?->balance ?? 0)) : null;
        @endphp

        <div style="display:flex;gap:12px;align-items:center;padding:13px;border:1.5px solid {{ $canRedeem ? '#A5D6A7' : 'var(--line)' }};border-radius:15px;margin-bottom:10px;{{ $canRedeem ? 'background:#F1F8E9' : '' }}">
            <div style="width:50px;height:50px;border-radius:12px;flex-shrink:0;display:grid;place-items:center;font-size:24px;background:linear-gradient(135deg,#FFF4EE,#FFE0CC)">
                {{ $reward->icon ?: '🎁' }}
            </div>
            <div style="flex:1;min-width:0">
                <b style="display:block;font-size:14px">{{ $reward->name }}</b>
                @if($reward->description)
                    <small style="color:var(--muted);font-size:12px">{{ $reward->description }}</small>
                @endif
                <div style="font-weight:800;color:var(--brand);font-size:14px;margin-top:2px">
                    {{ number_format($reward->points_cost) }} แต้ม
                </div>
            </div>
            <div style="flex-shrink:0">
                @if($canRedeem)
                    <form action="{{ route('shop-qr.redeem', $profile->shop_qr_token) }}" method="POST">
                        @csrf
                        <input type="hidden" name="phone" value="{{ $customer->phone }}">
                        <input type="hidden" name="reward_id" value="{{ $reward->id }}">
                        <button type="submit"
                                onclick="return confirm('ยืนยันแลก {{ $reward->name }} จำนวน {{ $reward->points_cost }} แต้ม?')"
                                class="btn btn-main"
                                style="width:auto;padding:10px 16px;font-size:13px;border-radius:12px">
                            แลกเลย
                        </button>
                    </form>
                @elseif($customer)
                    <span style="font-size:11px;color:var(--muted)">ขาด {{ number_format($needMore) }}</span>
                @else
                    <span style="font-size:11px;color:var(--muted)">กรอกเบอร์<br>เพื่อแลก</span>
                @endif
            </div>
        </div>
    @empty
        <div style="text-align:center;padding:30px 0;color:var(--muted);font-size:13px">
            ยังไม่มีของรางวัลเปิดให้แลก
        </div>
    @endforelse

    {{-- ข้อมูลร้าน --}}
    @if($profile->address || $profile->phone)
        <div style="border-top:1px solid var(--line);margin-top:18px;padding-top:14px;font-size:12px;color:var(--muted);line-height:1.7">
            @if($profile->address)
                <div>📍 {{ $profile->address }}</div>
            @endif
            @if($profile->phone)
                <div>📞 {{ $profile->phone }}</div>
            @endif
        </div>
    @endif
</div>
@endsection
