@extends('layouts.app')
@section('title', '📋 คู่มือขั้นตอนการทำงาน')
@section('crumb', 'Workflow Guide · ' . auth()->user()->role->label())

@push('head')
<style>
.wf-wrap{max-width:900px;margin:0 auto}

/* Tabs */
.wf-tabs{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-bottom:28px}
.wf-tab{padding:10px 18px;border-radius:10px;border:2px solid var(--line);background:#fff;cursor:pointer;
  font-size:13px;font-weight:700;transition:all .2s;display:flex;align-items:center;gap:6px;
  color:var(--muted)}
.wf-tab:hover{border-color:var(--brand);color:var(--ink);transform:translateY(-1px)}
.wf-tab.active{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border-color:#ea580c;
  box-shadow:0 4px 12px rgba(249,115,22,.3)}
.wf-tab .emoji{font-size:18px}

/* Panels */
.wf-panel{display:none;animation:fadeIn .3s ease}
.wf-panel.active{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

/* Role header */
.wf-role{background:linear-gradient(135deg,#1e293b,#334155);color:#fff;border-radius:16px;
  padding:24px 28px;margin-bottom:24px;position:relative;overflow:hidden}
.wf-role::after{content:'';position:absolute;top:-30px;right:-30px;width:120px;height:120px;
  background:rgba(249,115,22,.15);border-radius:50%}
.wf-role h2{font-size:20px;margin-bottom:6px;font-weight:800}
.wf-role p{color:#94a3b8;font-size:13px;line-height:1.7}
.wf-role .tag{display:inline-block;background:rgba(249,115,22,.2);color:#fb923c;
  padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;margin-top:8px}

/* Process */
.wf-process{margin-bottom:28px}
.wf-ptitle{font-size:15px;font-weight:800;color:var(--ink);margin-bottom:14px;
  display:flex;align-items:center;gap:8px}
.wf-ptitle .num{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;
  width:26px;height:26px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;
  font-size:12px;font-weight:800;flex-shrink:0}

/* Steps */
.wf-steps{position:relative;padding-left:30px}
.wf-steps::before{content:'';position:absolute;left:12px;top:8px;bottom:8px;width:2px;
  background:linear-gradient(180deg,#f97316,#10b981)}
.wf-step{position:relative;margin-bottom:10px;background:#fff;border:1px solid var(--line);
  border-radius:12px;padding:13px 16px;transition:all .2s;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.wf-step:hover{box-shadow:var(--shadow-md);transform:translateX(3px)}
.wf-step::before{content:'';position:absolute;left:-24px;top:16px;width:11px;height:11px;
  border-radius:50%;background:#f97316;border:3px solid #fff;box-shadow:0 0 0 2px #f97316}
.wf-step.done::before{background:#10b981;box-shadow:0 0 0 2px #10b981}
.wf-step .lbl{font-weight:700;font-size:13px;color:var(--ink);margin-bottom:2px}
.wf-step .dsc{font-size:12.5px;color:var(--muted);line-height:1.6}
.wf-step .dsc code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11.5px}
.wf-step .hint{display:inline-block;margin-top:5px;font-size:11px;font-weight:700;
  color:var(--primary);background:#eff6ff;padding:2px 9px;border-radius:6px}

/* Connector */
.wf-conn{text-align:center;padding:6px 0;color:#f97316;font-size:20px;font-weight:800}

/* Result */
.wf-result{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:2px solid #86efac;
  border-radius:12px;padding:14px 18px;margin:10px 0 4px;font-size:12.5px}
.wf-result b{color:#15803d}
.wf-result ul{margin:4px 0 0 16px}
.wf-result li{margin-bottom:2px}

/* Next */
.wf-next{background:linear-gradient(135deg,#eff6ff,#dbeafe);border:2px solid #93c5fd;
  border-radius:12px;padding:14px 18px;margin-top:14px}
.wf-next h4{color:#1e40af;font-size:12.5px;margin-bottom:6px}
.wf-next ul{margin-left:16px;font-size:12px;color:#475569}
.wf-next li{margin-bottom:3px}
.wf-next li b{color:#1e40af}

/* Tip */
.wf-tip{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;
  margin:12px 0;font-size:12px;color:#92400e;line-height:1.7}
.wf-tip b{color:#78350f}
.wf-tip code{background:rgba(0,0,0,.06);padding:2px 6px;border-radius:4px;font-size:11px}

/* Flow cards */
.wf-flow{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:28px}
.wf-fcard{background:#fff;border:2px solid var(--line);border-radius:12px;padding:14px;text-align:center;
  transition:all .2s;position:relative}
.wf-fcard:hover{border-color:#f97316;transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.08)}
.wf-fcard .ic{font-size:26px;margin-bottom:4px}
.wf-fcard .nm{font-weight:800;font-size:12.5px;color:var(--ink)}
.wf-fcard .inf{font-size:10.5px;color:var(--muted);margin-top:3px}
.wf-fcard .arr{position:absolute;right:-14px;top:50%;transform:translateY(-50%);
  color:#f97316;font-size:18px;font-weight:800}

/* Sub-heading */
.wf-sub{margin:18px 0 8px;font-size:13.5px;font-weight:700;display:flex;align-items:center;gap:6px}

/* Table inside steps */
.wf-tbl{font-size:12px;border-collapse:collapse;width:100%;margin-top:6px}
.wf-tbl td{padding:4px 8px;border:1px solid var(--line)}
.wf-tbl td:first-child{font-weight:700;white-space:nowrap;color:var(--ink);background:#f8fafc;width:160px}
</style>
@endpush

@section('content')
<div class="wf-wrap">

{{-- ═══ TABS ═══ --}}
<div class="wf-tabs">
  @if(in_array('overview', $visibleTabs))
    <div class="wf-tab {{ $defaultTab === 'overview' ? 'active' : '' }}" data-tab="overview"><span class="emoji">🗺️</span> ภาพรวม</div>
  @endif
  @if(in_array('admin', $visibleTabs))
    <div class="wf-tab {{ $defaultTab === 'admin' ? 'active' : '' }}" data-tab="admin"><span class="emoji">👑</span> เจ้าของระบบ</div>
  @endif
  @if(in_array('warehouse', $visibleTabs))
    <div class="wf-tab {{ $defaultTab === 'warehouse' ? 'active' : '' }}" data-tab="warehouse"><span class="emoji">🏭</span> ผู้จัดการคลัง</div>
  @endif
  @if(in_array('agent', $visibleTabs))
    <div class="wf-tab {{ $defaultTab === 'agent' ? 'active' : '' }}" data-tab="agent"><span class="emoji">🤝</span> ตัวแทนขาย</div>
  @endif
  @if(in_array('shop', $visibleTabs))
    <div class="wf-tab {{ $defaultTab === 'shop' ? 'active' : '' }}" data-tab="shop"><span class="emoji">🏪</span> เจ้าของร้านค้า</div>
  @endif
  @if(in_array('seller', $visibleTabs))
    <div class="wf-tab {{ $defaultTab === 'seller' ? 'active' : '' }}" data-tab="seller"><span class="emoji">🛒</span> ผู้ขาย</div>
  @endif
  @if(in_array('customer', $visibleTabs))
    <div class="wf-tab {{ $defaultTab === 'customer' ? 'active' : '' }}" data-tab="customer"><span class="emoji">👤</span> ลูกค้า</div>
  @endif
</div>

{{-- ═══════════════════════════════════════ --}}
{{-- OVERVIEW (ทุกคนเห็น)                    --}}
{{-- ═══════════════════════════════════════ --}}
@if(in_array('overview', $visibleTabs))
<div class="wf-panel {{ $defaultTab === 'overview' ? 'active' : '' }}" id="wf-overview">
  <div class="wf-role">
    <h2>🗺️ ภาพรวม Process ทั้งหมด</h2>
    <p>ระบบทำงานเป็นวงจร: ตั้งค่า → เพิ่มสินค้า → กระจาย → ขาย → สแกน QR → สะสมแต้ม → แลกของรางวัล</p>
  </div>

  <div class="wf-flow">
    <div class="wf-fcard"><div class="ic">⚙️</div><div class="nm">1. ตั้งค่าระบบ</div><div class="inf">เจ้าของระบบ</div><div class="arr">→</div></div>
    <div class="wf-fcard"><div class="ic">🏷️</div><div class="nm">2. เพิ่มสินค้า</div><div class="inf">ผู้จัดการคลัง</div><div class="arr">→</div></div>
    <div class="wf-fcard"><div class="ic">📦</div><div class="nm">3. เติมสต๊อก</div><div class="inf">คลัง/Quick Stock</div><div class="arr">→</div></div>
    <div class="wf-fcard"><div class="ic">🚚</div><div class="nm">4. กระจายสินค้า</div><div class="inf">ใบโอน</div><div class="arr">→</div></div>
    <div class="wf-fcard"><div class="ic">💰</div><div class="nm">5. ขายสินค้า</div><div class="inf">POS</div><div class="arr">→</div></div>
    <div class="wf-fcard"><div class="ic">📱</div><div class="nm">6. สแกน QR</div><div class="inf">ลูกค้า</div><div class="arr">→</div></div>
    <div class="wf-fcard"><div class="ic">🎁</div><div class="nm">7. แลกของรางวัล</div><div class="inf">ลูกค้า + ร้าน</div><div class="arr">→</div></div>
    <div class="wf-fcard"><div class="ic">📊</div><div class="nm">8. วิเคราะห์</div><div class="inf">รายงาน</div></div>
  </div>

  <div class="wf-tip">
    <b>💡 วิธีใช้:</b> เลือกแท็บด้านบนที่เป็น<b>บทบาทของคุณ</b> → ดูว่าต้องทำอะไรก่อน-หลัง → แต่ละขั้นมี "🔗 ไปต่อที่..." บอกขั้นตอนถัดไป
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════ --}}
{{-- ADMIN (SystemAdmin)                     --}}
{{-- ═══════════════════════════════════════ --}}
@if(in_array('admin', $visibleTabs))
<div class="wf-panel {{ $defaultTab === 'admin' ? 'active' : '' }}" id="wf-admin">
  <div class="wf-role">
    <h2>👑 เจ้าของระบบ (SystemAdmin)</h2>
    <p>ควบคุมระบบทั้งหมด — ตั้งค่า, จัดการสายงาน, อนุมัติใบเบิก, ความปลอดภัย</p>
    <div class="tag">สิทธิ์: ทุกอย่าง</div>
  </div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">1</span> ตั้งค่าระบบครั้งแรก</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">⚙️ ตั้งชื่อระบบ + URL</div><div class="dsc">⚙️ ตั้งค่าระบบ → APP_NAME, URL, เขตเวลา</div></div>
      <div class="wf-step"><div class="lbl">🖼️ อัปโหลดโลโก้</div><div class="dsc">🖼️ ตั้งค่าโลโก้ → อัปโหลด SVG/PNG → favicon + ทุกหน้า</div></div>
      <div class="wf-step done"><div class="lbl">✅ เสร็จขั้นพื้นฐาน</div><div class="dsc">ระบบพร้อม → ไปตั้งค่าเชื่อมต่อภายนอก ↓</div></div>
    </div>
    <div class="wf-next"><h4>🔗 ไปต่อที่:</h4><ul><li><b>ขั้น 5-7:</b> ตั้งค่า LINE / Gmail / SMS ↓</li><li><b>ขั้น 2:</b> ตั้งค่าแต้ม + แพ็กเกจ ↓</li></ul></div>
  </div>
  <div class="wf-conn">▼</div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">2</span> ตั้งค่าระบบแต้ม + แพ็กเกจ</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">💰 กำหนดอัตราแต้ม</div><div class="dsc">⚙️ แพ็กเกจและค่าแต้ม → ขาย 100 บาท = X แต้ม, สแกน QR = X แต้ม</div><div class="hint">💡 แต้มมาก=ต้นทุนสูง, น้อย=ลูกค้าไม่สนใจ</div></div>
      <div class="wf-step"><div class="lbl">📦 สร้างแพ็กเกจสมาชิก</div><div class="dsc">สร้างแพ็กเกจรายเดือน/รายปี → ราคา + สิทธิ์</div></div>
      <div class="wf-step done"><div class="lbl">✅ บันทึก</div><div class="dsc">มีผลทันทีกับบิล/QR ใหม่</div></div>
    </div>
    <div class="wf-next"><h4>🔗 ไปต่อที่:</h4><ul><li><b>ขั้น 3:</b> สร้างสายงาน ↓</li></ul></div>
  </div>
  <div class="wf-conn">▼</div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">3</span> สร้างสายงาน + เพิ่มสมาชิก</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">🏢 สร้างหน่วยงาน</div><div class="dsc">🏢 หน่วยงาน → สร้างคลังใหญ่ → คลังย่อย → ร้านค้า</div></div>
      <div class="wf-step"><div class="lbl">👤 เพิ่มสมาชิก</div><div class="dsc">👤 สมาชิก → เพิ่มคน → กำหนดบทบาท</div><div class="hint">⚠️ จดรหัสผ่านชั่วคราว — แสดงครั้งเดียว!</div></div>
      <div class="wf-step done"><div class="lbl">✅ เสร็จ</div><div class="dsc">สมาชิกแต่ละคนเข้าระบบได้ เห็นเมนูตามบทบาท</div></div>
    </div>
    <div class="wf-next"><h4>🔗 ไปต่อที่:</h4><ul><li>ผู้จัดการคลัง → ทำตาม "เพิ่มสินค้า" → "เติมสต๊อก" → "กระจาย"</li><li>เฝ้าระวัง → 🔒 ศูนย์ความปลอดภัย</li></ul></div>
  </div>
  <div class="wf-conn">▼</div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">4</span> งานประจำวัน</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">📊 เช็ค Dashboard</div><div class="dsc">KPI: ยอดขาย, สต๊อก, ใบโอนค้าง</div></div>
      <div class="wf-step"><div class="lbl">🔒 ตรวจความปลอดภัย</div><div class="dsc">Alert ใหม่ → login ผิดปกติ? → บล็อก IP</div></div>
      <div class="wf-step"><div class="lbl">✅ อนุมัติใบเบิก</div><div class="dsc">Badge สีส้ม → ตรวจสอบ → อนุมัติ/ปฏิเสธ</div></div>
      <div class="wf-step done"><div class="lbl">📈 ดูรายงาน</div><div class="dsc">สรุปผลประกอบการ → วิเคราะห์แนวโน้ม</div></div>
    </div>
  </div>
  <div class="wf-conn">▼</div>

  {{-- ═══ 5: LINE OA ═══ --}}
  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">5</span> 💬 ตั้งค่า LINE OA (Login + Notify)</div>
    <div class="wf-tip"><b>📌 ทำไมต้องตั้งค่า LINE?</b><br>• <b>LINE Login</b> — ลูกค้าเข้าระบบด้วย LINE ได้ทันที<br>• <b>LINE Notify</b> — แจ้งเตือนพนักงานเมื่อมีใบโอน/ใบเบิกใหม่<br>• ส่งข้อความหาลูกค้าอัตโนมัติ (ได้แต้ม/แลกแต้ม)</div>

    <div class="wf-sub" style="color:#06C755">📋 ส่วน A: สร้าง LINE Login Channel</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">1️⃣ เข้า LINE Developers</div><div class="dsc">เปิด <code>https://developers.line.biz</code> → Login ด้วย LINE ส่วนตัว</div></div>
      <div class="wf-step"><div class="lbl">2️⃣ สร้าง Provider</div><div class="dsc"><b>Create a new provider</b> → ตั้งชื่อบริษัท → Create</div></div>
      <div class="wf-step"><div class="lbl">3️⃣ สร้าง LINE Login Channel</div><div class="dsc"><b>Create a LINE Login channel</b> → เลือก Provider → ตั้งชื่อ → Create</div></div>
      <div class="wf-step"><div class="lbl">4️⃣ คัดลอก Channel ID + Secret</div><div class="dsc">Basic settings → <b>Channel ID</b><br>Messaging API → <b>Channel secret</b></div><div class="hint">⚠️ เก็บ Secret เป็นความลับ</div></div>
      <div class="wf-step"><div class="lbl">5️⃣ ตั้ง Callback URL</div><div class="dsc">LINE Login → Callback URL → ใส่:<br><code>https://your-domain.com/social/callback/line</code></div></div>
      <div class="wf-step"><div class="lbl">6️⃣ เปิด Email permission</div><div class="dsc">LINE Login → OpenID Connect → Apply → ติ๊ก <b>email</b> → Submit</div></div>
      <div class="wf-step"><div class="lbl">7️⃣ กรอกค่าในระบบ</div><div class="dsc">⚙️ ตั้งค่าระบบ → Social Login → ใส่ Channel ID + Secret → 💾 บันทึก</div></div>
      <div class="wf-step done"><div class="lbl">✅ ทดสอบ</div><div class="dsc">Logout → กด 💬 เข้าสู่ระบบด้วย LINE → login ได้ = สำเร็จ!</div></div>
    </div>

    <div class="wf-sub" style="color:#06C755">📋 ส่วน B: LINE Notify (แจ้งเตือนพนักงาน)</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">1️⃣ สร้าง Token</div><div class="dsc"><code>https://notify-bot.line.me</code> → Login → My page → Generate token</div></div>
      <div class="wf-step"><div class="lbl">2️⃣ เลือกกลุ่ม/ส่วนตัว</div><div class="dsc">ส่งหาตัวเอง (ทดสอบ) หรือเลือกกลุ่ม LINE</div></div>
      <div class="wf-step"><div class="lbl">3️⃣ คัดลอก Token</div><div class="dsc">Access Token → คัดลอกเก็บไว้</div><div class="hint">⚠️ แสดงครั้งเดียว!</div></div>
      <div class="wf-step done"><div class="lbl">4️⃣ พนักงานเชื่อมต่อ</div><div class="dsc">แต่ละคน → 👤 การแจ้งเตือน → เชื่อมต่อ LINE → อนุญาต</div></div>
    </div>
    <div class="wf-result"><b>✅ ผลลัพธ์:</b><ul><li>ลูกค้า login ด้วย LINE → สะดวก</li><li>พนักงานได้รับแจ้งเตือน LINE เมื่อมีงานใหม่</li><li>ลูกค้าได้รับข้อความเมื่อได้แต้ม/แลกแต้ม</li></ul></div>
  </div>
  <div class="wf-conn">▼</div>

  {{-- ═══ 6: Gmail SMTP ═══ --}}
  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">6</span> 📧 ตั้งค่า Gmail (SMTP)</div>
    <div class="wf-tip"><b>📌 ทำไมต้องตั้งค่า Gmail?</b><br>• ส่งอีเมลแจ้งเตือนอัตโนมัติ (ใบโอน, ใบเบิก, รหัสผ่าน)<br>• ส่งใบเสร็จ/Statement ให้ลูกค้า<br>• ใช้ Gmail ฟรี ไม่ต้องเสียค่าใช้จ่าย</div>

    <div class="wf-sub" style="color:#D44638">📋 ขั้น 1: สร้าง App Password ของ Google</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">1️⃣ เข้า Google Account</div><div class="dsc"><code>https://myaccount.google.com</code> → Login Gmail ที่ต้องการใช้</div></div>
      <div class="wf-step"><div class="lbl">2️⃣ เปิด 2-Step Verification</div><div class="dsc">Security → 2-Step Verification → เปิดใช้งาน</div><div class="hint">⚠️ ต้องเปิด 2FA ก่อน!</div></div>
      <div class="wf-step"><div class="lbl">3️⃣ สร้าง App Password</div><div class="dsc">Security → App passwords → Mail → Other → ชื่อ <b>RaoMembers</b> → Generate</div></div>
      <div class="wf-step"><div class="lbl">4️⃣ คัดลอกรหัส 16 ตัว</div><div class="dsc">เช่น <code>abcd efgh ijkl mnop</code> → เอาช่องว่างออก → <code>abcdefghijklmnop</code></div><div class="hint">⚠️ แสดงครั้งเดียว!</div></div>
    </div>

    <div class="wf-sub" style="color:#D44638">📋 ขั้น 2: กรอกค่าในระบบ</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">5️⃣ ไปที่ตั้งค่าระบบ</div><div class="dsc">⚙️ ตั้งค่าระบบ → หมวด อีเมล (SMTP)</div></div>
      <div class="wf-step"><div class="lbl">6️⃣ กรอกค่า SMTP</div><div class="dsc">
        <table class="wf-tbl">
          <tr><td>MAIL_MAILER</td><td><code>smtp</code></td></tr>
          <tr><td>MAIL_HOST</td><td><code>smtp.gmail.com</code></td></tr>
          <tr><td>MAIL_PORT</td><td><code>587</code></td></tr>
          <tr><td>MAIL_USERNAME</td><td>อีเมล Gmail ของคุณ</td></tr>
          <tr><td>MAIL_PASSWORD</td><td>App Password 16 ตัว (ไม่มีช่องว่าง)</td></tr>
          <tr><td>MAIL_ENCRYPTION</td><td><code>tls</code></td></tr>
          <tr><td>MAIL_FROM_NAME</td><td>ชื่อร้าน (เช่น RaoMembers)</td></tr>
        </table>
      </div></div>
      <div class="wf-step"><div class="lbl">7️⃣ บันทึก</div><div class="dsc">💾 บันทึกการตั้งค่า</div></div>
      <div class="wf-step done"><div class="lbl">✅ ทดสอบ</div><div class="dsc">ลองเพิ่มสมาชิก → ระบบส่งอีเมลรหัสผ่านชั่วคราว → เช็ค Gmail</div></div>
    </div>
    <div class="wf-result"><b>✅ ผลลัพธ์:</b><ul><li>ระบบส่งอีเมลอัตโนมัติ (แจ้งเตือน, รหัสผ่าน, ใบเสร็จ)</li><li>ใช้ Gmail ฟรี — ดูน่าเชื่อถือ</li></ul></div>
    <div class="wf-tip"><b>⚠️ ปัญหาที่พบบ่อย:</b><br>• ส่งไม่ได้ → เปิด 2FA แล้ว? ใช้ App Password? (ไม่ใช่รหัส Gmail ปกติ)<br>• Port 587 ไม่ทำงาน → ลอง <code>465</code> + <code>ssl</code></div>
  </div>
  <div class="wf-conn">▼</div>

  {{-- ═══ 7: SMS ═══ --}}
  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">7</span> 📱 ตั้งค่า SMS</div>
    <div class="wf-tip"><b>📌 ทำไมต้อง SMS?</b><br>• ส่ง OTP / ยืนยันตัวตน<br>• แจ้งเตือนพนักงานเมื่องานด่วน<br>• ส่งโปรโมชั่นให้ลูกค้า</div>

    <div class="wf-sub" style="color:#6366f1">📋 ตัวเลือก A: Thai Bulk SMS (แนะนำ)</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">1️⃣ สมัครสมาชิก</div><div class="dsc"><code>https://www.thaibulksms.com</code> → สมัคร → ยืนยันตัวตน</div></div>
      <div class="wf-step"><div class="lbl">2️⃣ เติมเงิน</div><div class="dsc">ราคา ~0.50 - 1.50 บาท/SMS</div></div>
      <div class="wf-step"><div class="lbl">3️⃣ ขอ API Key</div><div class="dsc">API / Developer → คัดลอก API Key + Secret</div></div>
      <div class="wf-step"><div class="lbl">4️⃣ ตั้งชื่อ Sender</div><div class="dsc">ยื่นขอ Sender Name → รออนุมัติ 1-3 วัน</div></div>
      <div class="wf-step"><div class="lbl">5️⃣ กรอกค่าในระบบ</div><div class="dsc">⚙️ ตั้งค่าระบบ → SMS → API Key + Secret + Sender → บันทึก</div></div>
      <div class="wf-step done"><div class="lbl">✅ ทดสอบ</div><div class="dsc">📱 ส่ง SMS ทดสอบ → เช็ค SMS ที่ได้รับ</div></div>
    </div>

    <div class="wf-sub" style="color:#6366f1">📋 ตัวเลือก B: Twilio (นานาชาติ)</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">1️⃣ สมัคร Twilio</div><div class="dsc"><code>https://www.twilio.com</code> → Sign up</div></div>
      <div class="wf-step"><div class="lbl">2️⃣ ซื้อเบอร์</div><div class="dsc">Phone Numbers → Buy → Thailand (+66) → ~$1/เดือน</div></div>
      <div class="wf-step"><div class="lbl">3️⃣ คัดลอก Credentials</div><div class="dsc">Account SID + Auth Token + Phone Number</div></div>
      <div class="wf-step done"><div class="lbl">4️⃣ กรอก + ทดสอบ</div><div class="dsc">⚙️ ตั้งค่า → Twilio → ใส่ค่า → ทดสอบส่ง</div></div>
    </div>
    <div class="wf-result"><b>✅ ผลลัพธ์:</b><ul><li>ส่ง SMS อัตโนมัติ (OTP, แจ้งเตือน, โปรโมชั่น)</li></ul></div>
    <div class="wf-tip"><b>💡 คำแนะนำ:</b><br>• ไทย → Thai Bulk SMS (ถูก, รองรับภาษาไทย)<br>• ตปท. → Twilio (ทั่วโลก)<br>• ประหยัด → ใช้ LINE Notify แทน (ฟรี!) — ดู Process 5</div>
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════ --}}
{{-- WAREHOUSE                               --}}
{{-- ═══════════════════════════════════════ --}}
@if(in_array('warehouse', $visibleTabs))
<div class="wf-panel {{ $defaultTab === 'warehouse' ? 'active' : '' }}" id="wf-warehouse">
  <div class="wf-role">
    <h2>🏭 ผู้จัดการคลัง (WarehouseAdmin)</h2>
    <p>จัดการสินค้า: เพิ่มสินค้า, สร้างล็อต QR, เติมสต๊อก, โอนไปร้าน, นับสต๊อก</p>
    <div class="tag">สิทธิ์: สต๊อก, โอน, นับ, จัดการสมาชิก</div>
  </div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">1</span> เพิ่มสินค้าใหม่</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">🏷️ ไปที่ สินค้าและล็อต QR</div><div class="dsc">Sidebar → 📦 คลังสินค้า → สินค้าและล็อต QR</div></div>
      <div class="wf-step"><div class="lbl">➕ กด + เพิ่มสินค้า</div><div class="dsc">กรอก: ชื่อ, SKU, บาร์โค้ด, ราคาขาย, ราคาทุน, ค่าแต้ม QR</div></div>
      <div class="wf-step done"><div class="lbl">💾 บันทึก</div><div class="dsc">สินค้าเข้าระบบ → แต่ยังไม่มีสต๊อก (ต้องสร้างล็อต)</div></div>
    </div>
    <div class="wf-next"><h4>🔗 ไปต่อ:</h4><ul><li><b>ขั้น 2:</b> สร้างล็อต + QR ↓</li></ul></div>
  </div>
  <div class="wf-conn">▼</div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">2</span> สร้างล็อตผลิต + QR Code</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">📦 คลิกสินค้า → + เพิ่มล็อต</div><div class="dsc">เลขล็อต, จำนวน, วันผลิต, วันหมดอายุ</div></div>
      <div class="wf-step"><div class="lbl">🔲 ระบบสร้าง QR อัตโนมัติ</div><div class="dsc">1 QR = 1 ซอง → ลูกค้าสแกนรับแต้ม</div></div>
      <div class="wf-step"><div class="lbl">🖨️ พิมพ์ QR → แปะซอง</div><div class="dsc">ขนาด ≥ 2cm × 2cm → ตำแหน่งเดียวกันทุกซอง</div></div>
      <div class="wf-step done"><div class="lbl">✅ สต๊อกเข้าคลัง</div><div class="dsc">สินค้า + ล็อต + QR พร้อม</div></div>
    </div>
    <div class="wf-result"><b>✅ ผลลัพธ์:</b> สินค้าอยู่ในสต๊อก → พร้อมกระจายไปร้าน</div>
    <div class="wf-next"><h4>🔗 ไปต่อ:</h4><ul><li><b>ขั้น 3:</b> กระจายไปร้าน (ใบโอน) ↓</li><li>หรือ <b>📱 เพิ่มสต๊อกด่วน</b> ถ้ามีสินค้าอยู่แล้ว</li></ul></div>
  </div>
  <div class="wf-conn">▼</div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">3</span> กระจายสินค้า (ใบโอน)</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">📋 สร้างใบโอน</div><div class="dsc">เลือกปลายทาง → เลือกสินค้า+ล็อต+จำนวน</div></div>
      <div class="wf-step"><div class="lbl">⏳ รออนุมัติ → ✅ อนุมัติ</div><div class="dsc">หัวหน้าอนุมัติ (หรือตัวเองถ้ามีสิทธิ์)</div></div>
      <div class="wf-step"><div class="lbl">📤 ส่งของ</div><div class="dsc">ระบุเลขพัสดุ → สต๊อกออกจากคลัง</div></div>
      <div class="wf-step done"><div class="lbl">📥 ปลายทางรับของ</div><div class="dsc">ร้านกดรับ → สต๊อกเข้าร้าน</div></div>
    </div>
    <div class="wf-result"><b>✅ ผลลัพธ์:</b> สินค้าโอนจากคลัง → ร้านค้า สำเร็จ</div>
  </div>
  <div class="wf-conn">▼</div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">4</span> นับสต๊อก (ประจำสัปดาห์/เดือน)</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">🔢 สร้างใบนับสต๊อก</div><div class="dsc">เลือกสินค้าที่ต้องการนับ</div></div>
      <div class="wf-step"><div class="lbl">📋 นับของจริง → กรอก</div><div class="dsc">ระบบแสดงส่วนต่าง: 🔴 น้อยกว่า, 🟢 มากกว่า</div></div>
      <div class="wf-step"><div class="lbl">✏️ กรอกเหตุผล</div><div class="dsc">เช่น "นับประจำเดือน", "สต๊อกหาย"</div></div>
      <div class="wf-step done"><div class="lbl">✅ ยืนยัน</div><div class="dsc">สต๊อกในระบบ = ของจริง</div></div>
    </div>
    <div class="wf-tip"><b>💡 เคล็ดลับ:</b> นับทุกสัปดาห์ → พบปัญหาเร็ว ก่อนสะสมเยอะ</div>
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════ --}}
{{-- AGENT                                   --}}
{{-- ═══════════════════════════════════════ --}}
@if(in_array('agent', $visibleTabs))
<div class="wf-panel {{ $defaultTab === 'agent' ? 'active' : '' }}" id="wf-agent">
  <div class="wf-role">
    <h2>🤝 ตัวแทนขาย (AgentUser)</h2>
    <p>ดูแลร้านค้าในสาย — สร้างหน่วยงานลูก, เพิ่มสมาชิก, อนุมัติใบโอน</p>
    <div class="tag">สิทธิ์: จัดการสมาชิก, โอน, รายงาน</div>
  </div>
  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">1</span> จัดการร้านค้าในสาย</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">🏢 สร้างหน่วยงานลูก</div><div class="dsc">หน่วยงาน → สร้างร้านค้า → ตั้งชื่อ, รหัส</div></div>
      <div class="wf-step"><div class="lbl">👤 เพิ่มสมาชิก</div><div class="dsc">สมาชิก → เพิ่มคน → ShopUser/SellerUser → จดรหัสผ่าน</div></div>
      <div class="wf-step"><div class="lbl">📝 จัดการแพ็กเกจ</div><div class="dsc">สมาชิกร้านค้า → กรอกใบสมัคร → ต่ออายุ</div></div>
      <div class="wf-step done"><div class="lbl">✅ ร้านพร้อมใช้งาน</div><div class="dsc">สมาชิกเข้าระบบ → เริ่มขาย</div></div>
    </div>
  </div>
  <div class="wf-conn">▼</div>
  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">2</span> งานประจำวัน</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">📊 Dashboard + รายงาน</div><div class="dsc">ยอดขาย, สต๊อก, ใบโอน ของร้านในสาย</div></div>
      <div class="wf-step"><div class="lbl">📋 อนุมัติใบโอน</div><div class="dsc">ใบโอนจาก/ถึงร้านในสาย → อนุมัติ/ปฏิเสธ</div></div>
      <div class="wf-step done"><div class="lbl">📈 วิเคราะห์ผลงาน</div><div class="dsc">เปรียบเทียบร้าน → ปรับกลยุทธ์</div></div>
    </div>
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════ --}}
{{-- SHOP OWNER                              --}}
{{-- ═══════════════════════════════════════ --}}
@if(in_array('shop', $visibleTabs))
<div class="wf-panel {{ $defaultTab === 'shop' ? 'active' : '' }}" id="wf-shop">
  <div class="wf-role">
    <h2>🏪 เจ้าของร้านค้า (ShopUser)</h2>
    <p>ขายสินค้า, รับแลกแต้ม, ตั้งหน้าร้าน, เบิกเงิน</p>
    <div class="tag">สิทธิ์: ขาย, แลกแต้ม, หน้าร้าน, เบิกเงิน</div>
  </div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">1</span> รับสินค้าเข้าร้าน</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">📥 ตรวจใบโอน "ส่งแล้ว"</div><div class="dsc">ใบโอนสินค้า → สถานะ 🟣 ส่งแล้ว (จากคลัง)</div></div>
      <div class="wf-step"><div class="lbl">📦 นับของจริง</div><div class="dsc">เทียบใบโอนกับของจริง → ตรงกัน?</div></div>
      <div class="wf-step done"><div class="lbl">✅ กดรับของ</div><div class="dsc">สต๊อกเข้าร้าน → พร้อมขาย!</div></div>
    </div>
    <div class="wf-next"><h4>🔗 ไปต่อ:</h4><ul><li><b>ขั้น 2:</b> ขายสินค้า (POS) ↓</li></ul></div>
  </div>
  <div class="wf-conn">▼</div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">2</span> ขายสินค้า (POS)</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">💰 เปิดบิลขาย</div><div class="dsc">สแกน/เลือกสินค้า → จำนวน → เลือกล็อต (FIFO)</div></div>
      <div class="wf-step"><div class="lbl">👤 เลือกลูกค้า</div><div class="dsc">ค้นหาเบอร์โทร → ลูกค้าได้แต้มสะสม</div></div>
      <div class="wf-step"><div class="lbl">💾 บันทึกบิล → 🧾 ใบเสร็จ</div><div class="dsc">สต๊อกลด → กำไรคำนวณจากทุนล็อต</div></div>
      <div class="wf-step done"><div class="lbl">✅ ขายสำเร็จ</div><div class="dsc">ดูยอดขายที่ ประวัติการขาย</div></div>
    </div>
    <div class="wf-next"><h4>🔗 ไปต่อ:</h4><ul><li>ลูกค้าสะสมแต้ม → มาแลกของรางวัล ↓</li><li><b>ขั้น 3:</b> ตั้งหน้าร้าน ↓</li></ul></div>
  </div>
  <div class="wf-conn">▼</div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">3</span> ตั้งหน้าร้าน + ของรางวัล</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">🏪 ตั้งค่าหน้าร้าน</div><div class="dsc">ชื่อ, โลโก้, สีธีม → เปิดใช้งาน</div></div>
      <div class="wf-step"><div class="lbl">🎁 เพิ่มของรางวัล</div><div class="dsc">กำหนดแต้ม, รูป, จำนวน → หลายระดับ (เล็ก-ใหญ่)</div></div>
      <div class="wf-step"><div class="lbl">📱 แชร์หน้าร้าน</div><div class="dsc">แชร์ลิงก์/QR → ลูกค้าดู 24 ชม.</div></div>
      <div class="wf-step done"><div class="lbl">✅ พร้อม</div><div class="dsc">ลูกค้าดู + แลกจากมือถือ</div></div>
    </div>
    <div class="wf-next"><h4>🔗 ไปต่อ:</h4><ul><li><b>ขั้น 4:</b> รับแลกแต้ม ↓</li></ul></div>
  </div>
  <div class="wf-conn">▼</div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">4</span> รับแลกแต้ม</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">👤 ลูกค้าแสดง QR</div><div class="dsc">QR Code ของรางวัล (จาก LINE/แอป)</div></div>
      <div class="wf-step"><div class="lbl">📷 สแกน → 🔍 ตรวจสอบ</div><div class="dsc">ชื่อตรง? ของรางวัลถูก? แต้มพอ?</div></div>
      <div class="wf-step"><div class="lbl">✅ ยืนยัน → 🎁 ให้ของ</div><div class="dsc">แต้มตัดอัตโนมัติ → มอบของรางวัล</div></div>
      <div class="wf-step done"><div class="lbl">🎉 เสร็จ!</div><div class="dsc">ลูกค้าพอใจ → บันทึกในประวัติ</div></div>
    </div>
    <div class="wf-next"><h4>🔗 ไปต่อ:</h4><ul><li><b>ขั้น 5:</b> เบิกเงิน (ถ้ามีค่าใช้จ่าย) ↓</li></ul></div>
  </div>
  <div class="wf-conn">▼</div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">5</span> เบิกเงินคืน</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">💵 สร้างใบเบิก</div><div class="dsc">จำนวน, เหตุผล, แนบหลักฐาน</div></div>
      <div class="wf-step"><div class="lbl">⏳ รออนุมัติ</div><div class="dsc">เจ้าของระบบตรวจสอบ</div></div>
      <div class="wf-step done"><div class="lbl">💰 รับเงิน</div><div class="dsc">หลังอนุมัติ → โอนเงินให้</div></div>
    </div>
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════ --}}
{{-- SELLER                                  --}}
{{-- ═══════════════════════════════════════ --}}
@if(in_array('seller', $visibleTabs))
<div class="wf-panel {{ $defaultTab === 'seller' ? 'active' : '' }}" id="wf-seller">
  <div class="wf-role">
    <h2>🛒 ผู้ขาย (SellerUser)</h2>
    <p>ขายสินค้า + รับแลกแต้ม — เน้นงานขายประจำวัน</p>
    <div class="tag">สิทธิ์: ขาย, แลกแต้ม, ดูรายงาน</div>
  </div>
  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">1</span> เปิดร้าน (ประจำวัน)</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">📦 เช็คสต๊อก</div><div class="dsc">สต๊อกคงเหลือ → ตัวไหนใกล้หมด → แจ้งหัวหน้า</div></div>
      <div class="wf-step"><div class="lbl">📥 รับของ (ถ้ามี)</div><div class="dsc">ใบโอน "ส่งแล้ว" → นับ → กดรับ</div></div>
      <div class="wf-step done"><div class="lbl">✅ พร้อมขาย!</div></div>
    </div>
  </div>
  <div class="wf-conn">▼</div>
  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">2</span> ขายสินค้า (POS)</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">💰 เปิดบิลขาย</div><div class="dsc">สแกน/เลือก → จำนวน → ล็อต (FIFO)</div></div>
      <div class="wf-step"><div class="lbl">👤 ถาม "มีสมาชิกไหม?"</div><div class="dsc">มี → ค้นหาเบอร์ → ลูกค้าได้แต้ม</div></div>
      <div class="wf-step"><div class="lbl">💾 บันทึก → 🧾 ใบเสร็จ</div><div class="dsc">สต๊อกลด → พิมพ์ใบเสร็จ</div></div>
      <div class="wf-step done"><div class="lbl">🔄 ขายไปเรื่อยๆ</div><div class="dsc">ดูยอดขายที่ ประวัติการขาย</div></div>
    </div>
    <div class="wf-tip"><b>💡 เคล็ดลับ:</b> ถามลูกค้าทุกครั้ง "มีบัญชีสมาชิกไหม?" — ลูกค้าได้แต้ม, คุณได้ข้อมูล, ลูกค้ากลับมาซื้ออีก</div>
  </div>
  <div class="wf-conn">▼</div>
  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">3</span> รับแลกแต้ม</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">⭐ สแกน QR ลูกค้า</div><div class="dsc">รับแลกแต้ม → สแกน → ตรวจสอบ → ยืนยัน</div></div>
      <div class="wf-step done"><div class="lbl">🎁 มอบของรางวัล</div><div class="dsc">แต้มตัดอัตโนมัติ → เสร็จ!</div></div>
    </div>
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════ --}}
{{-- CUSTOMER                                --}}
{{-- ═══════════════════════════════════════ --}}
@if(in_array('customer', $visibleTabs))
<div class="wf-panel {{ $defaultTab === 'customer' ? 'active' : '' }}" id="wf-customer">
  <div class="wf-role">
    <h2>👤 ลูกค้า (Customer)</h2>
    <p>ซื้อสินค้า → สแกน QR → สะสมแต้ม → แลกของรางวัล!</p>
    <div class="tag">ใช้งานผ่านมือถือ — ไม่ต้อง login ระบบ</div>
  </div>

  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">1</span> สแกน QR ครั้งแรก</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">📱 เปิดลิงก์/สแกน QR จากซอง</div><div class="dsc">เห็นชื่อสินค้าและแต้มที่จะได้</div></div>
      <div class="wf-step"><div class="lbl">📷 ถ่ายรูปสแกน</div><div class="dsc">📷 ถ่ายรูปสแกน → ถ่าย QR → อ่านอัตโนมัติ</div></div>
      <div class="wf-step"><div class="lbl">📞 กรอกเบอร์โทร</div><div class="dsc">ครั้งแรกเท่านั้น → ระบบจำไว้</div></div>
      <div class="wf-step"><div class="lbl">⭐ รับแต้ม</div><div class="dsc">⭐ รับแต้มเลย → แต้มเข้ากระเป๋า!</div></div>
      <div class="wf-step done"><div class="lbl">🎉 สำเร็จ!</div><div class="dsc">เห็นแต้มที่ได้ → ดูกระเป๋าแต้ม</div></div>
    </div>
    <div class="wf-result"><b>✅ ผลลัพธ์:</b> มีแต้มแล้ว! สแกนซองถัดไปสะสมเพิ่ม</div>
  </div>
  <div class="wf-conn">▼</div>
  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">2</span> สะสมแต้ม (ทุกครั้งที่ซื้อ)</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">🛒 ซื้อ → 📷 สแกน → ⭐ รับแต้ม</div><div class="dsc">ทำซ้ำทุกครั้ง → ยิ่งซื้อมาก ยิ่งแต้มมาก</div></div>
      <div class="wf-step done"><div class="lbl">📈 แต้มเพิ่มขึ้นเรื่อยๆ</div></div>
    </div>
    <div class="wf-tip"><b>💡 เคล็ดลับ:</b> ยิ่งซื้อเยอะ ยิ่งสแกนเยอะ → แลกของรางวัลใหญ่ได้!</div>
  </div>
  <div class="wf-conn">▼</div>
  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">3</div> แลกของรางวัล</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">💰 เช็คแต้ม</div><div class="dsc">กระเป๋าแต้ม → มีกี่แต้ม</div></div>
      <div class="wf-step"><div class="lbl">🎁 เลือกของรางวัล</div><div class="dsc">ดูหน้าร้าน → เลือกตัวที่แต้มถึง</div></div>
      <div class="wf-step"><div class="lbl">📱 แสดง QR → 🏪 ไปร้าน</div><div class="dsc">กดแลก → ได้ QR → แสดงให้พนักงานสแกน</div></div>
      <div class="wf-step done"><div class="lbl">🎉 ได้ของรางวัล!</div><div class="dsc">แต้มตัด → ของเป็นของคุณ</div></div>
    </div>
    <div class="wf-result"><b>🎉 ยินดีด้วย!</b> สะสมต่อเพื่อแลกของใหญ่กว่า!</div>
  </div>
  <div class="wf-conn">▼</div>
  <div class="wf-process">
    <div class="wf-ptitle"><span class="num">4</span> ผูก LINE (ไม่บังคับ)</div>
    <div class="wf-steps">
      <div class="wf-step"><div class="lbl">💬 ผูก LINE</div><div class="dsc">กระเป๋าแต้ม → บัญชีฉัน → ผูก LINE</div></div>
      <div class="wf-step done"><div class="lbl">✅ สะดวกขึ้น</div><div class="dsc">ไม่ต้องกรอกเบอร์ทุกครั้ง</div></div>
    </div>
  </div>
</div>
@endif

</div><!-- wf-wrap -->
@endsection

@push('scripts')
<script>
document.querySelectorAll('.wf-tab').forEach(function(tab) {
  tab.addEventListener('click', function() {
    document.querySelectorAll('.wf-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.wf-panel').forEach(function(p) { p.classList.remove('active'); });
    tab.classList.add('active');
    var panel = document.getElementById('wf-' + tab.dataset.tab);
    if (panel) panel.classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
});
</script>
@endpush
