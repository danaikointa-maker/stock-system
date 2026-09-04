<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BrandController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage-packages');
    }

    public function index()
    {
        $currentLogo = $this->getCurrentLogo();
        return view('admin.brand', compact('currentLogo'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'logo' => 'required|file|mimes:svg,png,jpg,jpeg,webp,ico|max:2048',
        ], [
            'logo.required' => 'กรุณาเลือกไฟล์โลโก้',
            'logo.mimes' => 'รองรับเฉพาะไฟล์ SVG, PNG, JPG, WEBP, ICO',
            'logo.max' => 'ขนาดไฟล์ต้องไม่เกิน 2MB',
        ]);

        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'jpeg') $ext = 'jpg';

        // ลบไฟล์เก่าทั้งหมด
        $this->clearOldLogos();

        // บันทึกไฟล์ใหม่เป็น logo.{ext}
        $file->move(public_path('brand'), 'logo.' . $ext);

        // สร้าง favicon.ico จาก PNG/JPG (ถ้าทำได้)
        if (in_array($ext, ['png', 'jpg', 'webp']) && function_exists('imagecreatefromstring')) {
            $this->generateFavicon(public_path('brand/logo.' . $ext));
        }

        // Clear cache
        cache()->forget('brand_logo_file');

        return redirect()->route('admin.brand.index')
            ->with('status', 'อัปโหลดโลโก้สำเร็จ! ระบบจะใช้โลโก้ใหม่ทันที');
    }

    public function destroy()
    {
        $this->clearOldLogos();

        // ไม่ copy default กลับ → ให้ brand_logo() return transparent PNG
        cache()->forget('brand_logo_file');

        return redirect()->route('admin.brand.index')
            ->with('status', 'ลบโลโก้แล้ว — ระบบจะใช้พื้นที่โปร่งใสแทน');
    }

    private function getCurrentLogo(): ?array
    {
        $exts = ['svg', 'png', 'jpg', 'jpeg', 'webp', 'ico'];
        foreach ($exts as $ext) {
            $path = public_path('brand/logo.' . $ext);
            if (File::exists($path)) {
                return [
                    'file' => 'logo.' . $ext,
                    'ext' => $ext,
                    'size' => number_format(File::size($path) / 1024, 1) . ' KB',
                    'updated' => date('d/m/Y H:i', File::lastModified($path)),
                    'url' => asset('brand/logo.' . $ext),
                ];
            }
        }
        return null;
    }

    private function clearOldLogos(): void
    {
        $exts = ['svg', 'png', 'jpg', 'jpeg', 'webp', 'ico'];
        foreach ($exts as $ext) {
            $path = public_path('brand/logo.' . $ext);
            if (File::exists($path)) {
                File::delete($path);
            }
            // ลบ favicon.ico ด้วย
            $favicon = public_path('brand/favicon.ico');
            if (File::exists($favicon)) {
                File::delete($favicon);
            }
        }
    }

    private function generateFavicon(string $sourcePath): void
    {
        try {
            $img = null;
            $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
            
            if ($ext === 'png') {
                $img = @imagecreatefrompng($sourcePath);
            } elseif (in_array($ext, ['jpg', 'jpeg'])) {
                $img = @imagecreatefromjpeg($sourcePath);
            } elseif ($ext === 'webp') {
                $img = @imagecreatefromwebp($sourcePath);
            }

            if (!$img) return;

            // Resize เป็น 32x32
            $favicon = imagecreatetruecolor(32, 32);
            imagesavealpha($favicon, true);
            $transparent = imagecolorallocatealpha($favicon, 0, 0, 0, 127);
            imagefill($favicon, 0, 0, $transparent);
            
            imagecopyresampled($favicon, $img, 0, 0, 0, 0, 32, 32, imagesx($img), imagesy($img));
            
            // บันทึกเป็น PNG (เบราว์เซอร์รองรับดีกว่า ICO)
            imagepng($favicon, public_path('brand/favicon.png'));
            
            imagedestroy($img);
            imagedestroy($favicon);
        } catch (\Throwable $e) {
            // ไม่เป็นไร ถ้าสร้าง favicon ไม่ได้
        }
    }
}
