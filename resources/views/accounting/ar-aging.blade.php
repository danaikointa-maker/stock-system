@extends('layouts.app')
@section('title', 'รายงานลูกหนี้ค้างรับ (AR Aging)')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">📋 รายงานลูกหนี้ค้างรับ (AR Aging)</h2>
        <a href="{{ route('accounting.invoices') }}" class="btn btn-outline-secondary">
            ← กลับ
        </a>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card text-center border-success">
                <div class="card-body py-3">
                    <div class="text-success fw-bold">ยังไม่ถึงกำหนด</div>
                    <h4 class="mb-0">{{ number_format($current->sum('balance'), 2) }}</h4>
                    <small class="text-muted">{{ $current->count() }} ใบ</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-info">
                <div class="card-body py-3">
                    <div class="text-info fw-bold">1-30 วัน</div>
                    <h4 class="mb-0">{{ number_format($days30->sum('balance'), 2) }}</h4>
                    <small class="text-muted">{{ $days30->count() }} ใบ</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-warning">
                <div class="card-body py-3">
                    <div class="text-warning fw-bold">31-60 วัน</div>
                    <h4 class="mb-0">{{ number_format($days60->sum('balance'), 2) }}</h4>
                    <small class="text-muted">{{ $days60->count() }} ใบ</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-danger">
                <div class="card-body py-3">
                    <div class="text-danger fw-bold">61-90 วัน</div>
                    <h4 class="mb-0">{{ number_format($days90->sum('balance'), 2) }}</h4>
                    <small class="text-muted">{{ $days90->count() }} ใบ</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-dark">
                <div class="card-body py-3">
                    <div class="text-dark fw-bold">เกิน 90 วัน</div>
                    <h4 class="mb-0">{{ number_format($over90->sum('balance'), 2) }}</h4>
                    <small class="text-muted">{{ $over90->count() }} ใบ</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center bg-light">
                <div class="card-body py-3">
                    <div class="fw-bold">รวมทั้งหมด</div>
                    <h4 class="mb-0">{{ number_format($current->sum('balance') + $days30->sum('balance') + $days60->sum('balance') + $days90->sum('balance') + $over90->sum('balance'), 2) }}</h4>
                    <small class="text-muted">{{ $current->count() + $days30->count() + $days60->count() + $days90->count() + $over90->count() }} ใบ</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail Tables --}}
    @foreach([
        ['title' => 'ยังไม่ถึงกำหนด', 'data' => $current, 'class' => 'success'],
        ['title' => 'เกินกำหนด 1-30 วัน', 'data' => $days30, 'class' => 'info'],
        ['title' => 'เกินกำหนด 31-60 วัน', 'data' => $days60, 'class' => 'warning'],
        ['title' => 'เกินกำหนด 61-90 วัน', 'data' => $days90, 'class' => 'danger'],
        ['title' => 'เกินกำหนด 90+ วัน', 'data' => $over90, 'class' => 'dark'],
    ] as $bucket)
        @if($bucket['data']->count() > 0)
        <div class="card mb-4">
            <div class="card-header bg-{{ $bucket['class'] }} text-white">
                <strong>{{ $bucket['title'] }}</strong>
                <span class="float-end">{{ $bucket['data']->count() }} รายการ | รวม {{ number_format($bucket['data']->sum('balance'), 2) }} บาท</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>เลขที่บิล</th>
                            <th>ลูกค้า</th>
                            <th>วันที่ออกบิล</th>
                            <th>กำหนดชำระ</th>
                            <th class="text-end">ยอดรวม</th>
                            <th class="text-end">ยอดค้าง</th>
                            <th class="text-center">สถานะ</th>
                            <th class="text-center">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bucket['data'] as $inv)
                        <tr>
                            <td><strong>{{ $inv->invoice_no }}</strong></td>
                            <td>{{ $inv->customer_name }}</td>
                            <td>{{ $inv->invoice_date->format('d/m/Y') }}</td>
                            <td>{{ $inv->due_date->format('d/m/Y') }}</td>
                            <td class="text-end">{{ number_format($inv->total, 2) }}</td>
                            <td class="text-end fw-bold text-danger">{{ number_format($inv->balance, 2) }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $inv->status === 'overdue' ? 'danger' : 'warning' }}">
                                    {{ $inv->status === 'overdue' ? 'เกินกำหนด' : 'ค้างชำระ' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('accounting.invoices.show', $inv->id) }}" class="btn btn-sm btn-outline-primary">
                                    ดูรายละเอียด
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endforeach

    @if($current->count() + $days30->count() + $days60->count() + $days90->count() + $over90->count() === 0)
        <div class="alert alert-info text-center">
            <i class="bi bi-check-circle"></i> ไม่มีลูกหนี้ค้างรับ
        </div>
    @endif
</div>
@endsection
