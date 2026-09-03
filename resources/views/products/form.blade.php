@extends('layouts.app')
@section('title', $product->exists ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า')
@section('crumb', 'จัดการสินค้า')

@section('content')

<form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
  @csrf
  @if($product->exists) @method('PUT') @endif

  <div class="grid g2">
    <div class="card">
      <h3>ข้อมูลสินค้า</h3>
      <div class="body">
        <div class="field">
          <label for="sku">รหัสสินค้า (SKU) *</label>
          <input type="text" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" required>
          @error('sku')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="field">
          <label for="name">ชื่อสินค้า *</label>
          <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" required>
          @error('name')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="field">
          <label for="barcode">บาร์โค้ด</label>
          <input type="text" id="barcode" name="barcode" value="{{ old('barcode', $product->barcode) }}">
        </div>

        <div class="field">
          <label for="category_id">หมวดหมู่</label>
          <select id="category_id" name="category_id">
            <option value="">— ไม่ระบุ —</option>
            @foreach($categories as $c)
              <option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label for="unit_id">หน่วยนับ</label>
          <select id="unit_id" name="unit_id">
            <option value="">— ไม่ระบุ —</option>
            @foreach($units as $u)
              <option value="{{ $u->id }}" @selected(old('unit_id', $product->unit_id) == $u->id)>{{ $u->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label for="status">สถานะ *</label>
          <select id="status" name="status" required>
            <option value="active" @selected(old('status', $product->status) === 'active')>ใช้งาน</option>
            <option value="inactive" @selected(old('status', $product->status) === 'inactive')>ปิดการขาย</option>
          </select>
        </div>
      </div>
    </div>

    <div class="card">
      <h3>ราคาและคะแนน</h3>
      <div class="body">
        <div class="field">
          <label for="cost_price">ราคาทุน *</label>
          <input type="number" step="0.01" min="0" id="cost_price" name="cost_price"
                 value="{{ old('cost_price', $product->cost_price ?? 0) }}" required>
          @error('cost_price')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="field">
          <label for="retail_price">ราคาขายปลีก *</label>
          <input type="number" step="0.01" min="0" id="retail_price" name="retail_price"
                 value="{{ old('retail_price', $product->retail_price ?? 0) }}" required>
          @error('retail_price')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="field">
          <label for="pack_size">จำนวนต่อแพ็ค *</label>
          <input type="number" min="1" id="pack_size" name="pack_size"
                 value="{{ old('pack_size', $product->pack_size ?? 1) }}" required>
        </div>

        <div class="field">
          <label for="points_per_unit">คะแนนที่ลูกค้าได้ต่อชิ้น *</label>
          <input type="number" min="0" id="points_per_unit" name="points_per_unit"
                 value="{{ old('points_per_unit', $product->points_per_unit ?? 1) }}" required>
          <div style="font-size:11.5px;color:var(--muted);margin-top:3px">
            ใช้เป็นค่าเริ่มต้นตอนออก QR ของล็อตใหม่
          </div>
        </div>

        <div class="field">
          <label>
            <input type="hidden" name="has_expiry" value="0">
            <input type="checkbox" name="has_expiry" value="1" style="width:auto"
                   @checked(old('has_expiry', $product->has_expiry))>
            สินค้ามีวันหมดอายุ (เปิดใช้การตัดสต๊อกแบบ FEFO)
          </label>
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:10px">
    <button type="submit" class="btn btn-p">บันทึก</button>
    <a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="btn">ยกเลิก</a>
  </div>
</form>

@endsection
