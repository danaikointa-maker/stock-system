@extends('layouts.app')
@section('title', 'ประวัติ Check-in')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">📍 ประวัติ Check-in</h2>
        <a href="{{ route('agent.shops.index') }}" class="btn btn-outline-secondary">← กลับ</a>
    </div>

    @if($checkins->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="fs-1">📍</div>
                <p class="text-muted">ยังไม่มีประวัติ check-in</p>
                <a href="{{ route('agent.shops.index') }}" class="btn btn-primary">ไปยังรายการร้านค้า</a>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>วันที่</th>
                            <th>ร้านค้า</th>
                            <th>ประเภท</th>
                            <th>หมายเหตุ</th>
                            <th>พิกัด</th>
                            <th>ระยะห่าง</th>
                            <th>รูป</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($checkins as $checkin)
                        <tr>
                            <td>
                                <strong>{{ $checkin->created_at->format('d/m/Y') }}</strong><br>
                                <small class="text-muted">{{ $checkin->created_at->format('H:i') }} น.</small>
                            </td>
                            <td>
                                <strong>{{ $checkin->shop->name ?? 'ไม่พบร้าน' }}</strong>
                                @if($checkin->shop && $checkin->shop->address)
                                    <br><small class="text-muted">{{ Str::limit($checkin->shop->address, 30) }}</small>
                                @endif
                            </td>
                            <td>
                                @switch($checkin->type)
                                    @case('visit')
                                        <span class="badge bg-primary">🏪 เยี่ยม</span>
                                        @break
                                    @case('delivery')
                                        <span class="badge bg-success">🚚 ส่งของ</span>
                                        @break
                                    @case('pickup')
                                        <span class="badge bg-warning text-dark">📦 รับของ</span>
                                        @break
                                    @case('other')
                                        <span class="badge bg-secondary">📌 อื่นๆ</span>
                                        @break
                                @endswitch
                            </td>
                            <td>
                                @if($checkin->notes)
                                    <small>{{ Str::limit($checkin->notes, 50) }}</small>
                                @else
                                    <small class="text-muted">-</small>
                                @endif
                            </td>
                            <td>
                                <small>{{ number_format($checkin->latitude, 6) }}, {{ number_format($checkin->longitude, 6) }}</small>
                                <br>
                                <a href="https://maps.google.com/?q={{ $checkin->latitude }},{{ $checkin->longitude }}"
                                   target="_blank" class="small">🗺️ ดูแผนที่</a>
                            </td>
                            <td>
                                @if($checkin->distance_meters !== null)
                                    <span class="badge bg-{{ $checkin->distance_meters < 100 ? 'success' : ($checkin->distance_meters < 500 ? 'warning' : 'danger') }}">
                                        {{ number_format($checkin->distance_meters) }} ม.
                                    </span>
                                @else
                                    <small class="text-muted">-</small>
                                @endif
                            </td>
                            <td>
                                @if($checkin->photos && count($checkin->photos) > 0)
                                    <div class="d-flex gap-1">
                                        @foreach(array_slice($checkin->photos, 0, 3) as $photo)
                                            <img src="{{ asset('storage/' . $photo) }}" class="rounded"
                                                 width="40" height="40" style="object-fit:cover;" alt="photo">
                                        @endforeach
                                        @if(count($checkin->photos) > 3)
                                            <span class="badge bg-secondary align-self-center">+{{ count($checkin->photos) - 3 }}</span>
                                        @endif
                                    </div>
                                @else
                                    <small class="text-muted">ไม่มีรูป</small>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $checkins->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
