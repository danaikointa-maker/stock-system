<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\OrgNode;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * จัดการยอดสต๊อกทั้งหมด — ทุกเมธอดต้องถูกเรียกภายใน DB::transaction()
 *
 * หลักการ: stock_movements คือแหล่งความจริง (append-only)
 *          stock_balances คือยอดสรุปที่อัปเดตพร้อมกันในทรานแซกชันเดียว
 */
class StockService
{
    /**
     * ล็อกแถวยอดคงเหลือ (สร้างถ้ายังไม่มี) — กัน race condition
     * ต้องเรียงลำดับการล็อกให้คงที่เสมอเพื่อกัน deadlock (ดู lockMany)
     */
    public function lockBalance(int $nodeId, int $productId, ?int $lotId = null): StockBalance
    {
        $balance = StockBalance::where('org_node_id', $nodeId)
            ->where('product_id', $productId)
            ->where('lot_id', $lotId)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            return $balance;
        }

        // firstOrCreate แบบกันชนกัน: ถ้าซ้อนกันให้ดึงของเดิมมาล็อกใหม่
        try {
            StockBalance::create([
                'org_node_id' => $nodeId,
                'product_id'  => $productId,
                'lot_id'      => $lotId,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // อีก transaction สร้างไปแล้ว
        }

        return StockBalance::where('org_node_id', $nodeId)
            ->where('product_id', $productId)
            ->where('lot_id', $lotId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** ล็อกหลายแถวพร้อมกันโดยเรียงลำดับคงที่ (กัน deadlock) */
    public function lockMany(array $keys): array
    {
        usort($keys, fn ($a, $b) => [$a[0], $a[1], $a[2] ?? 0] <=> [$b[0], $b[1], $b[2] ?? 0]);

        $out = [];
        foreach ($keys as $k) {
            $b = $this->lockBalance($k[0], $k[1], $k[2] ?? null);
            $out[$this->key($k[0], $k[1], $k[2] ?? null)] = $b;
        }

        return $out;
    }

    private function key(int $n, int $p, ?int $l): string
    {
        return "$n:$p:" . ($l ?? 'null');
    }

    /** รับของเข้า (+on_hand) และบันทึก movement */
    public function increase(
        int $nodeId,
        int $productId,
        int $qty,
        MovementType $type,
        ?int $lotId = null,
        ?string $refType = null,
        ?int $refId = null,
        ?float $unitCost = null,
        ?string $note = null,
    ): StockMovement {
        $this->assertPositive($qty);
        $balance = $this->lockBalance($nodeId, $productId, $lotId);

        $balance->qty_on_hand += $qty;
        $balance->save();

        return $this->writeMovement($balance, 'in', $qty, $type, $refType, $refId, $unitCost, $note);
    }

    /** ตัดของออก (-on_hand) พร้อมตรวจยอดพอ */
    public function decrease(
        int $nodeId,
        int $productId,
        int $qty,
        MovementType $type,
        ?int $lotId = null,
        ?string $refType = null,
        ?int $refId = null,
        ?string $note = null,
        bool $fromReserved = false,
    ): StockMovement {
        $this->assertPositive($qty);
        $balance = $this->lockBalance($nodeId, $productId, $lotId);

        $usable = $fromReserved ? $balance->qty_on_hand : $balance->available;

        if ($usable < $qty) {
            throw new InsufficientStockException(
                "สต๊อกไม่พอ: ต้องการ {$qty} มีใช้ได้ {$usable} (node {$nodeId}, product {$productId})"
            );
        }

        $balance->qty_on_hand -= $qty;
        if ($fromReserved) {
            $balance->qty_reserved = max(0, $balance->qty_reserved - $qty);
        }
        $balance->save();

        return $this->writeMovement($balance, 'out', $qty, $type, $refType, $refId, null, $note);
    }

    /** จองของไว้ในบิลที่ยังไม่ส่ง (ไม่ลด on_hand แต่ลด available) */
    public function reserve(int $nodeId, int $productId, int $qty, ?int $lotId = null): void
    {
        $this->assertPositive($qty);
        $balance = $this->lockBalance($nodeId, $productId, $lotId);

        if ($balance->available < $qty) {
            throw new InsufficientStockException(
                "จองไม่ได้ สต๊อกว่างไม่พอ: ต้องการ {$qty} ว่าง {$balance->available}"
            );
        }

        $balance->qty_reserved += $qty;
        $balance->save();
    }

    public function releaseReservation(int $nodeId, int $productId, int $qty, ?int $lotId = null): void
    {
        $balance = $this->lockBalance($nodeId, $productId, $lotId);
        $balance->qty_reserved = max(0, $balance->qty_reserved - $qty);
        $balance->save();
    }

    public function addInTransit(int $nodeId, int $productId, int $qty, ?int $lotId = null): void
    {
        $balance = $this->lockBalance($nodeId, $productId, $lotId);
        $balance->qty_in_transit += $qty;
        $balance->save();
    }

    public function clearInTransit(int $nodeId, int $productId, int $qty, ?int $lotId = null): void
    {
        $balance = $this->lockBalance($nodeId, $productId, $lotId);
        $balance->qty_in_transit = max(0, $balance->qty_in_transit - $qty);
        $balance->save();
    }

    /** ปรับยอดให้ตรงกับที่นับได้จริง (stock count) */
    public function adjustTo(int $nodeId, int $productId, int $countedQty, ?int $lotId = null, ?string $note = null): ?StockMovement
    {
        $balance = $this->lockBalance($nodeId, $productId, $lotId);
        $diff = $countedQty - $balance->qty_on_hand;

        if ($diff === 0) {
            return null;
        }

        return $diff > 0
            ? $this->increase($nodeId, $productId, $diff, MovementType::AdjustIn, $lotId, note: $note ?? 'ปรับยอดจากการนับ')
            : $this->decrease($nodeId, $productId, abs($diff), MovementType::AdjustOut, $lotId, note: $note ?? 'ปรับยอดจากการนับ');
    }

    /**
     * เลือกล็อตแบบ FEFO (First Expired, First Out) สำหรับตัดของ
     * คืน [['lot_id' => x, 'qty' => n], ...]
     */
    public function pickLotsFefo(int $nodeId, int $productId, int $qty): array
    {
        // ต้องระบุชื่อตารางให้ครบทุกคอลัมน์ เพราะมี join (ไม่งั้น ambiguous column)
        $rows = StockBalance::query()
            ->leftJoin('product_lots', 'product_lots.id', '=', 'stock_balances.lot_id')
            ->where('stock_balances.org_node_id', $nodeId)
            ->where('stock_balances.product_id', $productId)
            ->whereRaw('stock_balances.qty_on_hand - stock_balances.qty_reserved > 0')
            ->orderByRaw('CASE WHEN product_lots.expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('product_lots.expiry_date')
            ->select('stock_balances.*')
            ->get();

        $picked = [];
        $remaining = $qty;

        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, $row->available);
            $picked[] = ['lot_id' => $row->lot_id, 'qty' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new InsufficientStockException("สต๊อกรวมทุกล็อตไม่พอ ขาดอีก {$remaining} ชิ้น");
        }

        return $picked;
    }

    /** สรุปสต๊อกทั้งสายงานใต้โหนดที่กำหนด */
    public function subtreeSummary(OrgNode $node, ?int $productId = null): \Illuminate\Support\Collection
    {
        return DB::table('v_stock_summary')
            ->whereIn('node_id', $node->subtreeIds())
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->get();
    }

    private function writeMovement(
        StockBalance $balance, string $direction, int $qty, MovementType $type,
        ?string $refType, ?int $refId, ?float $unitCost, ?string $note,
    ): StockMovement {
        return StockMovement::create([
            'org_node_id'   => $balance->org_node_id,
            'product_id'    => $balance->product_id,
            'lot_id'        => $balance->lot_id,
            'direction'     => $direction,
            'qty'           => $qty,
            'balance_after' => $balance->qty_on_hand,
            'type'          => $type,
            'ref_type'      => $refType,
            'ref_id'        => $refId,
            'unit_cost'     => $unitCost,
            'note'          => $note,
            'created_by'    => Auth::id(),
        ]);
    }

    private function assertPositive(int $qty): void
    {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('จำนวนต้องมากกว่า 0');
        }
    }
}
