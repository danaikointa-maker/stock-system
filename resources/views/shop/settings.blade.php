@extends('layouts.app')
@section('title', 'ตั้งค่าหน้าร้าน')

@push('head')
<style>
  .set-grid{display:grid;grid-template-columns:1fr 330px;gap:18px;align-items:start}
  @media(max-width:980px){.set-grid{grid-template-columns:1fr}}

  .tmpl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
  .tmpl{
    border:2px solid var(--line);border-radius:13px;padding:13px 10px;text-align:center;
    cursor:pointer;transition:border-color .15s,background .15s;
  }
  .tmpl:has(input:checked){border-color:var(--brand);background:#FFF4EE}
  .tmpl input{display:none}
  .tmpl .ic{font-size:26px;display:block;margin-bottom:5px}
  .tmpl .nm{font-size:12.5px;font-weight:700}
  .tmpl .sw{display:flex;gap:3px;justify-content:center;margin-top:6px}
  .tmpl .sw i{width:16px;height:9px;border-radius:3px;display:block}

  .upload{
    border:2px dashed var(--line);border-radius:13px;padding:16px;text-align:center;
  }
  .upload img{max-width:110px;max-height:110px;object-fit:contain;border-radius:10px;margin-bottom:9px}
  .upload input[type=file]{font-size:12.5px;width:100%}
  .upload .no{
    width:88px;height:88px;margin:0 auto 9px;border-radius:12px;background:#F2F2EC;
    display:grid;place-items:center;font-size:30px;color:#BDBDBD;
  }

  .blocks{display:grid;grid-template-columns:1fr 1fr;gap:8px}
  @media(max-width:600px){.blocks{grid-template-columns:1fr}}
  .blocks label{
    display:flex;align-items:center;gap:9px;padding:10px 12px;
    border:1.5px solid var(--line);border-radius:11px;font-size:13px;cursor:pointer;
  }
  .blocks label:has(input:checked){border-color:var(--brand);background:#FFF9F6}
  .blocks input{width:17px;height:17px;accent-color:var(--brand)}

  .rw-row{
    display:flex;gap:11px;align-items:center;padding:11px 13px;
    border:1.5px solid var(--line);border-radius:12px;margin-bottom:8px;
  }
  .rw-row.off{opacity:.5;background:#FAFAF7}
  .rw-row .th{
    width:44px;height:44px;border-radius:10px;flex-shrink:0;display:grid;
    place-items:center;font-size:21px;background:#FFF4EE;overflow:hidden;
  }
  .rw-row .th img{width:100%;height:100%;object-fit:cover}
  .rw-row .g{flex:1;min-width:0}
  .rw-row .g b{display:block;font-size:13.5px}
  .rw-row .g small{color:var(--muted);font-size:11.5px}
  .rw-row .p{font-weight:800;color:var(--brand);white-space:nowrap;font-size:15px}

  .colorpick{display:flex;gap:9px;align-items:center}
  .colorpick input[type=color]{
    width:46px;height:38px;border:1.5px solid var(--line);border-radius:9px;
    padding:2px;cursor:pointer;background:#fff;
  }
</style>
@endpush

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:16px">
  <div>
    <h1 style="margin:0 0 4px">ตั้งค่าหน้าร้าน</h1>
    <p style="margin:0;color:var(--muted);font-size:13.5px">
      {{ $shop->name }} · ปรับหน้าตาร้านของคุณเองได้ทั้งหมด
    </p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="{{ route('shop.preview') }}" target="_blank" class="btn btn-sm">ดูตัวอย่าง</a>
    @if($profile->slug && $profile->status === 'active')
      <a href="{{ route('storefront', $profile->slug) }}" target="_blank" class="btn btn-sm">เปิดหน้าร้านจริง</a>
    @endif
  </div>
</div>

@if(session('status'))
  <div class="alert a-ok">{{ session('status') }}</div>
@endif

@if($errors->any())
  <div class="alert a-bad">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
  </div>
@endif

<form method="POST" action="{{ route('shop.update') }}" enctype="multipart/form-data">
  @csrf
  @method('PUT')

  <div class="set-grid">
    <div>
      {{-- ข้อมูลร้าน --}}
      <div class="card" style="margin-bottom:16px">
        <div class="body">
          <h3 style="margin:0 0 13px;font-size:14px">ข้อมูลร้าน</h3>

          <div class="field">
            <label for="display_name">ชื่อร้าน <span style="color:var(--brand)">*</span></label>
            <input class="input" type="text" id="display_name" name="display_name" maxlength="150"
                   value="{{ old('display_name', $profile->display_name ?? $shop->name) }}" required>
          </div>

          <div class="field">
            <label for="tagline">คำโปรยสั้น ๆ</label>
            <input class="input" type="text" id="tagline" name="tagline" maxlength="255"
                   value="{{ old('tagline', $profile->tagline) }}"
                   placeholder="เช่น กาแฟคั่วสดทุกวัน บรรยากาศร่มรื่น">
          </div>

          <div class="field">
            <label for="description">รายละเอียดร้าน</label>
            <textarea class="input" id="description" name="description" rows="3"
                      maxlength="2000">{{ old('description', $profile->description) }}</textarea>
          </div>

          <div class="grid g2">
            <div class="field">
              <label for="phone">เบอร์โทร</label>
              <input class="input" type="text" id="phone" name="phone" maxlength="30"
                     value="{{ old('phone', $profile->phone) }}">
            </div>
            <div class="field">
              <label for="line_id">LINE ID</label>
              <input class="input" type="text" id="line_id" name="line_id" maxlength="80"
                     value="{{ old('line_id', $profile->line_id) }}">
            </div>
          </div>

          <div class="field">
            <label for="address">ที่อยู่</label>
            <textarea class="input" id="address" name="address" rows="2"
                      maxlength="500">{{ old('address', $profile->address) }}</textarea>
          </div>

          <div class="grid g2">
            <div class="field">
              <label for="lat">พิกัด — ละติจูด</label>
              <input class="input" type="text" id="lat" name="lat"
                     value="{{ old('lat', $profile->lat) }}" placeholder="13.7563">
            </div>
            <div class="field" style="margin-bottom:0">
              <label for="lng">พิกัด — ลองจิจูด</label>
              <input class="input" type="text" id="lng" name="lng"
                     value="{{ old('lng', $profile->lng) }}" placeholder="100.5018">
            </div>
          </div>
          <p class="hint">พิกัดใช้ตรวจว่าลูกค้าสแกนใกล้ร้านจริงไหม (ช่วยกันโกง)</p>
        </div>
      </div>

      {{-- เทมเพลต --}}
      <div class="card" style="margin-bottom:16px">
        <div class="body">
          <h3 style="margin:0 0 6px;font-size:14px">เลือกเทมเพลตตามประเภทธุรกิจ</h3>
          <p class="hint" style="margin-bottom:13px">
            แต่ละแบบมีชุดสีและบล็อกเนื้อหาที่เหมาะกับธุรกิจนั้น เปลี่ยนทีหลังได้ตลอด
          </p>

          <div class="tmpl-grid">
            @foreach($templates as $key => $t)
              <label class="tmpl">
                <input type="radio" name="business_type" value="{{ $key }}"
                       {{ old('business_type', $profile->business_type ?? 'retail') === $key ? 'checked' : '' }}>
                <span class="ic">{{ $t['icon'] }}</span>
                <span class="nm">{{ $t['name'] }}</span>
                <span class="sw">
                  <i style="background:{{ $t['colors'][0] }}"></i>
                  <i style="background:{{ $t['colors'][1] }}"></i>
                </span>
              </label>
            @endforeach
          </div>

          <div class="grid g2" style="margin-top:15px">
            <div class="field" style="margin-bottom:0">
              <label for="color_primary">สีหลัก (ถ้าอยากกำหนดเอง)</label>
              <div class="colorpick">
                <input type="color" id="color_primary_pick"
                       value="{{ old('color_primary', $profile->color_primary ?: '#F04800') }}">
                <input class="input" type="text" id="color_primary" name="color_primary"
                       value="{{ old('color_primary', $profile->color_primary) }}"
                       placeholder="เว้นว่าง = ใช้สีของเทมเพลต" maxlength="7">
              </div>
            </div>
            <div class="field" style="margin-bottom:0">
              <label for="color_secondary">สีรอง</label>
              <div class="colorpick">
                <input type="color" id="color_secondary_pick"
                       value="{{ old('color_secondary', $profile->color_secondary ?: '#FF6B2B') }}">
                <input class="input" type="text" id="color_secondary" name="color_secondary"
                       value="{{ old('color_secondary', $profile->color_secondary) }}"
                       placeholder="เว้นว่าง = ใช้สีของเทมเพลต" maxlength="7">
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- บล็อกเนื้อหา --}}
      <div class="card" style="margin-bottom:16px">
        <div class="body">
          <h3 style="margin:0 0 13px;font-size:14px">บล็อกที่แสดงบนหน้าร้าน</h3>
          @php $blocks = old('blocks', $profile->blocks ?? ['rewards' => true, 'gallery' => true]); @endphp
          <div class="blocks">
            @foreach([
              'rewards' => 'รายการแลกแต้ม',
              'gallery' => 'แกลเลอรีรูปภาพ',
              'hours'   => 'เวลาทำการ',
              'map'     => 'แผนที่ / ที่อยู่',
              'contact' => 'ช่องทางติดต่อ',
              'booking' => 'ระบบจองคิว',
            ] as $key => $label)
              <label>
                <input type="hidden" name="blocks[{{ $key }}]" value="0">
                <input type="checkbox" name="blocks[{{ $key }}]" value="1"
                       {{ ! empty($blocks[$key]) ? 'checked' : '' }}>
                {{ $label }}
              </label>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    {{-- แถบข้าง --}}
    <div>
      <div class="card" style="margin-bottom:16px">
        <div class="body">
          <h3 style="margin:0 0 12px;font-size:14px">โลโก้ร้าน</h3>
          <div class="upload">
            @if($profile->logo_path)
              <img src="{{ Storage::url($profile->logo_path) }}" alt="โลโก้">
            @else
              <div class="no">🏪</div>
            @endif
            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
            <p class="hint" style="margin-top:7px">jpg / png / webp · ไม่เกิน 3 MB</p>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <div class="body">
          <h3 style="margin:0 0 12px;font-size:14px">รูปปก</h3>
          <div class="upload">
            @if($profile->cover_path)
              <img src="{{ Storage::url($profile->cover_path) }}" alt="ปก" style="max-width:100%">
            @else
              <div class="no">🖼️</div>
            @endif
            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <div class="body">
          <h3 style="margin:0 0 12px;font-size:14px">สถานะหน้าร้าน</h3>
          <div class="field" style="margin-bottom:0">
            <select class="input" name="status">
              <option value="draft" @selected(old('status', $profile->status ?? 'draft') === 'draft')>
                ร่าง — ยังไม่เผยแพร่
              </option>
              <option value="active" @selected(old('status', $profile->status) === 'active')>
                เผยแพร่ — ลูกค้าเห็นได้
              </option>
            </select>
          </div>
          @if($profile->slug)
            <p class="hint" style="margin-top:9px">
              ลิงก์หน้าร้าน<br>
              <code style="font-size:11px">/r/{{ $profile->slug }}</code>
            </p>
          @endif
        </div>
      </div>

      <button type="submit" class="btn btn-p" style="width:100%;padding:14px;font-size:15px">
        บันทึกการตั้งค่า
      </button>
    </div>
  </div>
</form>

{{-- ของรางวัล --}}
<div class="card" style="margin-top:20px">
  <div class="body">
    <h3 style="margin:0 0 4px;font-size:15px">ของรางวัลที่รับแลก</h3>
    <p class="hint" style="margin-bottom:14px">
      รายการเหล่านี้จะแสดงบนหน้าร้านให้ลูกค้าเห็นว่าแลกอะไรได้บ้าง
    </p>

    @forelse($rewards as $rw)
      <div class="rw-row {{ $rw->is_active ? '' : 'off' }}">
        <div class="th">
          @if($rw->image_path)
            <img src="{{ Storage::url($rw->image_path) }}" alt="">
          @else
            {{ $rw->displayIcon() }}
          @endif
        </div>
        <div class="g">
          <b>{{ $rw->name }}</b>
          <small>
            @switch($rw->reward_type)
              @case('goods') สินค้า @break
              @case('service') บริการ @break
              @case('discount') ส่วนลด @break
              @case('cash') เงินสด @break
            @endswitch
            @if($rw->stockLeft() !== null) · เหลือ {{ $rw->stockLeft() }} สิทธิ์ @endif
            @if($rw->redeemed_count > 0) · แลกไปแล้ว {{ $rw->redeemed_count }} ครั้ง @endif
          </small>
        </div>
        <div class="p">{{ number_format($rw->points_cost) }}</div>
        <form method="POST" action="{{ route('shop.rewards.toggle', $rw) }}" style="display:inline">
          @csrf @method('PATCH')
          <button type="submit" class="btn btn-sm">{{ $rw->is_active ? 'ปิด' : 'เปิด' }}</button>
        </form>
        <form method="POST" action="{{ route('shop.rewards.destroy', $rw) }}" style="display:inline"
              onsubmit="return confirm('ลบของรางวัลนี้?')">
          @csrf @method('DELETE')
          <button type="submit" class="btn btn-sm btn-d">ลบ</button>
        </form>
      </div>
    @empty
      <div class="empty">ยังไม่มีของรางวัล — เพิ่มรายการแรกด้านล่าง</div>
    @endforelse

    {{-- ฟอร์มเพิ่ม --}}
    <details style="margin-top:16px">
      <summary style="cursor:pointer;font-weight:700;font-size:14px;padding:10px 0">
        + เพิ่มของรางวัลใหม่
      </summary>

      <form method="POST" action="{{ route('shop.rewards.store') }}"
            enctype="multipart/form-data" style="margin-top:14px">
        @csrf
        <div class="grid g2">
          <div class="field">
            <label for="rw_name">ชื่อของรางวัล <span style="color:var(--brand)">*</span></label>
            <input class="input" type="text" id="rw_name" name="name" maxlength="200" required
                   placeholder="เช่น กาแฟเย็น แก้วใหญ่">
          </div>
          <div class="field">
            <label for="rw_type">ประเภท</label>
            <select class="input" id="rw_type" name="reward_type">
              <option value="service">บริการ</option>
              <option value="goods">สินค้า (ตัดสต๊อก)</option>
              <option value="discount">ส่วนลด</option>
              <option value="cash">เงินสด</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="rw_desc">รายละเอียด</label>
          <input class="input" type="text" id="rw_desc" name="description" maxlength="500"
                 placeholder="เช่น เลือกได้ทุกเมนู ยกเว้นเมนูพิเศษ">
        </div>

        <div class="grid g4">
          <div class="field">
            <label for="rw_points">ใช้กี่แต้ม <span style="color:var(--brand)">*</span></label>
            <input class="input" type="number" id="rw_points" name="points_cost" min="1" required>
          </div>
          <div class="field">
            <label for="rw_cash">มูลค่า (บาท)</label>
            <input class="input" type="number" id="rw_cash" name="cash_value" min="0" step="0.01">
          </div>
          <div class="field">
            <label for="rw_stock">จำกัดจำนวน</label>
            <input class="input" type="number" id="rw_stock" name="stock_limit" min="0"
                   placeholder="เว้นว่าง = ไม่จำกัด">
          </div>
          <div class="field">
            <label for="rw_icon">ไอคอน</label>
            <input class="input" type="text" id="rw_icon" name="icon" maxlength="10" placeholder="☕">
          </div>
        </div>

        <div class="grid g2">
          <div class="field" id="productField" style="display:none">
            <label for="rw_product">สินค้าที่จะจ่ายออก</label>
            <select class="input" id="rw_product" name="product_id">
              <option value="">— เลือกสินค้า —</option>
              @foreach(\App\Models\Product::orderBy('name')->limit(200)->get() as $p)
                <option value="{{ $p->id }}">{{ $p->sku }} · {{ $p->name }}</option>
              @endforeach
            </select>
            <p class="hint">จำเป็นสำหรับของรางวัลประเภทสินค้า เพื่อให้ระบบตัดสต๊อกได้</p>
          </div>
          <div class="field">
            <label for="rw_img">รูปภาพ</label>
            <input class="input" type="file" id="rw_img" name="image"
                   accept="image/jpeg,image/png,image/webp">
          </div>
        </div>

        <button type="submit" class="btn btn-p">เพิ่มของรางวัล</button>
      </form>
    </details>
  </div>
</div>
@endsection

@push('scripts')
<script>
/* ซิงก์ color picker กับช่องข้อความ */
[['color_primary','color_primary_pick'],['color_secondary','color_secondary_pick']]
  .forEach(function ([textId, pickId]) {
    var text = document.getElementById(textId);
    var pick = document.getElementById(pickId);
    if (!text || !pick) return;
    pick.addEventListener('input', function () { text.value = pick.value.toUpperCase(); });
    text.addEventListener('input', function () {
      if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) pick.value = text.value;
    });
  });

/* แสดงช่องเลือกสินค้าเฉพาะของรางวัลประเภทสินค้า */
(function () {
  var type = document.getElementById('rw_type');
  var field = document.getElementById('productField');
  if (!type || !field) return;
  function toggle() { field.style.display = type.value === 'goods' ? '' : 'none'; }
  type.addEventListener('change', toggle);
  toggle();
})();
</script>
@endpush
