<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\QrStatus;
use App\Enums\TransferStatus;
use App\Exceptions\InvalidTransferException;
use App\Models\OrgNode;
use App\Models\Product;
use App\Models\ProductQrcode;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * การโอนสินค้าระหว่างระดับชั้น
 *
 * Flow: draft -> pending_approve -> approved -> shipped -> received
 *
 * | ขั้นตอน  | ต้นทาง                          | ปลายทาง               |
 * |----------|---------------------------------|-----------------------|
 * | approved | reserved +N                     | -                     |
 * | shipped  | on_hand -N, reserved -N (out)   | in_transit +N         |
 * | received | -                               | in_transit -N, on_hand +N (in) |
 */
class TransferService
{
    public function __construct(private StockService $stock) {}

    /**
     * สร้างใบโอน
     *
     * @param array $items [['product_id'=>1,'lot_id'=>null,'qty'=>10], ...]
     */
    public function create(
        OrgNode $from,
        OrgNode $to,
        array $items,
        string $type = 'allocation',
        ?string $note = null,
    ): Transfer {
        $this->assertValidDirection($from, $to, $type);

        return DB::transaction(function () use ($from, $to, $items, $type, $note) {
            $transfer = Transfer::create([
                'doc_no'       => Transfer::nextDocNo(),
                'from_node_id' => $from->id,
                'to_node_id'   => $to->id,
                'type'         => $type,
                'status'       => TransferStatus::PendingApprove,
                'requested_by' => Auth::id(),
                'note'         => $note,
            ]);

            $totalQty = 0;
            $totalAmount = 0.0;

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty = (int) $item['qty'];
                $price = $item['unit_price'] ?? $product->priceForLevel($to->level_id, $qty);

                // ถ้าไม่ระบุล็อต ต้องกระจายตาม FEFO ตั้งแต่ตอนสร้างใบ
                // มิฉะนั้นการจอง/ตัดสต๊อกจะไปอ้างแถวยอดคงเหลือของ lot_id = null ซึ่งเป็น 0 เสมอ
                $allocations = isset($item['lot_id'])
                    ? [['lot_id' => $item['lot_id'], 'qty' => $qty]]
                    : $this->stock->pickLotsFefo($from->id, $product->id, $qty);

                foreach ($allocations as $alloc) {
                    $transfer->items()->create([
                        'product_id'    => $product->id,
                        'lot_id'        => $alloc['lot_id'],
                        'qty_requested' => $alloc['qty'],
                        'unit_price'    => $price,
                    ]);

                    $totalQty += $alloc['qty'];
                    $totalAmount += $alloc['qty'] * (float) $price;
                }
            }

            $transfer->update(['total_qty' => $totalQty, 'total_amount' => $totalAmount]);

            return $transfer->load('items');
        });
    }

    /** อนุมัติ -> จองของที่ต้นทาง */
    public function approve(Transfer $transfer, User $approver): Transfer
    {
        $this->assertStatus($transfer, [TransferStatus::PendingApprove]);

        return DB::transaction(function () use ($transfer, $approver) {
            foreach ($transfer->items as $item) {
                $this->stock->reserve(
                    $transfer->from_node_id, $item->product_id,
                    $item->qty_requested, $item->lot_id
                );
            }

            $transfer->update([
                'status'      => TransferStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            return $transfer;
        });
    }

    public function reject(Transfer $transfer, User $user, ?string $reason = null): Transfer
    {
        $this->assertStatus($transfer, [TransferStatus::PendingApprove]);

        $transfer->update([
            'status'      => TransferStatus::Rejected,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'note'        => trim($transfer->note . "\nเหตุผลที่ปฏิเสธ: " . $reason),
        ]);

        return $transfer;
    }

    /**
     * ส่งของ -> ตัดสต๊อกต้นทางจริง + ปลายทางขึ้น in_transit
     *
     * @param array|null $shippedQty [transfer_item_id => qty] ถ้าส่งไม่ครบ
     */
    public function ship(Transfer $transfer, ?array $shippedQty = null): Transfer
    {
        $this->assertStatus($transfer, [TransferStatus::Approved]);

        return DB::transaction(function () use ($transfer, $shippedQty) {
            foreach ($transfer->items as $item) {
                $qty = (int) ($shippedQty[$item->id] ?? $item->qty_requested);

                if ($qty <= 0) {
                    $this->stock->releaseReservation(
                        $transfer->from_node_id, $item->product_id, $item->qty_requested, $item->lot_id
                    );
                    continue;
                }

                // ถ้าส่งน้อยกว่าที่จอง คืนส่วนต่างเข้าคลัง
                if ($qty < $item->qty_requested) {
                    $this->stock->releaseReservation(
                        $transfer->from_node_id, $item->product_id,
                        $item->qty_requested - $qty, $item->lot_id
                    );
                }

                $this->stock->decrease(
                    nodeId: $transfer->from_node_id,
                    productId: $item->product_id,
                    qty: $qty,
                    type: MovementType::TransferOut,
                    lotId: $item->lot_id,
                    refType: Transfer::class,
                    refId: $transfer->id,
                    note: "โอนไป {$transfer->toNode->code}",
                    fromReserved: true,
                );

                $this->stock->addInTransit(
                    $transfer->to_node_id, $item->product_id, $qty, $item->lot_id
                );

                $item->update(['qty_shipped' => $qty]);
            }

            $transfer->update(['status' => TransferStatus::Shipped, 'shipped_at' => now()]);

            return $transfer;
        });
    }

    /**
     * รับของ -> ปลายทางเข้าสต๊อกจริง ส่วนต่างลงเป็น damage
     *
     * @param array|null $receivedQty [transfer_item_id => qty]
     */
    public function receive(Transfer $transfer, User $receiver, ?array $receivedQty = null): Transfer
    {
        $this->assertStatus($transfer, [TransferStatus::Shipped]);

        return DB::transaction(function () use ($transfer, $receiver, $receivedQty) {
            foreach ($transfer->items as $item) {
                $qty = (int) ($receivedQty[$item->id] ?? $item->qty_shipped);

                $this->stock->clearInTransit(
                    $transfer->to_node_id, $item->product_id, $item->qty_shipped, $item->lot_id
                );

                // รับเข้าตามจำนวนที่ "ส่งออกมา" ก่อน เพื่อให้บัญชีสองฝั่งตรงกัน
                // (ต้นทางตัดออก 55 ปลายทางต้องรับเข้า 55) แล้วค่อยตัดส่วนที่หายเป็น damage
                // ถ้ารับเข้าแค่จำนวนที่นับได้แล้วตัด damage อีก จะกลายเป็นหักซ้ำสองรอบ
                if ($item->qty_shipped > 0) {
                    $this->stock->increase(
                        nodeId: $transfer->to_node_id,
                        productId: $item->product_id,
                        qty: $item->qty_shipped,
                        type: MovementType::TransferIn,
                        lotId: $item->lot_id,
                        refType: Transfer::class,
                        refId: $transfer->id,
                        unitCost: (float) $item->unit_price,
                        note: "รับจาก {$transfer->fromNode->code}",
                    );
                }

                // ของหาย/เสียหายระหว่างทาง — ตัดออกจากยอดที่เพิ่งรับเข้า
                $missing = $item->qty_shipped - $qty;
                if ($missing > 0) {
                    $this->stock->decrease(
                        nodeId: $transfer->to_node_id,
                        productId: $item->product_id,
                        qty: $missing,
                        type: MovementType::Damage,
                        lotId: $item->lot_id,
                        refType: Transfer::class,
                        refId: $transfer->id,
                        note: 'สูญหาย/เสียหายระหว่างขนส่ง',
                    );
                }

                $item->update(['qty_received' => $qty]);
            }

            // ย้ายตำแหน่ง QR ตามของที่โอน (track & trace)
            $this->moveQrcodes($transfer);

            $transfer->update([
                'status'      => TransferStatus::Received,
                'received_by' => $receiver->id,
                'received_at' => now(),
            ]);

            return $transfer;
        });
    }

    public function cancel(Transfer $transfer): Transfer
    {
        $this->assertStatus($transfer, [
            TransferStatus::Draft, TransferStatus::PendingApprove, TransferStatus::Approved,
        ]);

        return DB::transaction(function () use ($transfer) {
            if ($transfer->status === TransferStatus::Approved) {
                foreach ($transfer->items as $item) {
                    $this->stock->releaseReservation(
                        $transfer->from_node_id, $item->product_id, $item->qty_requested, $item->lot_id
                    );
                }
            }

            $transfer->update(['status' => TransferStatus::Cancelled]);

            return $transfer;
        });
    }

    /** ย้าย current_node_id ของ QR ตามจำนวนที่รับจริง */
    private function moveQrcodes(Transfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            if ($item->qty_received <= 0) {
                continue;
            }

            $ids = ProductQrcode::where('product_id', $item->product_id)
                ->where('current_node_id', $transfer->from_node_id)
                ->when($item->lot_id, fn ($q) => $q->where('lot_id', $item->lot_id))
                ->whereIn('status', [QrStatus::Created, QrStatus::InStock])
                ->limit($item->qty_received)
                ->pluck('id');

            ProductQrcode::whereIn('id', $ids)->update([
                'current_node_id' => $transfer->to_node_id,
                'status'          => QrStatus::InStock,
                'updated_at'      => now(),
            ]);
        }
    }

    /** ทิศทางที่อนุญาต: โอนลงหาลูกโดยตรง หรือคืนขึ้นหา parent */
    private function assertValidDirection(OrgNode $from, OrgNode $to, string $type): void
    {
        if ($from->id === $to->id) {
            throw new InvalidTransferException('ต้นทางและปลายทางต้องไม่ใช่โหนดเดียวกัน');
        }

        if ($type === 'return') {
            if (! $to->isDirectParentOf($from)) {
                throw new InvalidTransferException('การคืนสินค้าต้องคืนให้หน่วยงานต้นสังกัดโดยตรงเท่านั้น');
            }

            return;
        }

        if (! $from->isDirectParentOf($to)) {
            throw new InvalidTransferException(
                "โอนได้เฉพาะไปยังหน่วยงานลูกโดยตรงเท่านั้น ({$from->code} -> {$to->code} ไม่ถูกต้อง)"
            );
        }
    }

    private function assertStatus(Transfer $transfer, array $allowed): void
    {
        if (! in_array($transfer->status, $allowed, true)) {
            throw new InvalidTransferException(
                "สถานะปัจจุบัน ({$transfer->status->value}) ไม่อนุญาตให้ทำรายการนี้"
            );
        }
    }
}
