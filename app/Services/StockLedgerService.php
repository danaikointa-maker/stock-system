<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\CreditItem;
use App\Models\DeliveryNote;
use App\Models\DeliveryItem;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Account;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Transfer;
use App\Models\TransferItem;
use Illuminate\Support\Facades\DB;

/**
 * StockLedgerService — บันทึก Stock Movement + Journal Entry อัตโนมัติ
 * 
 * หลักการ:
 * 1. ทุก movement → สร้าง StockLedger (immutable)
 * 2. ทุก movement → สร้าง JournalEntry (double-entry) ทันที
 * 3. ห้ามลบ/แก้ไข ถ้าผิดพลาด → สร้าง reversal entry
 */
class StockLedgerService
{
    /**
     * บันทึกใบส่งของ → ตัดสต๊อก + Journal Entry
     */
    public function recordDelivery(DeliveryNote $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $entry = JournalEntry::create([
                'org_node_id'    => $delivery->org_node_id,
                'entry_date'     => $delivery->shipped_at ?? now(),
                'reference'      => 'DN-' . $delivery->doc_no,
                'description'    => 'บันทึกใบส่งของ: ' . $delivery->doc_no,
                'status'         => 'posted',
            ]);

            foreach ($delivery->items as $item) {
                // 1. บันทึก Stock Ledger (immutable)
                $this->recordMovement(
                    orgNodeId:    $delivery->org_node_id,
                    productId:    $item->product_id,
                    lotId:        $item->lot_id,
                    movementType: 'delivery',
                    direction:    'out',
                    qty:          $item->qty,
                    unitCost:     $item->unit_cost,
                    refType:      DeliveryNote::class,
                    refId:        $delivery->id,
                    journalRef:   $entry->reference,
                    note:         'ส่งของ: ' . $delivery->doc_no,
                    createdBy:    $delivery->created_by,
                );

                // 2. Journal Entry: Dr. COGS, Cr. Inventory
                $totalCost = bcmul($item->unit_cost, $item->qty, 2);

                // Dr. ต้นทุนขาย (COGS)
                $this->addJournalLine($entry->id, 'expense', 'cogs', $totalCost, 'debit');
                // Cr. สินค้าคงเหลือ (Inventory)
                $this->addJournalLine($entry->id, 'asset', 'inventory', $totalCost, 'credit');
            }

            // 3. อัพเดท StockBalance
            foreach ($delivery->items as $item) {
                $this->updateBalance($delivery->org_node_id, $item->product_id, $item->lot_id, -$item->qty);
            }
        });
    }

    /**
     * บันทึกใบลดหนี้ (คืนสินค้า) → เพิ่มสต๊อกกลับ + Reversal Journal
     */
    public function recordCreditNote(CreditNote $credit): void
    {
        DB::transaction(function () use ($credit) {
            $entry = JournalEntry::create([
                'org_node_id'    => $credit->org_node_id,
                'entry_date'     => now(),
                'reference'      => 'CN-' . $credit->doc_no,
                'description'    => 'ใบลดหนี้/คืนสินค้า: ' . $credit->doc_no . ' (' . $credit->reason . ')',
                'status'         => 'posted',
            ]);

            foreach ($credit->items as $item) {
                if ($credit->type === 'return') {
                    // คืนสินค้า → เพิ่มสต๊อกกลับ
                    $this->recordMovement(
                        orgNodeId:    $credit->org_node_id,
                        productId:    $item->product_id,
                        lotId:        $item->lot_id,
                        movementType: 'return_in',
                        direction:    'in',
                        qty:          $item->qty,
                        unitCost:     $item->unit_cost,
                        refType:      CreditNote::class,
                        refId:        $credit->id,
                        journalRef:   $entry->reference,
                        note:         'คืนสินค้า: ' . $credit->doc_no,
                        createdBy:    $credit->created_by,
                    );

                    $totalCost = bcmul($item->unit_cost, $item->qty, 2);

                    // Dr. สินค้าคงเหลือ (เพิ่มกลับ)
                    $this->addJournalLine($entry->id, 'asset', 'inventory', $totalCost, 'debit');
                    // Cr. ต้นทุนขาย (กลับรายการ)
                    $this->addJournalLine($entry->id, 'expense', 'cogs', $totalCost, 'credit');

                    // อัพเดท StockBalance
                    $this->updateBalance($credit->org_node_id, $item->product_id, $item->lot_id, $item->qty);

                    // อัพเดท delivery item returned_qty
                    if ($credit->delivery_note_id) {
                        $dItem = DeliveryItem::where('delivery_note_id', $credit->delivery_note_id)
                            ->where('product_id', $item->product_id)
                            ->first();
                        if ($dItem) {
                            $dItem->returned_qty += $item->qty;
                            $dItem->returned = $dItem->returned_qty >= $dItem->qty;
                            $dItem->save();
                        }
                    }
                }

                // Journal for revenue reversal (all types)
                if ($credit->total_amount > 0) {
                    $vat = $credit->vat_amount;
                    $net = $credit->subtotal;

                    // Dr. รายได้ (ลด)
                    $this->addJournalLine($entry->id, 'revenue', 'sales', $net, 'debit');
                    if ($vat > 0) {
                        // Dr. VAT payable (ลด)
                        $this->addJournalLine($entry->id, 'liability', 'vat_payable', $vat, 'debit');
                    }
                    // Cr. ลูกหนี้ (ลด)
                    $this->addJournalLine($entry->id, 'asset', 'accounts_receivable', $credit->total_amount, 'credit');
                }
            }

            $credit->update(['posted_to_accounting' => true]);
        });
    }

    /**
     * บันทึก Transfer → สร้าง stock movement + journal ทั้งฝั่งออกและเข้า
     */
    public function recordTransfer(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $entry = JournalEntry::create([
                'org_node_id'    => $transfer->from_node_id,
                'entry_date'     => now(),
                'reference'      => 'TRF-' . $transfer->doc_no,
                'description'    => 'โอนสินค้า: ' . $transfer->doc_no . ' (' . ($transfer->fromNode->name ?? '') . ' → ' . ($transfer->toNode->name ?? '') . ')',
                'status'         => 'posted',
            ]);

            foreach ($transfer->items as $item) {
                $qty = $item->qty_shipped ?: $item->qty_received;
                $cost = $item->unit_price ?: ($item->product->cost_price ?? 0);
                $totalCost = bcmul($cost, $qty, 2);

                //ฝั่งออก
                $this->recordMovement(
                    orgNodeId:    $transfer->from_node_id,
                    productId:    $item->product_id,
                    lotId:        $item->lot_id,
                    movementType: 'transfer_out',
                    direction:    'out',
                    qty:          $qty,
                    unitCost:     $cost,
                    refType:      Transfer::class,
                    refId:        $transfer->id,
                    journalRef:   $entry->reference,
                    note:         'โอนออก: ' . $transfer->doc_no,
                    createdBy:    $transfer->requested_by,
                );
                $this->updateBalance($transfer->from_node_id, $item->product_id, $item->lot_id, -$qty);

                // ฝั่งเข้า
                $this->recordMovement(
                    orgNodeId:    $transfer->to_node_id,
                    productId:    $item->product_id,
                    lotId:        $item->lot_id,
                    movementType: 'transfer_in',
                    direction:    'in',
                    qty:          $qty,
                    unitCost:     $cost,
                    refType:      Transfer::class,
                    refId:        $transfer->id,
                    journalRef:   $entry->reference,
                    note:         'โอนเข้า: ' . $transfer->doc_no,
                    createdBy:    $transfer->requested_by,
                );
                $this->updateBalance($transfer->to_node_id, $item->product_id, $item->lot_id, $qty);

                // Journal: Dr. Inventory (ปลายทาง), Cr. Inventory (ต้นทาง)
                $this->addJournalLine($entry->id, 'asset', 'inventory', $totalCost, 'debit', $transfer->to_node_id);
                $this->addJournalLine($entry->id, 'asset', 'inventory', $totalCost, 'credit', $transfer->from_node_id);
            }
        });
    }

    /**
     * บันทึกการขาย → ตัดสต๊อก + Journal Entry
     */
    public function recordSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $entry = JournalEntry::create([
                'org_node_id'    => $sale->org_node_id,
                'entry_date'     => $sale->sold_at ?? now(),
                'reference'      => 'SAL-' . $sale->doc_no,
                'description'    => 'ขายสินค้า: ' . $sale->doc_no,
                'status'         => 'posted',
            ]);

            foreach ($sale->items as $item) {
                $cost = $item->product->cost_price ?? 0;
                $totalCost = bcmul($cost, $item->qty, 2);

                // Stock Ledger
                $this->recordMovement(
                    orgNodeId:    $sale->org_node_id,
                    productId:    $item->product_id,
                    lotId:        $item->lot_id,
                    movementType: 'sale',
                    direction:    'out',
                    qty:          $item->qty,
                    unitCost:     $cost,
                    refType:      Sale::class,
                    refId:        $sale->id,
                    journalRef:   $entry->reference,
                    note:         'ขาย: ' . $sale->doc_no,
                    createdBy:    $sale->seller_user_id,
                );

                // Journal: Dr. COGS, Cr. Inventory
                $this->addJournalLine($entry->id, 'expense', 'cogs', $totalCost, 'debit');
                $this->addJournalLine($entry->id, 'asset', 'inventory', $totalCost, 'credit');

                // Journal: Dr. Cash/AR, Cr. Revenue
                $this->addJournalLine($entry->id, 'asset', 'cash', $item->line_total, 'debit');
                $this->addJournalLine($entry->id, 'revenue', 'sales', $item->line_total, 'credit');

                // Balance
                $this->updateBalance($sale->org_node_id, $item->product_id, $item->lot_id, -$item->qty);
            }
        });
    }

    /**
     * รับสินค้าเข้าคลัง (Purchase/Receipt)
     */
    public function recordReceipt(int $orgNodeId, int $productId, ?int $lotId, int $qty, float $unitCost, ?string $refType = null, ?int $refId = null, ?string $note = null, ?int $createdBy = null): void
    {
        DB::transaction(function () use ($orgNodeId, $productId, $lotId, $qty, $unitCost, $refType, $refId, $note, $createdBy) {
            $totalCost = bcmul($unitCost, $qty, 2);

            $entry = JournalEntry::create([
                'org_node_id' => $orgNodeId,
                'entry_date'  => now(),
                'reference'   => 'RCPT-' . now()->format('ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                'description' => 'รับสินค้าเข้าคลัง' . ($note ? ': ' . $note : ''),
                'status'      => 'posted',
            ]);

            $this->recordMovement(
                orgNodeId:    $orgNodeId,
                productId:    $productId,
                lotId:        $lotId,
                movementType: 'receipt',
                direction:    'in',
                qty:          $qty,
                unitCost:     $unitCost,
                refType:      $refType,
                refId:        $refId,
                journalRef:   $entry->reference,
                note:         $note ?? 'รับเข้าคลัง',
                createdBy:    $createdBy,
            );

            // Dr. Inventory
            $this->addJournalLine($entry->id, 'asset', 'inventory', $totalCost, 'debit');
            // Cr. AP (เจ้าหนี้)
            $this->addJournalLine($entry->id, 'liability', 'accounts_payable', $totalCost, 'credit');

            $this->updateBalance($orgNodeId, $productId, $lotId, $qty);
        });
    }

    // ═══════════════════════════════════════════════════
    //  INTERNAL HELPERS
    // ═══════════════════════════════════════════════════

    private function recordMovement(
        int $orgNodeId, int $productId, ?int $lotId,
        string $movementType, string $direction, int $qty, float $unitCost,
        ?string $refType = null, ?int $refId = null, ?string $journalRef = null,
        ?string $note = null, ?int $createdBy = null
    ): StockLedger {
        // คำนวณ balance
        $balance = StockBalance::where('org_node_id', $orgNodeId)
            ->where('product_id', $productId)
            ->where('lot_id', $lotId)
            ->first();
        $currentBalance = $balance ? $balance->qty_on_hand : 0;
        $newBalance = $direction === 'in' ? $currentBalance + $qty : $currentBalance - $qty;

        return StockLedger::create([
            'org_node_id'     => $orgNodeId,
            'product_id'      => $productId,
            'lot_id'          => $lotId,
            'movement_type'   => $movementType,
            'direction'       => $direction,
            'qty'             => $qty,
            'unit_cost'       => $unitCost,
            'total_cost'      => bcmul($unitCost, $qty, 2),
            'balance_after'   => $newBalance,
            'ref_type'        => $refType,
            'ref_id'          => $refId,
            'journal_entry_ref' => $journalRef,
            'note'            => $note,
            'created_by'      => $createdBy,
            'created_at'      => now(),
        ]);
    }

    private function updateBalance(int $orgNodeId, int $productId, ?int $lotId, int $qtyChange): void
    {
        $balance = StockBalance::firstOrCreate(
            ['org_node_id' => $orgNodeId, 'product_id' => $productId, 'lot_id' => $lotId],
            ['qty_on_hand' => 0, 'qty_reserved' => 0, 'qty_in_transit' => 0, 'reorder_point' => 0]
        );
        $balance->qty_on_hand = max(0, $balance->qty_on_hand + $qtyChange);
        $balance->save();
    }

    private function addJournalLine(int $entryId, string $category, string $subType, float $amount, string $type, ?int $nodeId = null): void
    {
        $account = Account::where('category', $category)
            ->where('sub_type', $subType)
            ->first();

        if ($account) {
            JournalLine::create([
                'journal_entry_id' => $entryId,
                'account_id'       => $account->id,
                'debit'            => $type === 'debit' ? $amount : 0,
                'credit'           => $type === 'credit' ? $amount : 0,
                'description'      => ($type === 'debit' ? 'Dr.' : 'Cr.') . ' ' . $account->name,
            ]);
        }
    }

    // ═══════════════════════════════════════════════════
    //  VERIFICATION — ตรวจยอดตรง
    // ═══════════════════════════════════════════════════

    /**
     * ตรวจสอบว่า Stock Ledger สมดุลกับ StockBalance
     * @return array ['ok' => bool, 'mismatches' => array]
     */
    public function verifyBalances(?int $orgNodeId = null): array
    {
        $query = StockBalance::query();
        if ($orgNodeId) $query->where('org_node_id', $orgNodeId);

        $mismatches = [];
        foreach ($query->get() as $bal) {
            $ledgerBalance = StockLedger::where('org_node_id', $bal->org_node_id)
                ->where('product_id', $bal->product_id)
                ->where('lot_id', $bal->lot_id)
                ->selectRaw("SUM(CASE WHEN direction='in' THEN qty ELSE -qty END) as calc_balance")
                ->value('calc_balance') ?? 0;

            if ((int) $ledgerBalance !== $bal->qty_on_hand) {
                $mismatches[] = [
                    'node_id'       => $bal->org_node_id,
                    'product_id'    => $bal->product_id,
                    'lot_id'        => $bal->lot_id,
                    'balance_qty'   => $bal->qty_on_hand,
                    'ledger_qty'    => (int) $ledgerBalance,
                    'diff'          => $bal->qty_on_hand - (int) $ledgerBalance,
                ];
            }
        }

        return ['ok' => empty($mismatches), 'mismatches' => $mismatches];
    }

    /**
     * ตรวจสอบว่า Journal Entries สมดุล (debit = credit)
     */
    public function verifyJournals(?int $orgNodeId = null): array
    {
        $query = JournalEntry::query()->where('status', 'posted');
        if ($orgNodeId) $query->where('org_node_id', $orgNodeId);

        $mismatches = [];
        foreach ($query->get() as $entry) {
            $lines = JournalLine::where('journal_entry_id', $entry->id)->get();
            $totalDebit  = $lines->sum('debit');
            $totalCredit = $lines->sum('credit');

            if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
                $mismatches[] = [
                    'entry_id'     => $entry->id,
                    'reference'    => $entry->reference,
                    'total_debit'  => $totalDebit,
                    'total_credit' => $totalCredit,
                    'diff'         => bcsub($totalDebit, $totalCredit, 2),
                ];
            }
        }

        return ['ok' => empty($mismatches), 'mismatches' => $mismatches];
    }
}
