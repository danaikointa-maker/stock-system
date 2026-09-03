@extends('layouts.app')
@section('title', 'หน่วยงานในสังกัด')
@section('crumb', 'โครงสร้างสายงานที่คุณดูแล')

@section('content')

<div class="card">
  <h3>
    ค้นหาหน่วยงาน
    @can('create', App\Models\OrgNode::class)
      <a href="{{ route('nodes.create') }}" class="btn btn-p btn-sm">
        + เปิด{{ auth()->user()->level()?->child()?->label() ?? 'หน่วยงาน' }}ใหม่
      </a>
    @endcan
  </h3>
  <div class="body">
    <form method="GET" class="filters">
      <div class="field">
        <label>ค้นหา</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="ชื่อ หรือ รหัสหน่วยงาน">
      </div>
      <div class="field">
        <label>ระดับ</label>
        <select name="level">
          <option value="">ทุกระดับ</option>
          @foreach($levels as $l)
            <option value="{{ $l->value }}" @selected(request('level') == $l->value)>{{ $l->label() }}</option>
          @endforeach
        </select>
      </div>
      <button class="btn btn-p">ค้นหา</button>
      <a href="{{ route('nodes.index') }}" class="btn">ล้าง</a>
    </form>
  </div>
</div>

<div class="card">
  <h3>โครงสร้างสายงาน ({{ $nodes->count() }} หน่วยงาน)</h3>

  @if($nodes->isEmpty())
    <div class="empty">ไม่พบหน่วยงาน</div>
  @else
    <table>
      <thead>
        <tr>
          <th>หน่วยงาน</th><th>ระดับ</th><th>ติดต่อ</th>
          <th class="num">หน่วยงานลูก</th><th class="num">สมาชิก</th>
          <th>สถานะ</th><th style="width:170px"></th>
        </tr>
      </thead>
      <tbody>
      @php $baseDepth = $myNode?->depth ?? 0; @endphp
      @foreach($nodes as $n)
        <tr style="{{ $n->id === $myNode?->id ? 'background:#f0f5ff' : '' }}">
          <td>
            <span class="tree-in">{{ str_repeat('　', max(0, $n->depth - $baseDepth)) }}{{ $n->depth > $baseDepth ? '└ ' : '' }}</span>
            <a href="{{ route('nodes.show', $n) }}"><b>{{ $n->name }}</b></a>
            @if($n->id === $myNode?->id)
              <span class="badge b-blue" style="margin-left:5px">หน่วยงานของคุณ</span>
            @endif
            <div style="font-size:11px;margin-left:2px"><code>{{ $n->code }}</code></div>
          </td>
          <td><span class="badge b-gray">{{ $n->level_id->label() }}</span></td>
          <td style="font-size:12.5px">{{ $n->phone ?? '—' }}</td>
          <td class="num">{{ $n->children_count }}</td>
          <td class="num">{{ $n->users_count }}</td>
          <td>
            @if($n->status === 'active')
              <span class="badge b-green">เปิดทำการ</span>
            @elseif($n->status === 'suspended')
              <span class="badge b-amber">ระงับชั่วคราว</span>
            @else
              <span class="badge b-red">ปิดกิจการ</span>
            @endif
          </td>
          <td style="text-align:right">
            <a href="{{ route('nodes.show', $n) }}" class="btn btn-sm">รายละเอียด</a>
            @can('update', $n)
              <a href="{{ route('nodes.edit', $n) }}" class="btn btn-sm">แก้ไข</a>
            @endcan
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  @endif
</div>

@endsection
