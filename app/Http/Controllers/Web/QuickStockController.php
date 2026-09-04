<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductLot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * เพิ่มสต๊อกด่วน — รองรับสแกน Barcode จากมือถือ/ปืนสแกนเนอร์
 *
 * วิธีใช้:
 *   - ปืนสแกนเนอร์ USB: สแกน → ระบบรับ barcode อัตโนมัติ (ทำงานเหมือน keyboard)
 *   - มือถือ/แท็บเล็ต: กดปุ่ม 📷 เพื่อเปิดกล้องถ่าย barcode
 *   - พิมพ์เอง: พิมพ์ barcode/รหัสสินค้า แล้วกด Enter
 */
class QuickStockController extends Controller
{
    public function index()
    {
        $products = Product::orderBy('name')->get(['id', 'name', 'sku', 'barcode']);

        return view('products.quick-stock', [
            'products' => $products,
        ]);
    }

    /** ค้นหาสินค้าจาก barcode/sku/id (AJAX) */
    public function lookup(Request $request)
    {
        $request->validate(['q' => 'required|string|max:100']);
        $q = trim($request->input('q'));

        $product = Product::where('barcode', $q)
            ->orWhere('sku', $q)
            ->orWhere('id', is_numeric($q) ? (int) $q : 0)
            ->first(['id', 'name', 'sku', 'barcode']);

        if (! $product) {
            return response()->json(['found' => false, 'query' => $q]);
        }

        return response()->json([
            'found'   => true,
            'product' => $product,
        ]);
    }

    /** เพิ่มล็อตสินค้า (AJAX) */
    public function addLot(Request $request)
    {
        $data = $request->validate([
            'product_id'  => 'required|exists:products,id',
            'lot_no'      => 'required|string|max:60',
            'qty'         => 'required|integer|min:1|max:999999',
            'mfg_date'    => 'nullable|date',
            'expiry_date' => 'nullable|date|after:today',
        ]);

        $product = Product::findOrFail($data['product_id']);

        $lot = ProductLot::create([
            'product_id'   => $product->id,
            'lot_no'       => $data['lot_no'],
            'qty_produced' => $data['qty'],
            'mfg_date'     => $data['mfg_date'] ?? null,
            'expiry_date'  => $data['expiry_date'] ?? null,
        ]);

        return response()->json([
            'ok'   => true,
            'lot'  => $lot,
            'product_name' => $product->name,
        ]);
    }
}
