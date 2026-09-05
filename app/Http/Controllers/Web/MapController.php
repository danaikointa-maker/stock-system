<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OrgNode;
use Illuminate\Http\Request;

class MapController extends Controller
{
    /**
     * 🗺️ หน้าแผนที่สำหรับ Agent — ดูร้านค้าทั้งหมด + นำทาง
     */
    public function agentMap(Request $request)
    {
        abort_unless(
            $request->user()->hasAbility('manage-shop-profile') || $request->user()->hasAbility('agent-checkin'),
            403, 'ไม่มีสิทธิ์ดูแผนที่'
        );

        $shops = OrgNode::whereIn('id', $request->user()->visibleNodeIds())
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get(['id', 'name', 'lat', 'lng', 'address', 'phone', 'shop_type', 'opening_hours', 'photos']);

        return view('maps.agent', compact('shops'));
    }

    /**
     * 🗺️ หน้าแผนที่สำหรับทุกคน (ลูกค้า + Shop + Agent)
     * แสดงร้านค้าที่ show_on_map = true
     */
    public function publicMap(Request $request)
    {
        $shops = OrgNode::where('show_on_map', true)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('status', 'active')
            ->get(['id', 'name', 'lat', 'lng', 'address', 'phone', 'shop_type',
                   'opening_hours', 'photos', 'map_cover_photo', 'map_description']);

        return view('maps.public', compact('shops'));
    }

    /**
     * 🔍 API: ร้านค้าใกล้ฉัน
     */
    public function nearby(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:100', // km
        ]);

        $lat = $validated['lat'];
        $lng = $validated['lng'];
        $radius = $validated['radius'] ?? 50;

        // ดึงร้านค้าที่มีพิกัด + show_on_map
        $shops = OrgNode::where('show_on_map', true)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('status', 'active')
            ->get(['id', 'name', 'lat', 'lng', 'address', 'phone', 'shop_type',
                   'opening_hours', 'photos', 'map_cover_photo', 'map_description']);

        // คำนวณระยะทาง + กรองตาม radius
        $results = $shops->map(function ($shop) use ($lat, $lng) {
            $distance = $this->haversine($lat, $lng, $shop->lat, $shop->lng);
            $shop->distance_km = round($distance / 1000, 2);
            return $shop;
        })
        ->where('distance_km', '<=', $radius)
        ->sortBy('distance_km')
        ->values();

        return response()->json($results);
    }

    /**
     * 🔍 API: ร้านค้าทั้งหมด (สำหรับ Agent)
     */
    public function agentStores(Request $request)
    {
        abort_unless(
            $request->user()->hasAbility('manage-shop-profile') || $request->user()->hasAbility('agent-checkin'),
            403
        );

        $shops = OrgNode::whereIn('id', $request->user()->visibleNodeIds())
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get(['id', 'name', 'lat', 'lng', 'address', 'phone', 'shop_type',
                   'opening_hours', 'photos', 'map_description']);

        // คำนวณระยะทางถ้ามีพิกัดผู้ใช้
        if ($request->has('lat') && $request->has('lng')) {
            $lat = $request->input('lat');
            $lng = $request->input('lng');
            $shops->each(function ($shop) use ($lat, $lng) {
                $shop->distance_km = round($this->haversine($lat, $lng, $shop->lat, $shop->lng) / 1000, 2);
            });
            $shops = $shops->sortBy('distance_km')->values();
        }

        return response()->json($shops);
    }

    /**
     * คำนวณระยะทาง (Haversine formula) — return เมตร
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
