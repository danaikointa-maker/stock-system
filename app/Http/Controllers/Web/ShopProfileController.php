<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgentCheckin;
use App\Models\OrgNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShopProfileController extends Controller
{
    // ════════════════════════════════════
    // 🏪 จัดการร้านค้าของ Agent
    // ════════════════════════════════════

    /** แสดงรายการร้านค้าที่ Agent ดูแล */
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAbility('manage-shop-profile'), 403, 'ไม่มีสิทธิ์จัดการร้านค้า');

        $shops = OrgNode::whereIn('id', $request->user()->visibleNodeIds())
            ->whereNotNull('level_id')
            ->withCount(['children'])
            ->latest('updated_at')
            ->paginate(20);

        // ดึง check-in ล่าสุดของแต่ละร้าน
        $recentCheckins = AgentCheckin::where('user_id', $request->user()->id)
            ->latest('created_at')
            ->limit(10)
            ->with('shop')
            ->get();

        // สรุปจำนวน check-in เดือนนี้
        $monthlyCheckins = AgentCheckin::where('user_id', $request->user()->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return view('agent.shops.index', compact('shops', 'recentCheckins', 'monthlyCheckins'));
    }

    /** หน้าแก้ไขข้อมูลร้านค้า */
    public function edit(Request $request, OrgNode $shop)
    {
        abort_unless($request->user()->hasAbility('manage-shop-profile'), 403, 'ไม่มีสิทธิ์จัดการร้านค้า');
        abort_unless(in_array($shop->id, $request->user()->visibleNodeIds()), 403, 'ไม่มีสิทธิ์แก้ไขร้านค้านี้');

        return view('agent.shops.edit', compact('shop'));
    }

    /** อัปเดตข้อมูลร้านค้า */
    public function update(Request $request, OrgNode $shop)
    {
        abort_unless($request->user()->hasAbility('manage-shop-profile'), 403, 'ไม่มีสิทธิ์จัดการร้านค้า');
        abort_unless(in_array($shop->id, $request->user()->visibleNodeIds()), 403, 'ไม่มีสิทธิ์แก้ไขร้านค้านี้');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:1000',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'line_id' => 'nullable|string|max:50',
            'opening_hours' => 'nullable|string|max:100',
            'shop_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // จัดการรูปถ่าย
        $existingPhotos = $shop->photos ?? [];
        $newPhotos = [];

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('shops/' . $shop->id, 'public');
                $newPhotos[] = $path;
            }
        }

        // ลบรูปที่เลือกให้ลบ
        $keepPhotos = $request->input('keep_photos', []);
        $finalPhotos = [];
        foreach ($existingPhotos as $photo) {
            if (in_array($photo, $keepPhotos)) {
                $finalPhotos[] = $photo;
            } else {
                Storage::disk('public')->delete($photo);
            }
        }
        $finalPhotos = array_merge($finalPhotos, $newPhotos);

        // อัปเดตข้อมูล
        $shop->update([
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'lat' => $validated['lat'] ?? null,
            'lng' => $validated['lng'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'line_id' => $validated['line_id'] ?? null,
            'opening_hours' => $validated['opening_hours'] ?? null,
            'shop_type' => $validated['shop_type'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'photos' => $finalPhotos ?: null,
        ]);

        return redirect()->route('agent.shops.index')
            ->with('success', 'อัปเดตข้อมูลร้านค้าเรียบร้อย');
    }

    // ════════════════════════════════════
    // 📍 Check-in ที่ร้านค้า
    // ════════════════════════════════════

    /** หน้า check-in */
    public function checkin(Request $request, OrgNode $shop)
    {
        abort_unless($request->user()->hasAbility('agent-checkin'), 403, 'ไม่มีสิทธิ์ check-in');
        abort_unless(in_array($shop->id, $request->user()->visibleNodeIds()), 403, 'ไม่มีสิทธิ์ check-in ร้านค้านี้');

        return view('agent.shops.checkin', compact('shop'));
    }

    /** บันทึก check-in */
    public function storeCheckin(Request $request, OrgNode $shop)
    {
        abort_unless($request->user()->hasAbility('agent-checkin'), 403, 'ไม่มีสิทธิ์ check-in');
        abort_unless(in_array($shop->id, $request->user()->visibleNodeIds()), 403, 'ไม่มีสิทธิ์ check-in ร้านค้านี้');

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'type' => 'required|in:visit,delivery,pickup,other',
            'notes' => 'nullable|string|max:1000',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // คำนวณระยะห่างจากร้าน (ถ้ามีพิกัดร้าน)
        $distance = null;
        if ($shop->lat && $shop->lng) {
            $distance = AgentCheckin::calculateDistance(
                $validated['latitude'], $validated['longitude'],
                $shop->lat, $shop->lng
            );
        }

        // Upload รูป
        $photoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('checkins/' . $shop->id . '/' . now()->format('Y-m-d'), 'public');
                $photoPaths[] = $path;
            }
        }

        // บันทึก check-in
        AgentCheckin::create([
            'user_id' => $request->user()->id,
            'org_node_id' => $shop->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'type' => $validated['type'],
            'notes' => $validated['notes'] ?? null,
            'photos' => $photoPaths ?: null,
            'distance_meters' => $distance,
        ]);

        return redirect()->route('agent.shops.index')
            ->with('success', 'Check-in เรียบร้อย');
    }

    /** ประวัติ check-in */
    public function history(Request $request)
    {
        abort_unless($request->user()->hasAbility('agent-checkin'), 403, 'ไม่มีสิทธิ์ดูประวัติ');

        $checkins = AgentCheckin::where('user_id', $request->user()->id)
            ->with('shop')
            ->latest('created_at')
            ->paginate(20);

        return view('agent.shops.history', compact('checkins'));
    }
}
