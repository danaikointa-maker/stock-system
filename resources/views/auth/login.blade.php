<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เข้าสู่ระบบ · ระบบสต๊อกสินค้า</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Noto Sans Thai","Sarabun",-apple-system,"Segoe UI",sans-serif;
  background:linear-gradient(135deg,#16233d 0%,#2c5fd4 100%);
  min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:#1a2233}
.box{background:#fff;border-radius:14px;box-shadow:0 18px 50px rgba(0,0,0,.28);
  width:100%;max-width:410px;padding:34px 32px}
.logo{text-align:center;margin-bottom:26px}
.logo b{font-size:21px;display:block;letter-spacing:-.02em}
.logo span{font-size:12.5px;color:#6b7793}
label{display:block;font-size:12.5px;font-weight:600;margin-bottom:5px;color:#42506b}
input[type=text],input[type=password]{width:100%;padding:11px 13px;border:1px solid #d5dce8;
  border-radius:8px;font-size:14px;font-family:inherit}
input:focus{outline:2px solid #bfd2fa;border-color:#2c5fd4}
.field{margin-bottom:16px}
.btn{width:100%;padding:12px;background:#2c5fd4;color:#fff;border:0;border-radius:8px;
  font-size:14.5px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{background:#1e4499}
.err{background:#fee2e2;color:#7f1d1d;border:1px solid #fca5a5;padding:10px 13px;
  border-radius:8px;margin-bottom:16px;font-size:13px}
.ok{background:#dcfce7;color:#14532d;border:1px solid #86efac;padding:10px 13px;
  border-radius:8px;margin-bottom:16px;font-size:13px}
.remember{display:flex;align-items:center;gap:7px;font-size:13px;color:#42506b;margin-bottom:18px}
.remember input{width:auto}
.levels{margin-top:24px;padding-top:18px;border-top:1px solid #e3e8f0;font-size:11.5px;color:#6b7793}
.levels b{display:block;margin-bottom:7px;color:#42506b;font-size:12px}
.chain{display:flex;flex-wrap:wrap;gap:4px;align-items:center}
.chain span{background:#eef1f6;padding:2px 7px;border-radius:20px}
.chain i{color:#adb7c9;font-style:normal}
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <b>ระบบสต๊อกสินค้า</b>
    <span>บริหารคลังและสายงานจัดจำหน่าย</span>
  </div>

  @if(session('status'))
    <div class="ok">{{ session('status') }}</div>
  @endif

  @if($errors->any())
    <div class="err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
  @endif

  <form method="POST" action="{{ route('login.attempt') }}">
    @csrf
    <div class="field">
      <label for="login">อีเมล หรือ เบอร์โทรศัพท์</label>
      <input type="text" id="login" name="login" value="{{ old('login') }}"
             autocomplete="username" required autofocus>
    </div>

    <div class="field">
      <label for="password">รหัสผ่าน</label>
      <input type="password" id="password" name="password"
             autocomplete="current-password" required>
    </div>

    <label class="remember">
      <input type="checkbox" name="remember" value="1"> จดจำการเข้าสู่ระบบ
    </label>

    <button type="submit" class="btn">เข้าสู่ระบบ</button>
  </form>

  <div class="levels">
    <b>ระบบรองรับผู้ใช้ 6 ระดับ</b>
    <div class="chain">
      <span>เจ้าของระบบ</span><i>›</i>
      <span>คลังใหญ่</span><i>›</i>
      <span>คลังย่อย</span><i>›</i>
      <span>ตัวแทนขาย</span><i>›</i>
      <span>ร้านค้า</span><i>›</i>
      <span>ผู้ขาย</span>
    </div>
    <div style="margin-top:9px">แต่ละระดับเห็นและจัดการได้เฉพาะข้อมูลของตนเองและหน่วยงานใต้สังกัด</div>
  </div>
</div>
</body>
</html>
