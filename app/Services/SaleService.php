<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\QrStatus;
use App\Models\OrgNode;
use App\Models\Product;
use App\Models\ProductQrcode;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** การขายที่ร้านค้า (Lv5) / ผู้ขาย (Lv6) */
class SaleService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param array $items [['product_id'=>1,'qty'=>2,'unit_price'=>15,'discount'=>0,'lot_id'=>null], ...]
     */
    public function create(
        OrgNode $node,
        array $items,
        ?int $customerId = null,
        string $paymentMethod = 'cash',
        float $billDiscount = 0,
    ): Sale {
        if (! $node->level_id->canSellToCustomer()) {
            throw new \RuntimeException(
                "{$node->level_id->label()} ไม่สามารถขายให้ลูกค้าปลายทางได้ (เฉพาะร้านค้าและผู้ขาย)"
            );
        }

        return DB::transaction(function () use ($node, $items, $customerId, $paymentMethod, $billDiscount) {
            $sale = Sale::create([
                'doc_no'         => Sale::nextDocNo(),
                'org_node_id'    => $node->id,
                'seller_user_id' => Auth::id(),
                'customer_id'    => $customerId,
                'sold_at'        => now(),
                'payment_method' => $paymentMethod,
                'discount'       => $billDiscount,
            ]);

            $subtotal = 0.0;

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty = (int) $item['qty'];
                $price = (float) ($item['unit_price'] ?? $product->retail_price);
                $lineDiscount = (float) ($item['discount'] ?? 0);
                $lineTotal = $qty * $price - $lineDiscount;

                // ถ้าไม่ระบุล็อต ให้ระบบเลือกแบบ FEFO (ของใกล้หมดอายุออกก่อน)
                $picks = isset($item['lot_id'])
                    ? [['lot_id' => $item['lot_id'], 'qty' => $qty]]
                    : $this->stock->pickLotsFefo($node->id, $product->id, $qty);

                foreach ($picks as $pick) {
                    $this->stock->decrease(
                        nodeId: $node->id,
                        productId: $product->id,
                        qty: $pick['qty'],
                        type: MovementType::Sale,
                        lotId: $pick['lot_id'],
                        refType: Sale::class,
                        refId: $sale->id,
                        note: "ขายตามบิล {$sale->doc_no}",
                    );

                    // เปิดใช้งาน QR ของชิ้นที่ขายออก -> ลูกค้าถึงจะสแกนรับคะแนนได้
                    $this->activateQrcodes($node->id, $product->id, $pick['lot_id'], $pick['qty']);

                    // สร้าง sale_item แยกแต่ละล็อต เพื่อให้ยกเลิกบิลคืนสต๊อกได้ถูกต้องทุกล็อต
                    $pickDiscount = $qty > 0 ? ($lineDiscount * $pick['qty'] / $qty) : 0;
                    $sale->items()->create([
                        'product_id' => $product->id,
                        'lot_id'     => $pick['lot_id'],
                        'qty'        => $pick['qty'],
                        'unit_price' => $price,
                        'discount'   => round($pickDiscount, 2),
                        'line_total' => ($pick['qty'] * $price) - round($pickDiscount, 2),
                    ]);
                }

                $subtotal += $lineTotal;
            }

            $sale->update([
                'subtotal' => $subtotal,
                'total'    => $subtotal - $billDiscount,
            ]);

            return $sale->load('items');
        });
    }

    /** ยกเลิกบิล -> คืนของเข้าสต๊อก */
    public function void(Sale $sale, ?string $reason = null): Sale
    {
        if ($sale->status === 'voided') {
            throw new \RuntimeException('บิลนี้ถูกยกเลิกไปแล้ว');
        }

        return DB::transaction(function () use ($sale, $reason) {
            foreach ($sale->items as $item) {
                $this->stock->increase(
                    nodeId: $sale->org_node_id,
                    productId: $item->product_id,
                    qty: $item->qty,
                    type: MovementType::ReturnIn,
                    lotId: $item->lot_id,
                    refType: Sale::class,
                    refId: $sale->id,
                    note: 'ยกเลิกบิล: ' . ($reason ?? '-'),
                );
            }

            $sale->update(['status' => 'voided']);

            return $sale;
        });
    }

    /** in_stock -> sold (พร้อมให้ลูกค้าสแกน) */
    private function activateQrcodes(int $nodeId, int $productId, ?int $lotId, int $qty): void
    {
        $ids = ProductQrcode::where('product_id', $productId)
            ->where('current_node_id', $nodeId)
            ->when($lotId, fn ($q) => $q->where('lot_id', $lotId))
            ->where('status', QrStatus::InStock)
            ->limit($qty)
            ->pluck('id');

        ProductQrcode::whereIn('id', $ids)->update([
            'status'       => QrStatus::Sold->value,
            'activated_at' => now(),
            'updated_at'   => now(),
        ]);
    }
}
