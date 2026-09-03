<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\OrgNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * บันทึกและวิเคราะห์ตำแหน่งที่ลูกค้าสแกน
 *
 * ข้อจำกัดที่ต้องเข้าใจ:
 *   เบราว์เซอร์ "ไม่สามารถ" ดึงตำแหน่งแบบเบื้องหลังได้
 *   ทั้ง iOS และ Android บังคับให้ขึ้น popup ขออนุญาตเสมอ
 *   ระบบจึงต้องทำงานได้แม้ผู้ใช้ปฏิเสธ — บันทึกว่า denied แล้วให้แต้มตามปกติ
 *   ไม่งั้นจะเสียลูกค้าจำนวนมาก
 *
 * สิ่งที่ตรวจจับได้
 *   far_from_shop     สแกนห่างจากร้านที่ใกล้ที่สุดมากผิดปกติ
 *   impossible_travel เบอร์เดียวสแกนสองที่ไกลกันเกินกว่าจะเดินทางทัน
 */
class ScanGeoService
{
    /** ระยะที่ถือว่าไกลจากร้านผิดปกติ (เมตร) */
    private const FAR_THRESHOLD_M = 5000;

    /** ความเร็วสูงสุดที่เป็นไปได้ (กม./ชม.) — เร็วกว่านี้ถือว่าผิดปกติ */
    private const MAX_SPEED_KMH = 900;

    public function __construct(private SecurityService $security)
    {
    }

    /**
     * บันทึกตำแหน่งของการสแกนครั้งนี้
     * คืนค่าธงความเสี่ยงที่ตรวจพบ
     */
    public function record(
        Customer $customer,
        ?int $scanLogId,
        ?float $lat,
        ?float $lng,
        ?float $accuracy,
        string $permission,
        ?Request $request = null,
    ): string {
        $riskFlag = 'none';
        $nearestNodeId = null;
        $distanceM = null;

        if ($permission === 'granted' && $lat !== null && $lng !== null) {
            [$nearestNodeId, $distanceM] = $this->findNearestShop($lat, $lng);

            if ($distanceM !== null && $distanceM > self::FAR_THRESHOLD_M) {
                $riskFlag = 'far_from_shop';
            }

            if ($this->isImpossibleTravel($customer, $lat, $lng)) {
                $riskFlag = 'impossible_travel';
            }
        }

        DB::table('scan_geo_logs')->insert([
            'scan_log_id'     => $scanLogId ?? 0,
            'customer_id'     => $customer->id,
            'lat'             => $lat,
            'lng'             => $lng,
            'accuracy_m'      => $accuracy !== null ? (int) round($accuracy) : null,
            'permission'      => $permission,
            'nearest_node_id' => $nearestNodeId,
            'distance_m'      => $distanceM,
            'ip_address'      => $request?->ip(),
            'user_agent'      => substr((string) $request?->userAgent(), 0, 255),
            'risk_flag'       => $riskFlag,
            'created_at'      => now(),
        ]);

        if ($riskFlag !== 'none') {
            $this->security->log(
                SecurityService::E_SUSPICIOUS_GEO,
                match ($riskFlag) {
                    'far_from_shop'     => "สแกนห่างจากร้านที่ใกล้ที่สุด {$distanceM} เมตร",
                    'impossible_travel' => 'เบอร์เดียวกันสแกนจากสองที่ที่ไกลเกินกว่าจะเดินทางทัน',
                    default             => 'ตำแหน่งการสแกนผิดปกติ',
                },
                'medium',
                [
                    'customer_id' => $customer->id,
                    'lat'         => $lat,
                    'lng'         => $lng,
                    'distance_m'  => $distanceM,
                ],
            );
        }

        return $riskFlag;
    }

    /**
     * หาร้านที่ใกล้ที่สุด
     *
     * ใช้ bounding box คัดกรองก่อนเพื่อไม่ต้องคำนวณระยะทุกแถว
     * (1 องศา ≈ 111 กม. ใช้กรอบ ~0.5 องศาครอบคลุมราว 55 กม.)
     *
     * @return array{0: int|null, 1: int|null} [node_id, ระยะเป็นเมตร]
     */
    private function findNearestShop(float $lat, float $lng): array
    {
        $box = 0.5;

        $candidates = DB::table('shop_profiles')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereBetween('lat', [$lat - $box, $lat + $box])
            ->whereBetween('lng', [$lng - $box, $lng + $box])
            ->select('node_id', 'lat', 'lng')
            ->get();

        if ($candidates->isEmpty()) {
            return [null, null];
        }

        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($candidates as $shop) {
            $d = $this->haversine($lat, $lng, (float) $shop->lat, (float) $shop->lng);

            if ($d < $minDistance) {
                $minDistance = $d;
                $nearest = $shop->node_id;
            }
        }

        return [$nearest, (int) round($minDistance)];
    }

    /**
     * ตรวจว่าเดินทางจากจุดสแกนก่อนหน้ามาถึงที่นี่ทันไหม
     * ถ้าต้องบินเร็วกว่าเครื่องบิน = มีคนใช้เบอร์เดียวกันหลายที่
     */
    private function isImpossibleTravel(Customer $customer, float $lat, float $lng): bool
    {
        $previous = DB::table('scan_geo_logs')
            ->where('customer_id', $customer->id)
            ->where('permission', 'granted')
            ->whereNotNull('lat')
            ->orderByDesc('created_at')
            ->first();

        if (! $previous) {
            return false;
        }

        $minutes = now()->diffInMinutes($previous->created_at);

        if ($minutes < 1) {
            $minutes = 1;
        }

        $km = $this->haversine($lat, $lng, (float) $previous->lat, (float) $previous->lng) / 1000;
        $speedKmh = $km / ($minutes / 60);

        // ระยะใกล้ ๆ ไม่ต้องสนใจ (GPS คลาดเคลื่อนได้)
        return $km > 50 && $speedKmh > self::MAX_SPEED_KMH;
    }

    /** ระยะทางระหว่างสองพิกัด (เมตร) */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
