{{-- แถบเมนูย่อยของศูนย์ความปลอดภัย --}}
<div class="navtabs">
  <a href="{{ route('admin.security.index') }}"  class="{{ $active === 'index'  ? 'on' : '' }}">ภาพรวม</a>
  <a href="{{ route('admin.security.events') }}" class="{{ $active === 'events' ? 'on' : '' }}">เหตุการณ์</a>
  <a href="{{ route('admin.security.audits') }}" class="{{ $active === 'audits' ? 'on' : '' }}">ประวัติแก้ไขข้อมูล</a>
  <a href="{{ route('admin.security.logins') }}" class="{{ $active === 'logins' ? 'on' : '' }}">การเข้าสู่ระบบ</a>
  <a href="{{ route('admin.security.errors') }}" class="{{ $active === 'errors' ? 'on' : '' }}">ข้อผิดพลาด</a>
</div>
