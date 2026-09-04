<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MemberController;
use App\Http\Controllers\Web\NodeController;
use App\Http\Controllers\Web\PosController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\RedeemDeskController;
use App\Http\Controllers\Web\AdminClaimController;
use App\Http\Controllers\Web\ClaimController;
use App\Http\Controllers\Web\NotifySettingController;
use App\Http\Controllers\Web\PackageController;
use App\Http\Controllers\Web\ScanController;
use App\Http\Controllers\Web\SecurityLogController;
use App\Http\Controllers\Web\SubscriptionController;
use App\Http\Controllers\Web\ShopSettingController;
use App\Http\Controllers\Web\SocialAuthController;
use App\Http\Controllers\Web\StockCountController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\TransferWebController;
use App\Http\Controllers\Web\SetupController;
use App\Http\Controllers\Web\AdminSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

/*
|--------------------------------------------------------------------------
| หน้าลูกค้าปลายทาง (สาธารณะ ไม่ต้อง login)
|--------------------------------------------------------------------------
| QR บนสินค้าพิมพ์ลิงก์ /s/{token} ลูกค้าสแกนแล้วมาที่นี่
*/
Route::name('scan.')->group(function () {
    Route::get('/s/{token}', [ScanController::class, 'form'])->name('token');
    Route::get('/scan', [ScanController::class, 'form'])->name('form');
    Route::post('/scan', [ScanController::class, 'submit'])
        ->middleware('throttle:20,1')->name('submit');
    Route::get('/scan/result', [ScanController::class, 'result'])->name('result');
    Route::get('/scan/wallet', [ScanController::class, 'wallet'])->name('wallet');
    Route::post('/scan/forget', [ScanController::class, 'forget'])->name('forget');
    Route::get('/scan/statement', [ScanController::class, 'statement'])
        ->middleware('throttle:6,1')->name('statement');
});

/*
|--------------------------------------------------------------------------
| เข้าสู่ระบบด้วย LINE / Google (ลูกค้าปลายทาง)
|--------------------------------------------------------------------------
*/
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['line', 'google'])
    ->middleware('throttle:20,1')
    ->name('social.redirect');

Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['line', 'google'])
    ->middleware('throttle:20,1')
    ->name('social.callback');

/*
|--------------------------------------------------------------------------
| หน้าร้านสาธารณะ — ลูกค้าเปิดดูได้โดยไม่ต้องล็อกอิน
|--------------------------------------------------------------------------
*/
// ใช้ prefix /s/ แยกจาก /shop/* ที่เป็นหน้าตั้งค่า
// ถ้าใช้ /shop/{slug} จะไปดักคำว่า settings/preview/rewards ทำให้หน้าตั้งค่า 404
Route::get('/r/{slug}', [\App\Http\Controllers\Web\StorefrontController::class, 'show'])
    ->name('storefront');

