<?php

/**
 * Transparent 1×1 PNG — ใช้เมื่อไม่มีโลโก้ (favicon fallback)
 */
define('BRAND_TRANSPARENT_PNG', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVQI12NgAAIABQABNjN9GQAAAAlwSFlzAAAWJQAAFiUBSVIk8AAAAA0lEQVQI12P4z8BQDwAEgAF/QualIQAAAABJRU5ErkJggg==');

if (!function_exists('brand_logo')) {
    /**
     * ดึง URL โลโก้ปัจจุบัน — รองรับ SVG/PNG/JPG/WEBP/ICO
     * ถ้าไม่มีไฟล์ → return transparent 1×1 PNG (data URI)
     */
    function brand_logo(): string
    {
        $exts = ['svg', 'png', 'jpg', 'jpeg', 'webp', 'ico'];
        foreach ($exts as $ext) {
            $path = public_path('brand/logo.' . $ext);
            if (file_exists($path)) {
                return asset('brand/logo.' . $ext) . '?v=' . filemtime($path);
            }
        }
        // ไม่มีไฟล์ → transparent fallback
        return BRAND_TRANSPARENT_PNG;
    }
}

if (!function_exists('brand_logo_exists')) {
    /**
     * เช็คว่ามีโลโก้อัปโหลดอยู่หรือไม่
     */
    function brand_logo_exists(): bool
    {
        $exts = ['svg', 'png', 'jpg', 'jpeg', 'webp', 'ico'];
        foreach ($exts as $ext) {
            if (file_exists(public_path('brand/logo.' . $ext))) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('brand_favicon')) {
    /**
     * ดึง favicon URL — ถ้ามีโลโก้เป็น PNG/JPG ใช้ favicon.png ที่ generate ไว้
     * ถ้าเป็น SVG ใช้ SVG ตรงๆ (เบราว์เซอร์รองรับ)
     * ถ้าไม่มี → transparent
     */
    function brand_favicon(): string
    {
        // favicon.png ที่ generate จาก PNG/JPG
        if (file_exists(public_path('brand/favicon.png'))) {
            return asset('brand/favicon.png') . '?v=' . filemtime(public_path('brand/favicon.png'));
        }
        // SVG/PNG/JPG/ICO ตรงๆ
        $exts = ['ico', 'png', 'svg', 'jpg', 'jpeg', 'webp'];
        foreach ($exts as $ext) {
            $path = public_path('brand/logo.' . $ext);
            if (file_exists($path)) {
                return asset('brand/logo.' . $ext) . '?v=' . filemtime($path);
            }
        }
        return BRAND_TRANSPARENT_PNG;
    }
}
