@extends('layouts.app')
@section('title', 'จัดการร้านค้า')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">🏪 จัดการร้านค้า</h2>
            <p class="text-muted mb-0">จัดการข้อมูลร้านค้า ตรวจสอบ และยืนยันการทำงาน</p>
        </div>
        <a href="{{ route('agent.shops.history') }}" class="btn btn-outline-primary">
            📍 ประวัติ Check-in
        </a>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <div class="text-primary fs-1">🏪</div>
                    <h3 class="mb-0">{{ $shops->total() }}</h3>
                    <small class="text-muted">ร้านค้าที่ดูแล</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <div class="text-success fs-1">📍</div>
                    <h3 class="mb-0">{{ $monthlyCheckins }}</h3>
                    <small class="text-muted">Check-in เดือนนี้</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-info">
                <div class="card-body text-center">
                    <div class="text-info fs-1">📸</div>
                    <h3 class="mb-0">{{ $recentCheckins->where('photos')->count() }}</h3>
                    <small class="text-muted">มีรูปถ่าย</small>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        {{-- รายการร้านค้า --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <strong>🏪 รายการร้านค้า</strong>
                </div>
                <div class="card-body p-0">
                    @if($shops->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <div class="fs-1">🏪</div>
                            <p>ยังไม่มีร้านค้าในระบบ</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>รูป</th>
                                        <th>ชื่อร้าน</th>
                                        <th>ที่อยู่</th>
                                        <th>ติดต่อ</th>
                                        <th>พิกัด</th>
                                        <th class="text-center">ดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($shops as $shop)
                                    <tr>
                                        <td>
                                            @if($shop->photos && count($shop->photos) > 0)
                                                <img src="{{ asset('storage/' . $shop->photos[0]) }}"
                                                     class="rounded" width="50" height="50" style="object-fit:cover;"
                                                     alt="{{ $shop->name }}">
                                            @else
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                                     style="width:50px;height:50px;">
                                                    🏪
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $shop->name }}</strong>
                                            @if($shop->shop_type)
                                                <br><small class="text-muted">{{ $shop->shop_type }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($shop->address)
                                                <small>{{ Str::limit($shop->address, 50) }}</small>
                                            @else
                                                <small class="text-danger">ยังไม่ได้กรอก</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($shop->phone)
                                                <small>📞 {{ $shop->phone }}</small><br>
                                            @endif
                                            @if($shop->line_id)
                                                <small>💬 {{ $shop->line_id }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($shop->lat && $shop->lng)
                                                <small class="text-success">✅ มีพิกัด</small>
                                            @else
                                                <small class="text-danger">❌ ไม่มี</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('agent.shops.edit', $shop->id) }}"
                                                   class="btn btn-outline-primary" title="แก้ไขข้อมูล">
                                                    ✏️
                                                </a>
                                                <a href="{{ route('agent.shops.checkin', $shop->id) }}"
                                                   class="btn btn-outline-success" title="Check-in">
                                                    📍
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer">
                            {{ $shops->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Check-in ล่าสุด --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <strong>📍 Check-in ล่าสุด</strong>
                </div>
                <div class="card-body p-0">
                    @if($recentCheckins->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <p class="mb-0">ยังไม่มีประวัติ check-in</p>
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($recentCheckins as $checkin)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong>{{ $checkin->shop->name ?? 'ไม่พบร้าน' }}</strong>
                                        <br>
                                        <small class="text-muted">
                                            @switch($checkin->type)
                                                @case('visit') 🏪 เยี่ยมร้าน @break
                                                @case('delivery') 🚚 ส่งของ @break
                                                @case('pickup') 📦 รับของ @break
                                                @case('other') 📌 อื่นๆ @break
                                            @endswitch
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">
                                            {{ $checkin->created_at->diffForHumans() }}
                                        </small>
                                        @if($checkin->photos)
                                            <br><small class="text-info">📸 {{ count($checkin->photos) }} รูป</small>
                                        @endif
                                    </div>
                                </div>
                                @if($checkin->distance_meters !== null)
                                    <small class="{{ $checkin->distance_meters < 100 ? 'text-success' : 'text-warning' }}">
                                        📏 ระยะห่าง {{ number_format($checkin->distance_meters) }} ม.
                                    </small>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
