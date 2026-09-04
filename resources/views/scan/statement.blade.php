<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ประวัติแต้มสะสม — {{ $customer->name }}</title>
<style>
  @page { size: A4; margin: 14mm; }
  *{box-sizing:border-box}
  body{
    font-family:'Kanit','Prompt','TH Sarabun New',sans-serif;
    color:#14140F;margin:0;padding:20px;background:#fff;font-size:13px;
  }
  .head{
    display:flex;justify-content:space-between;align-items:flex-start;
    border-bottom:3px solid #F04800;padding-bottom:14px;margin-bottom:18px;
  }
  .head h1{margin:0 0 4px;font-size:21px;color:#F04800}
  .head .sub{font-size:12px;color:#6E6E63;line-height:1.7}
  .logo{width:70px;height:70px;object-fit:contain}
  .cards{display:flex;gap:12px;margin-bottom:20px}
  .card{
    flex:1;border:1.5px solid #E8E8E0;border-radius:10px;padding:12px 14px;
  }
  .card .l{font-size:11px;color:#6E6E63}
  .card .v{font-size:22px;font-weight:800;color:#F04800;line-height:1.3}
  h2{font-size:14px;margin:20px 0 9px;padding-left:9px;border-left:4px solid #006018}
  table{width:100%;border-collapse:collapse;font-size:12px}
  th{background:#F4F4EE;text-align:left;padding:8px 10px;font-weight:700;border-bottom:2px solid #E8E8E0}
  td{padding:7px 10px;border-bottom:1px solid #F0F0EA}
  .num{text-align:right;white-space:nowrap}
  .plus{color:#006018;font-weight:700}
  .minus{color:#F04800;font-weight:700}
  .foot{
    margin-top:26px;padding-top:12px;border-top:1px solid #E8E8E0;
    font-size:10.5px;color:#9E9E9E;line-height:1.7;text-align:center;
  }
  .noprint{
    background:#FFF9E6;border:1px solid #FFE9A8;border-radius:8px;
    padding:11px 14px;margin-bottom:18px;font-size:12px;color:#7A5C00;
  }
  .btn{
    background:#F04800;color:#fff;border:none;padding:10px 20px;border-radius:8px;
    font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;
  }
  @media print { .noprint{display:none} body{padding:0} }
</style>
</head>
<body>

<div class="noprint">
  กด <button class="btn" onclick="window.print()">พิมพ์ / บันทึกเป็น PDF</button>
  แล้วเลือก "บันทึกเป็น PDF" ในหน้าต่างที่ขึ้นมา
</div>

<div class="head">
  <div>
    <h1>ประวัติแต้มสะสม</h1>
    <div class="sub">
      <b>{{ $customer->name }}</b><br>
      เบอร์โทร {{ $customer->phone ?? '—' }}<br>
      ออกรายงานเมื่อ {{ $printedAt->format('j M Y เวลา H:i น.') }}
    </div>
  </div>
  <img class="logo" src="{{ asset('brand/logo.svg') }}" alt="{{ config('app.name') }}">
</div>

<div class="cards">
  <div class="card">
    <div class="l">แต้มคงเหลือทั้งหมด</div>
    <div class="v">{{ number_format($total) }}</div>
  </div>
  <div class="card">
    <div class="l">สะสมมาแล้วทั้งหมด</div>
    <div class="v">{{ number_format($wallets->sum('lifetime_earned')) }}</div>
  </div>
  <div class="card">
    <div class="l">ใช้ไปแล้ว</div>
    <div class="v">{{ number_format($wallets->sum('lifetime_used')) }}</div>
  </div>
</div>

<h2>แต้มคงเหลือแยกตามร้าน</h2>
<table>
  <thead>
    <tr>
      <th>ร้านผู้ออกแต้ม</th>
      <th class="num">สะสมมาแล้ว</th>
      <th class="num">ใช้ไป</th>
      <th class="num">คงเหลือ</th>
    </tr>
  </thead>
  <tbody>
    @forelse($wallets as $w)
      <tr>
        <td>{{ $w->issuer->name ?? 'ร้านค้า' }}</td>
        <td class="num">{{ number_format($w->lifetime_earned) }}</td>
        <td class="num">{{ number_format($w->lifetime_used) }}</td>
        <td class="num"><b>{{ number_format($w->balance) }}</b></td>
      </tr>
    @empty
      <tr><td colspan="4" style="text-align:center;color:#9E9E9E">ยังไม่มีแต้มสะสม</td></tr>
    @endforelse
  </tbody>
</table>

<h2>ประวัติการรับแต้ม</h2>
<table>
  <thead>
    <tr>
      <th>วันที่</th>
      <th>รายการ</th>
      <th class="num">แต้ม</th>
    </tr>
  </thead>
  <tbody>
    @forelse($scans as $s)
      <tr>
        <td>{{ $s->scanned_at?->format('j M Y H:i') }}</td>
        <td>สแกน QR — {{ $s->qrcode?->product?->name ?? 'สินค้า' }}</td>
        <td class="num plus">+{{ number_format($s->points_awarded ?? 0) }}</td>
      </tr>
    @empty
      <tr><td colspan="3" style="text-align:center;color:#9E9E9E">ยังไม่มีประวัติ</td></tr>
    @endforelse
  </tbody>
</table>

<h2>ประวัติการใช้แต้ม</h2>
<table>
  <thead>
    <tr>
      <th>วันที่</th>
      <th>รายการ</th>
      <th>ร้านที่ใช้</th>
      <th class="num">แต้ม</th>
    </tr>
  </thead>
  <tbody>
    @forelse($redemptions as $r)
      <tr>
        <td>{{ $r->redeemed_at?->format('j M Y H:i') }}</td>
        <td>{{ $r->reward_name }}</td>
        <td>{{ $r->shop->name ?? '—' }}</td>
        <td class="num minus">-{{ number_format($r->points_used) }}</td>
      </tr>
    @empty
      <tr><td colspan="4" style="text-align:center;color:#9E9E9E">ยังไม่มีการใช้แต้ม</td></tr>
    @endforelse
  </tbody>
</table>

<div class="foot">
  เอกสารนี้ออกโดยระบบ {{ config('app.name', 'RaoMembers') }} อัตโนมัติ<br>
  ใช้เพื่อการตรวจสอบส่วนบุคคลเท่านั้น · หากพบข้อมูลไม่ถูกต้องกรุณาติดต่อผู้ดูแลระบบ
</div>

</body>
</html>
