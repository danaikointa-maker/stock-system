<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductQrcode;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Services\QrScanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** จัดการสินค้า / ล็อตการผลิต / ออก QR code — ต้องมี ability `manage-products` */
class ProductController extends Controller
{
    public function __construct(private QrScanService $qr) {}

    public function index(Request $request): View
    {
        $this->authorize('manage-products');

        $q = trim((string) $request->query('q', ''));

        $products = Product::with(['category', 'unit'])
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('sku', 'like', "%{$q}%")
                ->orWhere('barcode', 'like', "%{$q}%")))
            ->orderBy('sku')
            ->paginate(20)
            ->withQueryString();

        // ยอดคงเหลือรวมทั้งระบบต่อสินค้า
        $totals = StockBalance::whereIn('product_id', $products->pluck('id'))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(qty_on_hand) AS total')
            ->pluck('total', 'product_id');

        return view('products.index', compact('products', 'totals', 'q'));
    }

    public function create(): View
    {
        $this->authorize('manage-products');

        return view('products.form', [
            'product'    => new Product(['status' => 'active', 'pack_size' => 1, 'points_per_unit' => 1]),
            'categories' => Category::orderBy('name')->get(),
            'units'      => Unit::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-products');

        $product = Product::create($this->validated($request));

        return redirect()->route('products.show', $product)
            ->with('ok', "เพิ่มสินค้า {$product->sku} เรียบร้อย");
    }

    public function show(Product $product): View
    {
        $this->authorize('manage-products');

        $product->load(['category', 'unit', 'lots' => fn ($q) => $q->latest('id')]);

        // นับ QR แยกสถานะต่อล็อต
        $qrStats = ProductQrcode::whereIn('lot_id', $product->lots->pluck('id'))
            ->groupBy('lot_id', 'status')
            ->selectRaw('lot_id, status, COUNT(*) AS c')
            ->get()
            ->groupBy('lot_id');

        return view('products.show', [
            'product' => $product,
            'qrStats' => $qrStats,
            'stock'   => StockBalance::with('node')
                ->where('product_id', $product->id)
                ->where('qty_on_hand', '>', 0)
                ->get(),
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorize('manage-products');

        return view('products.form', [
            'product'    => $product,
            'categories' => Category::orderBy('name')->get(),
            'units'      => Unit::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('manage-products');

        $product->update($this->validated($request, $product));

        return redirect()->route('products.show', $product)
            ->with('ok', 'บันทึกการแก้ไขเรียบร้อย');
    }

    /** เปิดล็อตการผลิตใหม่ */
    public function storeLot(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('manage-products');

        $data = $request->validate([
            'lot_no'       => ['required', 'string', 'max:60'],
            'qty_produced' => ['required', 'integer', 'min:1', 'max:1000000'],
            'mfg_date'     => ['nullable', 'date'],
            'expiry_date'  => ['nullable', 'date', 'after:mfg_date'],
        ]);

        $exists = ProductLot::where('product_id', $product->id)
            ->where('lot_no', $data['lot_no'])->exists();

        if ($exists) {
            return back()->withErrors(['lot_no' => 'เลขล็อตนี้มีอยู่แล้วในสินค้านี้'])->withInput();
        }

        $lot = $product->lots()->create($data);

        return redirect()->route('products.show', $product)
            ->with('ok', "เปิดล็อต {$lot->lot_no} จำนวน {$lot->qty_produced} ชิ้นเรียบร้อย (ยังไม่ได้ออก QR)");
    }

    /** ออก QR code สำหรับล็อต + คืนไฟล์ CSV ให้เอาไปสั่งพิมพ์ */
    public function generateQr(Request $request, Product $product, ProductLot $lot): RedirectResponse
    {
        $this->authorize('manage-products');

        abort_unless($lot->product_id === $product->id, 404);

        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $already = ProductQrcode::where('lot_id', $lot->id)->count();

        if ($already + $data['qty'] > $lot->qty_produced) {
            return back()->withErrors([
                'qty' => "ออก QR เกินจำนวนที่ผลิต — ล็อตนี้ผลิต {$lot->qty_produced} ชิ้น "
                       . "ออกไปแล้ว {$already} ใบ เหลือออกได้อีก " . max(0, $lot->qty_produced - $already) . ' ใบ',
            ]);
        }

        $secrets = $this->qr->generateForLot($lot, $data['qty']);

        // เขียนไฟล์สำหรับส่งโรงพิมพ์ — รหัสใต้ฟิล์มดิบมีให้ครั้งเดียวตอนนี้เท่านั้น
        // (ใน DB เก็บแค่ hash) ถ้าไม่บันทึกตอนนี้จะกู้คืนไม่ได้
        $this->appendQrCsv($lot, $secrets);

        return redirect()->route('products.show', $product)->with(
            'ok',
            "ออก QR {$data['qty']} ใบเรียบร้อย — ดาวน์โหลดไฟล์สำหรับสั่งพิมพ์ได้ที่ปุ่ม \"ไฟล์พิมพ์ QR\" ของล็อตนี้"
        );
    }

    /** ดาวน์โหลด CSV สำหรับส่งโรงพิมพ์ (มีรหัสใต้ฟิล์มขูดแบบ plain text) */
    public function qrCsv(Product $product, ProductLot $lot): StreamedResponse
    {
        $this->authorize('manage-products');

        abort_unless($lot->product_id === $product->id, 404);

        $path = storage_path("app/qr_print_{$lot->lot_no}.csv");

        abort_unless(is_file($path), 404, 'ยังไม่ได้ออก QR สำหรับล็อตนี้');

        return response()->streamDownload(
            fn () => readfile($path),
            "qr_print_{$product->sku}_{$lot->lot_no}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /** ต่อท้ายไฟล์ CSV สั่งพิมพ์ (สร้างใหม่พร้อมหัวตารางถ้ายังไม่มี) */
    private function appendQrCsv(ProductLot $lot, array $secrets): void
    {
        $path = storage_path("app/qr_print_{$lot->lot_no}.csv");
        $isNew = ! is_file($path);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $fh = fopen($path, 'a');

        if ($isNew) {
            fwrite($fh, "\xEF\xBB\xBF"); // BOM ให้ Excel อ่านภาษาไทยได้
            fputcsv($fh, ['serial_no', 'qr_token', 'secret', 'scan_url'], ',', '"', '\\');
        }

        foreach ($secrets as $row) {
            fputcsv($fh, [
                $row['serial_no'],
                $row['qr_token'],
                $row['secret'],
                url('/s/' . $row['qr_token']),
            ], ',', '"', '\\');
        }

        fclose($fh);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $id = $product?->id;

        return $request->validate([
            'sku'             => ['required', 'string', 'max:60', "unique:products,sku,{$id}"],
            'barcode'         => ['nullable', 'string', 'max:60'],
            'name'            => ['required', 'string', 'max:180'],
            'category_id'     => ['nullable', 'integer', 'exists:categories,id'],
            'unit_id'         => ['nullable', 'integer', 'exists:units,id'],
            'pack_size'       => ['required', 'integer', 'min:1'],
            'cost_price'      => ['required', 'numeric', 'min:0'],
            'retail_price'    => ['required', 'numeric', 'min:0'],
            'points_per_unit' => ['required', 'integer', 'min:0'],
            'has_expiry'      => ['nullable', 'boolean'],
            'status'          => ['required', 'in:active,inactive'],
        ]);
    }
}
