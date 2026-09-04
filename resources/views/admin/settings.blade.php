@extends('layouts.app')
@section('title', 'ตั้งค่าระบบ')

@section('content')
<div style="max-width:800px">
  <h1 style="font-size:22px;margin-bottom:4px">⚙️ ตั้งค่าระบบ</h1>
  <p style="font-size:13px;color:var(--muted);margin-bottom:24px">แก้ไขค่า config จาก UI ได้โดยตรง — ไม่ต้องแก้ไขไฟล์ .env เอง</p>

  @if(session('status'))
    <div style="background:#E8F5E9;border:1px solid #A5D6A7;color:#1B5E20;padding:12px 16px;border-radius:12px;margin-bottom:16px;font-size:13px">
      {{ session('status') }}
    </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf

    @foreach($groups as $groupKey => $group)
      <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px;margin-bottom:16px">
        <h2 style="font-size:16px;margin-bottom:16px">{{ $group['title'] }}</h2>

        @foreach($group['fields'] as $key => $meta)
          @php
            $lowerKey = strtolower($key);
            $currentVal = $currentValues[$key] ?? '';
          @endphp

          <div style="margin-bottom:14px">
            <label style="font-weight:600;font-size:13px;margin-bottom:4px;display:block">{{ $meta['label'] }}</label>

            @if($meta['type'] === 'toggle')
              <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
                <input type="hidden" name="{{ $lowerKey }}" value="">
                <input type="checkbox" name="{{ $lowerKey }}" value="true"
                       {{ $currentVal === 'true' ? 'checked' : '' }}
                       style="width:18px;height:18px;accent-color:var(--ok)">
                <span style="font-size:13px">{{ $currentVal === 'true' ? 'เปิด' : 'ปิด' }}</span>
              </label>

            @elseif($meta['type'] === 'select')
              <select name="{{ $lowerKey }}"
                      style="width:100%;padding:10px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fff">
                @foreach($meta['options'] as $val => $label)
                  <option value="{{ $val }}" {{ $currentVal === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>

            @elseif($meta['type'] === 'password')
              <input type="password" name="{{ $lowerKey }}" value="{{ $currentVal }}"
                     placeholder="••••••••"
                     style="width:100%;padding:10px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit">

            @else
              <input type="{{ $meta['type'] }}" name="{{ $lowerKey }}" value="{{ $currentVal }}"
                     style="width:100%;padding:10px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit">
            @endif

            @if(isset($meta['hint']))
              <p style="font-size:12px;color:var(--muted);margin-top:2px">{{ $meta['hint'] }}</p>
            @endif
          </div>
        @endforeach
      </div>
    @endforeach

    <div style="display:flex;gap:10px;margin-top:8px">
      <button type="submit"
              style="flex:1;padding:14px 24px;border-radius:12px;border:none;background:var(--brand);color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit">
        💾 บันทึกการตั้งค่า
      </button>
    </div>
  </form>

  <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;margin-top:16px">
    <h3 style="font-size:14px;margin-bottom:8px">ℹ️ ข้อมูลระบบ</h3>
    <table style="font-size:12px;color:var(--muted);width:100%">
      <tr><td style="padding:3px 0">PHP Version</td><td><b>{{ PHP_VERSION }}</b></td></tr>
      <tr><td style="padding:3px 0">Laravel</td><td><b>{{ app()->version() }}</b></td></tr>
      <tr><td style="padding:3px 0">Environment</td><td><b>{{ config('app.env') }}</b></td></tr>
      <tr><td style="padding:3px 0">Debug Mode</td><td><b>{{ config('app.debug') ? 'เปิด' : 'ปิด' }}</b></td></tr>
      <tr><td style="padding:3px 0">Database</td><td><b>{{ config('database.default') }}</b></td></tr>
    </table>
  </div>
</div>
@endsection
