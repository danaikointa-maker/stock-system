<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Enums\OrgLevel;
use App\Enums\Role;
use App\Models\Category;
use App\Models\OrgNode;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Unit;
use App\Models\User;
use App\Services\QrScanService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DemoSeeder extends Seeder
{
    public function run(StockService $stock, QrScanService $qr): void
    {
        // ---- สายงาน 6 ระดับ ----
        $hq    = OrgNode::create(['level_id' => OrgLevel::SystemOwner,   'code' => 'HQ',      'name' => 'เจ้าของระบบ (HQ)']);
        $wh    = OrgNode::create(['parent_id' => $hq->id,    'level_id' => OrgLevel::MainWarehouse, 'code' => 'WH-BKK',  'name' => 'คลังใหญ่ กรุงเทพฯ']);
        $swh   = OrgNode::create(['parent_id' => $wh->id,    'level_id' => OrgLevel::SubWarehouse,  'code' => 'SWH-NT',  'name' => 'คลังย่อย นนทบุรี']);
        $agent = OrgNode::create(['parent_id' => $swh->id,   'level_id' => OrgLevel::Agent,         'code' => 'AG-001',  'name' => 'ตัวแทนขาย สมชาย']);
        $shop  = OrgNode::create(['parent_id' => $agent->id, 'level_id' => OrgLevel::Shop,          'code' => 'SH-001',  'name' => 'ร้านค้า ป้าแดง']);
        $seller= OrgNode::create(['parent_id' => $shop->id,  'level_id' => OrgLevel::Seller,        'code' => 'SL-001',  'name' => 'ผู้ขาย นายเอ']);

        // ---- ผู้ใช้ครบทั้ง 6 ระดับ (รหัสผ่านเดียวกันหมด: password) ----
        foreach ([
            [$hq,     'ผู้ดูแลระบบ',      'admin@demo.test',  Role::SystemAdmin],
            [$wh,     'ผจก.คลังใหญ่',     'wh@demo.test',     Role::WarehouseAdmin],
            [$swh,    'ผจก.คลังย่อย',     'swh@demo.test',    Role::WarehouseAdmin],
            [$agent,  'สมชาย (ตัวแทน)',   'agent@demo.test',  Role::AgentUser],
            [$shop,   'ป้าแดง (ร้านค้า)', 'shop@demo.test',   Role::ShopUser],
            [$seller, 'นายเอ (ผู้ขาย)',   'seller@demo.test', Role::SellerUser],
        ] as [$node, $name, $email, $role]) {
            User::create([
                'org_node_id' => $node->id,
                'name'        => $name,
                'email'       => $email,
                'password'    => 'password',
                'role'        => $role,
                'is_active'   => true,
            ]);
        }

        // ---- สินค้า ----
        $cat  = Category::create(['name' => 'เครื่องดื่ม']);
        $unit = Unit::create(['name' => 'ชิ้น']);

        $product = Product::create([
            'sku' => 'SKU-001', 'name' => 'น้ำดื่มวิตามิน 500ml',
            'category_id' => $cat->id, 'unit_id' => $unit->id, 'pack_size' => 12,
            'cost_price' => 8, 'retail_price' => 15, 'points_per_unit' => 5,
            'track_serial' => true, 'has_expiry' => true,
        ]);

        foreach ([2 => 8.50, 3 => 9.50, 4 => 10.50, 5 => 12.00, 6 => 13.00] as $level => $price) {
            $product->levelPrices()->create(['level_id' => $level, 'price' => $price]);
        }

        // ---- ล็อต + รับเข้าคลังใหญ่ ----
        $lot = ProductLot::create([
            'product_id' => $product->id, 'lot_no' => 'L2601',
            'mfg_date' => now()->subDays(10), 'expiry_date' => now()->addYear(),
            'qty_produced' => 1000,
        ]);

        DB::transaction(fn () => $stock->increase(
            nodeId: $wh->id, productId: $product->id, qty: 1000,
            type: MovementType::Receipt, lotId: $lot->id,
            unitCost: 8.0, note: 'รับเข้าจากโรงงาน',
        ));

        // ---- สร้าง QR 1000 ชิ้น (เก็บรหัสใต้ฟิล์มไว้ส่งโรงพิมพ์) ----
        $secrets = $qr->generateForLot($lot, 1000, $wh->id);

        $csv = "serial_no,qr_token,secret,url\n";
        foreach ($secrets as $s) {
            $csv .= "{$s['serial_no']},{$s['qr_token']},{$s['secret']},".url('/s/'.$s['qr_token'])."\n";
        }
        File::put(storage_path('app/qr_print_' . $lot->lot_no . '.csv'), $csv);

        // ใช้ภาษาอังกฤษเมื่อสั่งผ่านสคริปต์ติดตั้งบน Windows
        // เพราะ Command Prompt รุ่นเก่าแสดงภาษาไทยเป็นตัวขยะ
        if (getenv('ST_LANG') === 'en') {
            $this->command->info('Demo data ready. QR print file: storage/app/qr_print_L2601.csv');
            $this->command->info('6 accounts created: admin@ / wh@ / swh@ / agent@ / shop@ / seller@ demo.test  -  password: password');
        } else {
            $this->command->info('Demo data พร้อมแล้ว — ไฟล์สั่งพิมพ์ QR อยู่ที่ storage/app/qr_print_L2601.csv');
            $this->command->info('เข้าระบบได้ 6 บัญชี: admin@ / wh@ / swh@ / agent@ / shop@ / seller@ demo.test — รหัสผ่าน: password');
        }
    }
}
