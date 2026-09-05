<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\DocSequence;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\ManualJournal;
use App\Models\OrgNode;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\TaxInvoice;
use App\Models\WithholdingTax;
use App\Services\DocSequenceService;
use App\Services\StockLedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingTestSeeder extends Seeder
{
    public function run(DocSequenceService $docSeq): void
    {
        // ═══════════════════════════════════════════
        // 0. ดึงข้อมูลจาก DemoSeeder
        // ═══════════════════════════════════════════
        $hq   = OrgNode::where('code', 'HQ')->first();
        $wh   = OrgNode::where('code', 'WH-BKK')->first();
        $shop = OrgNode::where('code', 'SH-001')->first();
        $product = Product::where('sku', 'SKU-001')->first();
        $lot = ProductLot::where('lot_no', 'L2601')->first();

        if (!$hq || !$wh || !$shop || !$product || !$lot) {
            $this->command->error('❌ ต้องรัน DemoSeeder ก่อน: php artisan db:seed --class=DemoSeeder');
            return;
        }

        $nodeId = $shop->id;      // ร้านค้าออกเอกสาร
        $whId   = $wh->id;        // คลังใหญ่
        $adminId = 1;             // admin user

        $this->command->info('🚀 เริ่มสร้างข้อมูลทดสอบบัญชี...');

        // ═══════════════════════════════════════════
        // 1. สร้างผังบัญชี (Chart of Accounts)
        // ═══════════════════════════════════════════
        $this->command->info('📋 1/9 สร้างผังบัญชี...');
        $accounts = $this->seedAccounts($nodeId);

        // ═══════════════════════════════════════════
        // 2. ใบเสนอราคา → แปลงเป็นบิลเรียกเก็บ
        // ═══════════════════════════════════════════
        $this->command->info('📋 2/9 สร้างใบเสนอราคา + บิลเรียกเก็บ...');

        $qtNo = $docSeq->next('QT', $nodeId);
        $quotation = Quotation::create([
            'org_node_id'     => $nodeId,
            'doc_no'          => $qtNo,
            'customer_name'   => 'บริษัท ทดสอบ จำกัด',
            'customer_address' => '123 ถ.ทดสอบ กรุงเทพฯ 10100',
            'customer_tax_id' => '0-1234-56789-01-2',
            'issue_date'      => now()->subDays(15),
            'valid_until'     => now()->addDays(15),
            'subtotal'        => 3000.00,
            'vat_rate'        => 7,
            'vat_amount'      => 210.00,
            'total'           => 3210.00,
            'status'          => 'converted',
            'created_by'      => $adminId,
        ]);

        $quotation->items()->create([
            'description' => 'น้ำดื่มวิตามิน 500ml (200 ขวด)',
            'qty'         => 200,
            'unit_price'  => 15.00,
            'line_total'  => 3000.00,
        ]);

        // แปลงเป็นบิลเรียกเก็บ
        $invNo = $docSeq->next('INV', $nodeId);
        $invoice = Invoice::create([
            'org_node_id'     => $nodeId,
            'invoice_no'      => $invNo,
            'customer_name'   => 'บริษัท ทดสอบ จำกัด',
            'customer_address' => '123 ถ.ทดสอบ กรุงเทพฯ 10100',
            'customer_tax_id' => '0-1234-56789-01-2',
            'invoice_date'    => now()->subDays(10),
            'due_date'        => now()->addDays(20),
            'subtotal'        => 3000.00,
            'vat_rate'        => 7,
            'vat_amount'      => 210.00,
            'total'           => 3210.00,
            'balance'         => 3210.00,
            'status'          => 'partial',
            'notes'           => 'แปลงจากใบเสนอราคา: ' . $qtNo,
            'created_by'      => $adminId,
        ]);

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'น้ำดื่มวิตามิน 500ml (200 ขวด)',
            'qty'         => 200,
            'unit_price'  => 15.00,
            'amount'      => 3000.00,
        ]);

        $quotation->update(['converted_invoice_id' => $invoice->id]);

        // ═══════════════════════════════════════════
        // 3. ใบส่งของ → ส่ง (ตัดสต๊อก + journal)
        // ═══════════════════════════════════════════
        $this->command->info('🚚 3/9 สร้างใบส่งของ + ตัดสต๊อก...');

        $dlvNo = $docSeq->next('DLV', $nodeId);
        $delivery = DeliveryNote::create([
            'org_node_id'      => $nodeId,
            'doc_no'           => $dlvNo,
            'sale_id'          => null,
            'customer_name'    => 'บริษัท ทดสอบ จำกัด',
            'delivery_address' => '123 ถ.ทดสอบ กรุงเทพฯ 10100',
            'recipient_name'   => 'คุณสมศักดิ์',
            'recipient_phone'  => '081-234-5678',
            'status'           => 'delivered',
            'total_qty'        => 200,
            'total_amount'     => 3000.00,
            'shipped_at'       => now()->subDays(8),
            'delivered_at'     => now()->subDays(7),
            'tracking_no'      => 'TH1234567890',
            'carrier'          => 'Kerry Express',
            'created_by'       => $adminId,
        ]);

        $delivery->items()->create([
            'product_id' => $product->id,
            'lot_id'     => $lot->id,
            'qty'        => 200,
            'unit_cost'  => 8.00,
            'unit_price' => 15.00,
            'line_total' => 3000.00,
        ]);

        // ตัดสต๊อก + สร้าง journal (จำลอง StockLedgerService)
        $this->simulateDeliveryStockCut($delivery, $product, $lot, $nodeId, $adminId, $docSeq);

        // ═══════════════════════════════════════════
        // 4. รับเงินบางส่วน → ใบเสร็จ
        // ═══════════════════════════════════════════
        $this->command->info('💰 4/9 สร้างใบเสร็จรับเงิน...');

        $rcpNo = $docSeq->next('RCP', $nodeId);
        $receipt = Receipt::create([
            'receipt_no'   => $rcpNo,
            'receipt_date' => now()->subDays(5),
            'org_node_id'  => $nodeId,
            'payer_name'   => 'บริษัท ทดสอบ จำกัด',
            'payer_tax_id' => '0-1234-56789-01-2',
            'invoice_id'   => $invoice->id,
            'amount'       => 2000.00,
            'method'       => 'bank_transfer',
            'bank_ref'     => 'BBL-20260825-001',
            'notes'        => 'ชำระบางส่วน',
            'created_by'   => $adminId,
        ]);

        $invoice->update([
            'paid_amount' => 2000.00,
            'balance'     => 1210.00,
            'status'      => 'partial',
        ]);

        // ═══════════════════════════════════════════
        // 5. ใบกำกับภาษี
        // ═══════════════════════════════════════════
        $this->command->info('🧾 5/9 สร้างใบกำกับภาษี...');

        $txiNo = $docSeq->next('TXI', $nodeId);
        TaxInvoice::create([
            'tax_invoice_no' => $txiNo,
            'issue_date'     => now()->subDays(10),
            'org_node_id'    => $nodeId,
            'invoice_id'     => $invoice->id,
            'buyer_name'     => 'บริษัท ทดสอบ จำกัด',
            'buyer_address'  => '123 ถ.ทดสอบ กรุงเทพฯ 10100',
            'buyer_tax_id'   => '0-1234-56789-01-2',
            'subtotal'       => 3000.00,
            'vat_rate'       => 7,
            'vat_amount'     => 210.00,
            'total'          => 3210.00,
            'type'           => 'full',
            'created_by'     => $adminId,
        ]);

        // ═══════════════════════════════════════════
        // 6. ใบสั่งซื้อ → อนุมัติ
        // ═══════════════════════════════════════════
        $this->command->info('🛒 6/9 สร้างใบสั่งซื้อ...');

        $poNo = $docSeq->next('PO', $nodeId);
        $po = PurchaseOrder::create([
            'org_node_id'    => $nodeId,
            'po_no'          => $poNo,
            'vendor_name'    => 'บริษัท ผู้ขาย จำกัด',
            'vendor_address' => '456 ถ.ผู้ขาย กรุงเทพฯ 10200',
            'vendor_tax_id'  => '0-9876-54321-01-1',
            'order_date'     => now()->subDays(3),
            'expected_date'  => now()->addDays(7),
            'subtotal'       => 5000.00,
            'vat_rate'       => 7,
            'vat_amount'     => 350.00,
            'wht_rate'       => 3,
            'wht_amount'     => 150.00,
            'total'          => 5350.00,
            'net_total'      => 5200.00,
            'status'         => 'approved',
            'approved_by'    => $adminId,
            'approved_at'    => now()->subDays(2),
            'created_by'     => $adminId,
        ]);

        $po->items()->create([
            'product_id'  => $product->id,
            'description' => 'น้ำดื่มวิตามิน 500ml (500 ขวด)',
            'qty'         => 500,
            'unit_price'  => 10.00,
            'line_total'  => 5000.00,
        ]);

        // ═══════════════════════════════════════════
        // 7. บิลจ่าย + WHT
        // ═══════════════════════════════════════════
        $this->command->info('💸 7/9 สร้างบิลจ่าย + หัก ณ ที่จ่าย...');

        $payNo = $docSeq->next('PAY', $nodeId);
        $payment = Payment::create([
            'payment_no'   => $payNo,
            'payment_date' => now()->subDays(1),
            'org_node_id'  => $nodeId,
            'payee_name'   => 'บริษัท ผู้ขาย จำกัด',
            'payee_tax_id' => '0-9876-54321-01-1',
            'amount'       => 5350.00,  // ยอดรวม = 5,000 + VAT 350
            'method'       => 'bank_transfer',
            'bank_ref'     => 'KTB-20260830-002',
            'description'  => 'ชำระค่าสินค้า PO: ' . $poNo,
            'created_by'   => $adminId,
        ]);

        // WHT คำนวณจากยอดก่อน VAT = 5,000 × 3% = 150
        // จ่ายสุทธิ = 5,350 - 150 = 5,200
        $whtNo = $docSeq->next('WHT', $nodeId);
        WithholdingTax::create([
            'wht_no'        => $whtNo,
            'issue_date'    => now()->subDays(1),
            'org_node_id'   => $nodeId,
            'payee_name'    => 'บริษัท ผู้ขาย จำกัด',
            'payee_tax_id'  => '0-9876-54321-01-1',
            'income_amount' => 5000.00,  // ยอดก่อน VAT
            'wht_rate'      => 3,
            'wht_amount'    => 150.00,   // 5,000 × 3% = 150
            'net_amount'    => 5200.00,  // 5,350 - 150 = 5,200
            'income_type'   => 'บริการ',
            'payment_id'    => $payment->id,
            'created_by'    => $adminId,
        ]);

        // ═══════════════════════════════════════════
        // 8. ใบลดหนี้ (คืนสินค้า 20 ขวด)
        // ═══════════════════════════════════════════
        $this->command->info('↩️  8/9 สร้างใบลดหนี้ (คืนสินค้า)...');

        $cnNo = $docSeq->next('CN', $nodeId);
        $credit = CreditNote::create([
            'org_node_id'      => $nodeId,
            'doc_no'           => $cnNo,
            'type'             => 'return',
            'reason'           => 'สินค้าชำรุด 20 ขวด',
            'delivery_note_id' => $delivery->id,
            'invoice_id'       => $invoice->id,
            'customer_name'    => 'บริษัท ทดสอบ จำกัด',
            'subtotal'         => 300.00,
            'vat_amount'       => 21.00,
            'total_amount'     => 321.00,
            'status'           => 'confirmed',
            'posted_to_accounting' => true,
            'created_by'       => $adminId,
        ]);

        $credit->items()->create([
            'product_id' => $product->id,
            'lot_id'     => $lot->id,
            'qty'        => 20,
            'unit_cost'  => 8.00,
            'unit_price' => 15.00,
            'line_total' => 300.00,
        ]);

        // คืนสต๊อก 20 ชิ้น
        $this->simulateCreditStockReturn($credit, $product, $lot, $nodeId, $adminId, $docSeq);

        // อัพเดท invoice balance — ไม่หัก credit note (CN เป็นเอกสารแยก)
        // balance = total(3,210) - paid(2,000) = 1,210
        $invoice->update([
            'balance' => 1210.00,
        ]);

        // ═══════════════════════════════════════════
        // 9. ลงบัญชีแยก (Manual Journal)
        // ═══════════════════════════════════════════
        $this->command->info('📒 9/9 สร้างรายการบัญชีแยก...');

        $jvNo = $docSeq->next('JV', $nodeId);
        $journal = ManualJournal::create([
            'org_node_id' => $nodeId,
            'doc_no'      => $jvNo,
            'entry_date'  => now(),
            'description' => 'บันทึกค่าเช่าสำนักงาน เดือนนี้',
            'status'      => 'posted',
            'notes'       => 'ค่าเช่าสำนักงาน 10,000 บาท',
            'created_by'  => $adminId,
        ]);

        // Dr. ค่าเช่า 10,000 / Cr. เงินสด 10,000
        $expenseAccount = Account::where('sub_type', 'rent')->first();
        $cashAccount    = Account::where('sub_type', 'cash')->first();

        if ($expenseAccount && $cashAccount) {
            $journal->lines()->createMany([
                ['account_id' => $expenseAccount->id, 'debit' => 10000, 'credit' => 0, 'description' => 'ค่าเช่าสำนักงาน'],
                ['account_id' => $cashAccount->id,    'debit' => 0,     'credit' => 10000, 'description' => 'จ่ายค่าเช่า'],
            ]);

            // สร้าง JournalEntry จาก manual journal
            $entry = JournalEntry::create([
                'org_node_id' => $nodeId,
                'entry_date'  => now(),
                'doc_no'      => $jvNo,
                'description' => 'ค่าเช่าสำนักงาน',
                'status'      => 'posted',
                'created_by'  => $adminId,
            ]);

            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $expenseAccount->id,
                'debit'            => 10000,
                'credit'           => 0,
                'description'      => 'Dr. ค่าเช่าสำนักงาน',
            ]);
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $cashAccount->id,
                'debit'            => 0,
                'credit'           => 10000,
                'description'      => 'Cr. เงินสด',
            ]);
        }

        // ═══════════════════════════════════════════
        // สรุปผล
        // ═══════════════════════════════════════════
        $this->command->info('');
        $this->command->info('✅ สร้างข้อมูลทดสอบบัญชีเสร็จสมบูรณ์!');
        $this->command->info('');
        $this->command->table(
            ['เอกสาร', 'เลขที่', 'สถานะ'],
            [
                ['📋 ใบเสนอราคา', $qtNo, 'แปลงแล้ว'],
                ['📄 บิลเรียกเก็บ', $invNo, 'ชำระบางส่วน (คงค้าง 889)'],
                ['🚚 ใบส่งของ', $dlvNo, 'ส่งถึงแล้ว'],
                ['💰 ใบเสร็จ', $rcpNo, 'รับเงิน 2,000'],
                ['🧾 ใบกำกับภาษี', $txiNo, 'ออกแล้ว'],
                ['🛒 ใบสั่งซื้อ', $poNo, 'อนุมัติแล้ว'],
                ['💸 บิลจ่าย', $payNo, 'จ่ายแล้ว 5,350'],
                ['📋 WHT', $whtNo, 'หัก 3% = 150'],
                ['↩️ ใบลดหนี้', $cnNo, 'คืน 20 ขวด (321 บ.)'],
                ['📒 ลงบัญชีแยก', $jvNo, 'โพสต์แล้ว'],
            ]
        );

        $this->command->info('');
        $this->command->info('📊 สรุปตัวเลข:');
        $this->command->info("  ยอดขาย: 3,000 + VAT 210 = 3,210");
        $this->command->info("  รับเงิน: 2,000 (คงค้าง 889 หลังคืนสินค้า)");
        $this->command->info("  ค่าใช้จ่าย: 5,350 (จ่ายผู้ขาย) + 10,000 (ค่าเช่า) = 15,350");
        $this->command->info("  WHT หัก: 150");
        $this->command->info("  สต๊อก: ส่งออก 200 - คืน 20 = สุทธิออก 180 ชิ้น");
    }

    // ═══════════════════════════════════════════
    // Helper: สร้างผังบัญชี
    // ═══════════════════════════════════════════
    private function seedAccounts(int $nodeId): array
    {
        $chart = [
            // Assets
            ['code' => '1000', 'name' => 'เงินสด',                    'category' => 'asset',     'sub_type' => 'cash',               'opening_balance' => 100000],
            ['code' => '1100', 'name' => 'ธนาคาร',                    'category' => 'asset',     'sub_type' => 'bank',               'opening_balance' => 500000],
            ['code' => '1200', 'name' => 'ลูกหนี้การค้า',             'category' => 'asset',     'sub_type' => 'accounts_receivable', 'opening_balance' => 0],
            ['code' => '1300', 'name' => 'สินค้าคงเหลือ',             'category' => 'asset',     'sub_type' => 'inventory',          'opening_balance' => 8000],

            // Liabilities
            ['code' => '2000', 'name' => 'เจ้าหนี้การค้า',            'category' => 'liability', 'sub_type' => 'accounts_payable',   'opening_balance' => 0],
            ['code' => '2100', 'name' => 'VAT ต้องนำส่ง',             'category' => 'liability', 'sub_type' => 'vat_payable',        'opening_balance' => 0],
            ['code' => '2200', 'name' => 'หัก ณ ที่จ่าย ต้องนำส่ง',   'category' => 'liability', 'sub_type' => 'wht_payable',        'opening_balance' => 0],

            // Equity
            ['code' => '3000', 'name' => 'ทุนจดทะเบียน',              'category' => 'equity',    'sub_type' => 'capital',            'opening_balance' => 600000],
            ['code' => '3100', 'name' => 'กำไรสะสม',                  'category' => 'equity',    'sub_type' => 'retained_earnings',  'opening_balance' => 8000],

            // Revenue
            ['code' => '4000', 'name' => 'รายได้จากการขาย',           'category' => 'revenue',   'sub_type' => 'sales',              'opening_balance' => 0],
            ['code' => '4100', 'name' => 'รายได้จากการให้บริการ',     'category' => 'revenue',   'sub_type' => 'service_income',   'opening_balance' => 0],

            // Expenses
            ['code' => '5000', 'name' => 'ต้นทุนขาย',                 'category' => 'expense',   'sub_type' => 'cogs',               'opening_balance' => 0],
            ['code' => '5100', 'name' => 'เงินเดือน',                 'category' => 'expense',   'sub_type' => 'salary',             'opening_balance' => 0],
            ['code' => '5200', 'name' => 'ค่าเช่า',                   'category' => 'expense',   'sub_type' => 'rent',               'opening_balance' => 0],
            ['code' => '5300', 'name' => 'ค่าสาธารณูปโภค',            'category' => 'expense',   'sub_type' => 'utilities',          'opening_balance' => 0],
        ];

        $accounts = [];
        foreach ($chart as $item) {
            $accounts[] = Account::firstOrCreate(
                ['code' => $item['code'], 'org_node_id' => $nodeId],
                $item
            );
        }

        return $accounts;
    }

    // ═══════════════════════════════════════════
    // Helper: จำลองตัดสต๊อกเมื่อส่งของ
    // ═══════════════════════════════════════════
    private function simulateDeliveryStockCut(DeliveryNote $delivery, Product $product, ProductLot $lot, int $nodeId, int $adminId, DocSequenceService $docSeq): void
    {
        foreach ($delivery->items as $item) {
            // คำนวณ balance
            $balance = StockBalance::firstOrCreate(
                ['org_node_id' => $nodeId, 'product_id' => $product->id, 'lot_id' => $lot->id],
                ['qty_on_hand' => 1000, 'qty_reserved' => 0, 'qty_in_transit' => 0, 'reorder_point' => 50]
            );

            $newBalance = max(0, $balance->qty_on_hand - $item->qty);

            // Stock Ledger
            StockLedger::create([
                'org_node_id'     => $nodeId,
                'product_id'      => $product->id,
                'lot_id'          => $lot->id,
                'movement_type'   => 'delivery',
                'direction'       => 'out',
                'qty'             => $item->qty,
                'unit_cost'       => $item->unit_cost,
                'total_cost'      => bcmul($item->unit_cost, $item->qty, 2),
                'balance_after'   => $newBalance,
                'ref_type'        => DeliveryNote::class,
                'ref_id'          => $delivery->id,
                'journal_entry_ref' => 'DN-' . $delivery->doc_no,
                'note'            => 'ส่งของ: ' . $delivery->doc_no,
                'created_by'      => $adminId,
                'created_at'      => now(),
            ]);

            // Update balance
            $balance->qty_on_hand = $newBalance;
            $balance->save();

            // Journal Entry: Dr. COGS, Cr. Inventory
            $totalCost = bcmul($item->unit_cost, $item->qty, 2);
            $cogsAccount = Account::where('sub_type', 'cogs')->where('org_node_id', $nodeId)->first();
            $invAccount  = Account::where('sub_type', 'inventory')->where('org_node_id', $nodeId)->first();

            if ($cogsAccount && $invAccount) {
                $entry = JournalEntry::create([
                    'org_node_id' => $nodeId,
                    'entry_date'  => $delivery->shipped_at ?? now(),
                    'doc_no'      => 'DN-' . $delivery->doc_no,
                    'description' => 'บันทึกใบส่งของ: ' . $delivery->doc_no,
                    'status'      => 'posted',
                    'created_by'  => $adminId,
                ]);

                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $cogsAccount->id,
                    'debit'            => $totalCost,
                    'credit'           => 0,
                    'description'      => 'Dr. ต้นทุนขาย',
                ]);
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $invAccount->id,
                    'debit'            => 0,
                    'credit'           => $totalCost,
                    'description'      => 'Cr. สินค้าคงเหลือ',
                ]);
            }
        }
    }

    // ═══════════════════════════════════════════
    // Helper: จำลองคืนสต๊อก (ใบลดหนี้)
    // ═══════════════════════════════════════════
    private function simulateCreditStockReturn(CreditNote $credit, Product $product, ProductLot $lot, int $nodeId, int $adminId, DocSequenceService $docSeq): void
    {
        foreach ($credit->items as $item) {
            $balance = StockBalance::where('org_node_id', $nodeId)
                ->where('product_id', $product->id)
                ->where('lot_id', $lot->id)
                ->first();

            $newBalance = ($balance ? $balance->qty_on_hand : 0) + $item->qty;

            // Stock Ledger
            StockLedger::create([
                'org_node_id'     => $nodeId,
                'product_id'      => $product->id,
                'lot_id'          => $lot->id,
                'movement_type'   => 'return_in',
                'direction'       => 'in',
                'qty'             => $item->qty,
                'unit_cost'       => $item->unit_cost,
                'total_cost'      => bcmul($item->unit_cost, $item->qty, 2),
                'balance_after'   => $newBalance,
                'ref_type'        => CreditNote::class,
                'ref_id'          => $credit->id,
                'journal_entry_ref' => 'CN-' . $credit->doc_no,
                'note'            => 'คืนสินค้า: ' . $credit->doc_no,
                'created_by'      => $adminId,
                'created_at'      => now(),
            ]);

            // Update balance
            if ($balance) {
                $balance->qty_on_hand = $newBalance;
                $balance->save();
            }

            // Journal Entry: Dr. Inventory, Cr. COGS (reversal)
            $totalCost = bcmul($item->unit_cost, $item->qty, 2);
            $cogsAccount = Account::where('sub_type', 'cogs')->where('org_node_id', $nodeId)->first();
            $invAccount  = Account::where('sub_type', 'inventory')->where('org_node_id', $nodeId)->first();

            if ($cogsAccount && $invAccount) {
                $entry = JournalEntry::create([
                    'org_node_id' => $nodeId,
                    'entry_date'  => now(),
                    'doc_no'      => 'CN-' . $credit->doc_no,
                    'description' => 'ใบลดหนี้/คืนสินค้า: ' . $credit->doc_no,
                    'status'      => 'posted',
                    'created_by'  => $adminId,
                ]);

                // Dr. Inventory (เพิ่มกลับ)
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $invAccount->id,
                    'debit'            => $totalCost,
                    'credit'           => 0,
                    'description'      => 'Dr. สินค้าคงเหลือ (คืน)',
                ]);
                // Cr. COGS (กลับรายการ)
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $cogsAccount->id,
                    'debit'            => 0,
                    'credit'           => $totalCost,
                    'description'      => 'Cr. ต้นทุนขาย (คืน)',
                ]);
            }
        }
    }
}