/*
|--------------------------------------------------------------------------
| QR ร้านค้า — ลูกค้าสแกนเพื่อแลกของรางวัล
|--------------------------------------------------------------------------
| QR ติดหน้าร้าน ลูกค้าสแกนแล้วเปิดหน้าแลกของรางวัลของร้านนั้น
| ไม่ต้อง login ระบบ แต่กรอกเบอร์โทรเพื่อยืนยันตัว
*/
Route::prefix('shop-qr')->name('shop-qr.')->group(function () {
    Route::get('/{token}', [\App\Http\Controllers\Web\ShopQrController::class, 'show'])
        ->name('show');
    Route::post('/{token}/redeem', [\App\Http\Controllers\Web\ShopQrController::class, 'redeem'])
        ->middleware('throttle:10,1')
        ->name('redeem');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
| ทุกหน้าอยู่ภายใต้ขอบเขตสายงานของผู้ใช้เสมอ (visibleNodeIds)
*/
Route::middleware(['auth', 'active.user'])->group(function () {

    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // บัญชีของฉัน
    Route::get('/profile', fn () => view('auth.profile'))->name('profile');
    Route::put('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password');

    // จัดการสมาชิกในสายงาน
    Route::prefix('members')->name('members.')->group(function () {
        Route::get('/', [MemberController::class, 'index'])->name('index');
        Route::get('/create', [MemberController::class, 'create'])->name('create');
        Route::post('/', [MemberController::class, 'store'])->name('store');
        Route::get('/{member}/edit', [MemberController::class, 'edit'])->name('edit');
        Route::put('/{member}', [MemberController::class, 'update'])->name('update');
        Route::patch('/{member}/toggle', [MemberController::class, 'toggleStatus'])->name('toggle');
        Route::patch('/{member}/reset-password', [MemberController::class, 'resetPassword'])->name('reset-password');
        Route::delete('/{member}', [MemberController::class, 'destroy'])->name('destroy');
    });

    // จัดการหน่วยงานลูก
    Route::prefix('nodes')->name('nodes.')->group(function () {
        Route::get('/', [NodeController::class, 'index'])->name('index');
        Route::get('/create', [NodeController::class, 'create'])->name('create');
        Route::post('/', [NodeController::class, 'store'])->name('store');
        Route::get('/{node}', [NodeController::class, 'show'])->name('show');
        Route::get('/{node}/edit', [NodeController::class, 'edit'])->name('edit');
        Route::put('/{node}', [NodeController::class, 'update'])->name('update');
        Route::delete('/{node}', [NodeController::class, 'destroy'])->name('destroy');
    });

    // การแจ้งเตือนของตัวเอง (ทุกบทบาท)
    Route::prefix('profile/notify')->name('profile.notify')->group(function () {
        Route::get('/', [NotifySettingController::class, 'index']);
        Route::post('/link', [NotifySettingController::class, 'linkLine'])->name('.link');
        Route::get('/callback', [NotifySettingController::class, 'linkCallback'])->name('.callback');
        Route::patch('/{link}/toggle', [NotifySettingController::class, 'toggle'])->name('.toggle');
        Route::delete('/{link}', [NotifySettingController::class, 'unlink'])->name('.unlink');
    });

    // สมาชิกร้านค้า (ตัวแทนขึ้นไป)
    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index'])->name('index');
        Route::get('/create', [SubscriptionController::class, 'create'])->name('create');
        Route::post('/', [SubscriptionController::class, 'store'])->name('store');
        Route::get('/{subscription}', [SubscriptionController::class, 'show'])->name('show');
        Route::patch('/{subscription}/pay', [SubscriptionController::class, 'confirmPayment'])->name('pay');
        Route::patch('/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
        Route::patch('/{subscription}/renew', [SubscriptionController::class, 'renew'])->name('renew');
    });

    // แพ็กเกจและค่าตั้งค่า (เจ้าของระบบ)
    Route::prefix('admin/packages')->name('admin.packages.')->group(function () {
        Route::get('/', [PackageController::class, 'index'])->name('index');
        Route::post('/', [PackageController::class, 'store'])->name('store');
        Route::put('/{package}', [PackageController::class, 'update'])->name('update');
        Route::patch('/{package}/toggle', [PackageController::class, 'toggle'])->name('toggle');
        Route::patch('/point-value', [PackageController::class, 'updatePointValue'])->name('point-value');
        Route::patch('/setting', [PackageController::class, 'updateSetting'])->name('setting');
    });

    // ศูนย์ความปลอดภัย (เจ้าของระบบ)
    Route::prefix('admin/security')->name('admin.security.')->group(function () {
        Route::get('/', [SecurityLogController::class, 'index'])->name('index');
        Route::get('/events', [SecurityLogController::class, 'events'])->name('events');
        Route::get('/audits', [SecurityLogController::class, 'audits'])->name('audits');
        Route::get('/logins', [SecurityLogController::class, 'logins'])->name('logins');
        Route::get('/errors', [SecurityLogController::class, 'errors'])->name('errors');
        Route::patch('/events/{event}/review', [SecurityLogController::class, 'reviewEvent'])->name('review');
        Route::patch('/alerts/{alert}', [SecurityLogController::class, 'handleAlert'])->name('alert');
        Route::post('/block', [SecurityLogController::class, 'block'])->name('block');
        Route::patch('/blocked/{blocked}/unblock', [SecurityLogController::class, 'unblock'])->name('unblock');
    });

    // เบิกเงินคืน (ร้านค้า)
    Route::prefix('claims')->name('claims.')->group(function () {
        Route::get('/', [ClaimController::class, 'index'])->name('index');
        Route::post('/', [ClaimController::class, 'store'])->name('store');
        Route::get('/{claim}', [ClaimController::class, 'show'])->name('show');
        Route::patch('/{claim}/submit', [ClaimController::class, 'submit'])->name('submit');
        Route::delete('/{claim}', [ClaimController::class, 'destroy'])->name('destroy');
    });

    // อนุมัติจ่ายเงิน (เจ้าของระบบ)
    Route::prefix('admin/claims')->name('admin.claims.')->group(function () {
        Route::get('/', [AdminClaimController::class, 'index'])->name('index');
        Route::get('/{claim}', [AdminClaimController::class, 'show'])->name('show');
        Route::patch('/{claim}/approve', [AdminClaimController::class, 'approve'])->name('approve');
        Route::patch('/{claim}/pay', [AdminClaimController::class, 'pay'])->name('pay');
        Route::patch('/{claim}/reject', [AdminClaimController::class, 'reject'])->name('reject');
    });

    // ตั้งค่าหน้าร้าน (เจ้าของร้าน)
    Route::prefix('shop')->name('shop.')->group(function () {
        Route::get('/settings', [ShopSettingController::class, 'edit'])->name('settings');
        Route::put('/settings', [ShopSettingController::class, 'update'])->name('update');
        Route::get('/preview', [ShopSettingController::class, 'preview'])->name('preview');
        Route::get('/qr-download', [ShopSettingController::class, 'downloadShopQr'])->name('qr-download');
        Route::post('/rewards', [ShopSettingController::class, 'storeReward'])->name('rewards.store');
        Route::patch('/rewards/{reward}/toggle', [ShopSettingController::class, 'toggleReward'])->name('rewards.toggle');
        Route::delete('/rewards/{reward}', [ShopSettingController::class, 'destroyReward'])->name('rewards.destroy');
    });

    // เคาน์เตอร์รับแลกแต้ม (ร้านค้า/ผู้ขาย)
    Route::prefix('redeem')->name('redeem.')->group(function () {
        Route::get('/', [RedeemDeskController::class, 'index'])->name('desk');
        Route::post('/', [RedeemDeskController::class, 'store'])
            ->middleware('throttle:30,1')->name('store');
        Route::get('/history', [RedeemDeskController::class, 'history'])->name('history');
        Route::get('/receipt/{redemption}', [RedeemDeskController::class, 'receipt'])->name('receipt');
    });

    // POS — เปิดบิลขาย
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::post('/', [PosController::class, 'store'])->name('store');
        Route::get('/history', [PosController::class, 'history'])->name('history');
        Route::get('/receipt/{sale}', [PosController::class, 'receipt'])->name('receipt');
        Route::patch('/{sale}/void', [PosController::class, 'void'])->name('void');
    });

    // ใบโอนสินค้า
    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::get('/', [TransferWebController::class, 'index'])->name('index');
        Route::get('/create', [TransferWebController::class, 'create'])->name('create');
        Route::post('/', [TransferWebController::class, 'store'])->name('store');
        Route::get('/{transfer}', [TransferWebController::class, 'show'])->name('show');
        Route::patch('/{transfer}/approve', [TransferWebController::class, 'approve'])->name('approve');
        Route::patch('/{transfer}/reject',  [TransferWebController::class, 'reject'])->name('reject');
        Route::patch('/{transfer}/ship',    [TransferWebController::class, 'ship'])->name('ship');
        Route::patch('/{transfer}/receive', [TransferWebController::class, 'receive'])->name('receive');
        Route::patch('/{transfer}/cancel',  [TransferWebController::class, 'cancel'])->name('cancel');
    });

    // สินค้า / ล็อต / QR
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::get('/create', [ProductController::class, 'create'])->name('create');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::get('/quick-stock', [\App\Http\Controllers\Web\QuickStockController::class, 'index'])->name('quick-stock');
        Route::get('/quick-stock/lookup', [\App\Http\Controllers\Web\QuickStockController::class, 'lookup'])->name('quick-stock.lookup');
        Route::post('/quick-stock/add', [\App\Http\Controllers\Web\QuickStockController::class, 'addLot'])->name('quick-stock.add');
        Route::get('/{product}', [ProductController::class, 'show'])->name('show');
        Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
        Route::put('/{product}', [ProductController::class, 'update'])->name('update');
        Route::post('/{product}/lots', [ProductController::class, 'storeLot'])->name('lots.store');
        Route::post('/{product}/lots/{lot}/qr', [ProductController::class, 'generateQr'])->name('lots.qr');
        Route::get('/{product}/lots/{lot}/qr.csv', [ProductController::class, 'qrCsv'])->name('lots.csv');
    });

    // นับสต๊อกและปรับยอด
    Route::get('/stock/count', [StockCountController::class, 'index'])->name('stock.count');
    Route::post('/stock/count', [StockCountController::class, 'store'])->name('stock.count.store');

    // ลูกค้า คะแนน ของรางวัล
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/rewards', [CustomerController::class, 'rewards'])->name('rewards');
        Route::post('/rewards', [CustomerController::class, 'storeReward'])->name('rewards.store');
        Route::patch('/rewards/{reward}', [CustomerController::class, 'updateReward'])->name('rewards.update');
        Route::patch('/redemptions/{redemption}/ship', [CustomerController::class, 'shipRedemption'])->name('redemptions.ship');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::patch('/{customer}/toggle', [CustomerController::class, 'toggle'])->name('toggle');
        Route::post('/{customer}/points', [CustomerController::class, 'adjustPoints'])->name('points');
    });

    // รายงาน
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/summary', [ReportController::class, 'summary'])->name('summary');
        Route::get('/stock', [ReportController::class, 'stock'])->name('stock');
        Route::get('/movements', [ReportController::class, 'movements'])->name('movements');
        Route::get('/qr', [ReportController::class, 'qr'])->name('qr');
        Route::get('/export/{type}', [ReportController::class, 'export'])->name('export');
    });

    // ตั้งค่าระบบ (Admin เท่านั้น)
    Route::prefix('admin/settings')->name('admin.settings.')->group(function () {
        Route::get('/', [AdminSettingsController::class, 'index'])->name('index');
        Route::post('/', [AdminSettingsController::class, 'update'])->name('update');
    });

    // คู่มือออนไลน์
    Route::get('/help', [\App\Http\Controllers\Web\HelpController::class, 'show'])->name('help');
});

/*
|--------------------------------------------------------------------------
| Setup Wizard — ติดตั้งระบบครั้งแรก
|--------------------------------------------------------------------------
*/
Route::prefix('setup')->name('setup.')->group(function () {
    Route::get('/', [SetupController::class, 'wizard'])->name('wizard');
    Route::get('/env', [SetupController::class, 'createEnv'])->name('createEnv');
    Route::get('/database', [SetupController::class, 'database'])->name('database');
    Route::post('/database', [SetupController::class, 'saveDatabase'])->name('saveDatabase');
    Route::get('/app', [SetupController::class, 'appConfig'])->name('app');
    Route::post('/app', [SetupController::class, 'saveAppConfig'])->name('saveAppConfig');
    Route::get('/admin', [SetupController::class, 'admin'])->name('admin');
    Route::post('/admin', [SetupController::class, 'saveAdmin'])->name('saveAdmin');
    Route::get('/social', [SetupController::class, 'social'])->name('social');
    Route::post('/social', [SetupController::class, 'saveSocial'])->name('saveSocial');
    Route::get('/install', [SetupController::class, 'install'])->name('install');
    Route::post('/install', [SetupController::class, 'runInstall'])->name('runInstall');
});
