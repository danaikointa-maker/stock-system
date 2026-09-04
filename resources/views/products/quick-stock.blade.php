@extends('layouts.app')
@section('title', 'เพิ่มสต๊อกด่วน')

@section('content')
<div style="max-width:800px">
  <h1 style="font-size:20px;margin-bottom:4px">📦 เพิ่มสต๊อกด่วน</h1>
  <p style="font-size:13px;color:var(--muted);margin-bottom:20px">
    สแกนบาร์โค้ดจากปืนสแกนเนอร์ · ถ่ายรูปจากมือถือ · หรือพิมพ์รหัสเอง
  </p>

  {{-- ─── ช่องสแกน ─────────────────────────────────────── --}}
  <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;margin-bottom:16px">
    <label style="font-weight:600;font-size:14px;margin-bottom:8px;display:block">🔍 สแกน / พิมพ์ บาร์โค้ด</label>
    <div style="display:flex;gap:8px">
      <input type="text" id="barcodeInput" class="input" autofocus
             placeholder="สแกนบาร์โค้ด หรือพิมพ์รหัสสินค้า แล้วกด Enter"
             style="flex:1;font-size:16px;padding:14px">
      <button type="button" id="cameraBtn" class="btn btn-main"
              style="padding:14px 18px;white-space:nowrap;border-radius:10px">
        📷
      </button>
    </div>
    <p style="font-size:12px;color:var(--muted);margin-top:6px">
      💡 ปืนสแกนเนอร์: สแกนได้เลย — ระบบจะรับค่าอัตโนมัติ
    </p>

    {{-- กล้อง (ซ่อน) --}}
    <div id="cameraBox" style="display:none;margin-top:12px">
      <div style="background:#000;border-radius:12px;overflow:hidden;position:relative">
        <div id="qr-reader" style="width:100%"></div>
        <button type="button" id="cameraClose"
                style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:50%;width:32px;height:32px;font-size:16px;cursor:pointer;z-index:10">✕</button>
      </div>
    </div>
  </div>

  {{-- ─── ผลลัพธ์สแกน ─────────────────────────────────── --}}
  <div id="scanResult" style="display:none;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;margin-bottom:16px">
    <div id="foundBox" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <span style="font-size:12px;color:var(--muted)">สินค้าที่พบ</span>
          <h3 id="productName" style="font-size:16px;margin:0"></h3>
          <span id="productSku" style="font-size:12px;color:var(--muted)"></span>
        </div>
        <span style="font-size:28px">✅</span>
      </div>

      <form id="lotForm">
        <input type="hidden" id="productId" value="">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
          <div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:2px">เลขล็อต *</label>
            <input type="text" id="lotNo" class="input" placeholder="เช่น LOT-2026-001" required>
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:2px">จำนวน *</label>
            <input type="number" id="qty" class="input" min="1" value="100" required>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
          <div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:2px">วันผลิต</label>
            <input type="date" id="mfgDate" class="input">
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:2px">วันหมดอายุ</label>
            <input type="date" id="expiryDate" class="input">
          </div>
        </div>
        <button type="submit" class="btn btn-main" style="width:100%;padding:14px;border-radius:10px">
          ➕ เพิ่มล็อตสินค้า
        </button>
      </form>
    </div>

    <div id="notFoundBox" style="display:none;text-align:center;padding:16px">
      <span style="font-size:36px">❌</span>
      <p style="margin-top:8px;font-weight:600">ไม่พบสินค้า</p>
      <p id="notFoundQuery" style="font-size:13px;color:var(--muted)"></p>
      <a href="{{ route('products.create') }}" class="btn btn-main" style="display:inline-block;margin-top:12px;padding:10px 20px;border-radius:10px;text-decoration:none">
        + เพิ่มสินค้าใหม่
      </a>
    </div>
  </div>

  {{-- ─── รายการที่เพิ่มแล้ว ─────────────────────────── --}}
  <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px">
    <h3 style="font-size:14px;margin-bottom:12px">📋 ล็อตที่เพิ่มในรอบนี้ <span id="lotCount" style="color:var(--muted)">(0)</span></h3>
    <div id="lotList" style="max-height:300px;overflow-y:auto">
      <p id="emptyMsg" style="text-align:center;color:var(--muted);font-size:13px;padding:20px">
        ยังไม่มีรายการ — สแกนบาร์โค้ดเพื่อเริ่มเพิ่มสต๊อก
      </p>
    </div>
  </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function(){
  var B = window.__baseUrl || '';
  var input = document.getElementById('barcodeInput');
  var scanResult = document.getElementById('scanResult');
  var foundBox = document.getElementById('foundBox');
  var notFoundBox = document.getElementById('notFoundBox');
  var lotForm = document.getElementById('lotForm');
  var lotList = document.getElementById('lotList');
  var lotCount = document.getElementById('lotCount');
  var emptyMsg = document.getElementById('emptyMsg');
  var lots = [];
  var qrReader = null;

  // ─── สแกน/พิมพ์ barcode → lookup ─────────────────────
  input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      lookup(this.value.trim());
    }
  });

  function lookup(q) {
    if (!q) return;
    fetch(B + '/products/quick-stock/lookup?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(data => {
        scanResult.style.display = 'block';
        if (data.found) {
          foundBox.style.display = 'block';
          notFoundBox.style.display = 'none';
          document.getElementById('productName').textContent = data.product.name;
          document.getElementById('productSku').textContent = 'SKU: ' + (data.product.sku || '-') + ' | Barcode: ' + (data.product.barcode || '-');
          document.getElementById('productId').value = data.product.id;
          document.getElementById('lotNo').value = 'LOT-' + new Date().toISOString().slice(0,10).replace(/-/g,'') + '-' + String(lots.length + 1).padStart(3, '0');
          document.getElementById('lotNo').focus();
        } else {
          foundBox.style.display = 'none';
          notFoundBox.style.display = 'block';
          document.getElementById('notFoundQuery').textContent = 'ค้นหา: "' + q + '"';
        }
        input.value = '';
        input.focus();
      })
      .catch(() => { input.focus(); });
  }

  // ─── เพิ่มล็อต (AJAX) ────────────────────────────────
  lotForm.addEventListener('submit', function(e) {
    e.preventDefault();
    var body = {
      product_id: document.getElementById('productId').value,
      lot_no: document.getElementById('lotNo').value,
      qty: document.getElementById('qty').value,
      mfg_date: document.getElementById('mfgDate').value || null,
      expiry_date: document.getElementById('expiryDate').value || null,
    };

    fetch(B + '/products/quick-stock/add', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      },
      body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        lots.push(data);
        renderLots();
        // reset form
        foundBox.style.display = 'none';
        scanResult.style.display = 'none';
        input.focus();
      }
    })
    .catch(() => alert('เกิดข้อผิดพลาด กรุณาลองใหม่'));
  });

  function renderLots() {
    lotCount.textContent = '(' + lots.length + ')';
    if (lots.length === 0) { emptyMsg.style.display = ''; return; }
    emptyMsg.style.display = 'none';
    var html = '';
    lots.forEach(function(l, i) {
      html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--line)">';
      html += '<div><b>' + l.product_name + '</b><br>';
      html += '<span style="font-size:12px;color:var(--muted)">ล็อต: ' + l.lot.lot_no + ' · จำนวน: ' + l.lot.qty_produced + '</span></div>';
      html += '<span style="font-size:12px;color:var(--ok);font-weight:600">✓ เพิ่มแล้ว</span></div>';
    });
    lotList.innerHTML = html;
  }

  // ─── กล้องสแกน ──────────────────────────────────────
  var cameraBtn = document.getElementById('cameraBtn');
  var cameraBox = document.getElementById('cameraBox');
  var cameraClose = document.getElementById('cameraClose');

  cameraBtn.addEventListener('click', function() {
    if (typeof Html5Qrcode === 'undefined') {
      // fallback: ใช้ native camera input
      var fi = document.createElement('input');
      fi.type = 'file'; fi.accept = 'image/*'; fi.capture = 'environment';
      fi.addEventListener('change', function(e) {
        if (!e.target.files[0]) return;
        if (typeof Html5Qrcode === 'undefined') { alert('กรุณากรอกรหัสเอง'); return; }
        var tid = 'qr-tmp-' + Date.now();
        var td = document.createElement('div'); td.id = tid; td.style.display = 'none';
        document.body.appendChild(td);
        var r = new Html5Qrcode(tid);
        r.scanFile(e.target.files[0], true).then(function(code) {
          lookup(code); r.clear().catch(function(){}); td.remove();
        }).catch(function() {
          alert('อ่าน barcode ไม่ได้ กรุณาลองใหม่'); r.clear().catch(function(){}); td.remove();
        });
      });
      fi.click();
      return;
    }
    cameraBox.style.display = 'block';
    qrReader = new Html5Qrcode('qr-reader');
    qrReader.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 250, height: 150 },
        formatsToSupport: [
          Html5QrcodeSupportedFormats.QR_CODE,
          Html5QrcodeSupportedFormats.EAN_13, Html5QrcodeSupportedFormats.EAN_8,
          Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.CODE_39,
          Html5QrcodeSupportedFormats.UPC_A, Html5QrcodeSupportedFormats.UPC_E
        ]
      },
      function(code) { stopCamera(); lookup(code); },
      function() {}
    ).catch(function() {
      stopCamera();
      alert('เปิดกล้องไม่ได้ — กรุณากรอกรหัสเอง');
    });
  });

  cameraClose.addEventListener('click', stopCamera);

  function stopCamera() {
    if (qrReader) { qrReader.stop().then(function(){ qrReader.clear(); }).catch(function(){}); qrReader = null; }
    cameraBox.style.display = 'none';
  }
})();
</script>
@endsection
