<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ProductQrcode;
use App\Services\QrScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API สำหรับหน้าเว็บ/LIFF ที่ลูกค้าเปิดจากการสแกน QR */
class QrScanController extends Controller
{
    public function __construct(private QrScanService $scanner) {}

    /** GET /api/qr/{token} — ดูข้อมูลก่อนยืนยัน (ไม่ตัดสิทธิ์) */
    public function show(string $token): JsonResponse
    {
        $qr = ProductQrcode::with('product:id,name,image_url')
            ->where('qr_token', $token)
            ->first();

        if (! $qr) {
            return response()->json(['message' => 'QR Code นี้ไม่ถูกต้อง'], 404);
        }

        return response()->json([
            'product'        => $qr->product->only(['name', 'image_url']),
            'points'         => $qr->points,
            'needs_secret'   => (bool) $qr->secret_hash,
            'already_used'   => ! $qr->isRedeemable(),
        ]);
    }

    /** POST /api/qr/{token}/redeem — ยืนยันรับคะแนน */
    public function redeem(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'phone'   => ['required', 'string', 'max:30'],
            'name'    => ['nullable', 'string', 'max:150'],
            'secret'  => ['nullable', 'string', 'max:32'],
            'node_id' => ['nullable', 'integer', 'exists:org_nodes,id'],
            'lat'     => ['nullable', 'numeric', 'between:-90,90'],
            'lng'     => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // ในระบบจริงควรผ่าน OTP ก่อนถึงขั้นนี้
        $customer = Customer::firstOrCreate(
            ['phone' => $data['phone']],
            ['name' => $data['name'] ?? null, 'referred_by_node_id' => $data['node_id'] ?? null]
        );

        $result = $this->scanner->scan(
            token: $token,
            customer: $customer,
            secret: $data['secret'] ?? null,
            context: [
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'node_id'    => $data['node_id'] ?? null,
                'lat'        => $data['lat'] ?? null,
                'lng'        => $data['lng'] ?? null,
            ],
        );

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
