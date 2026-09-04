<?php

if (!function_exists('brand_logo')) {
    /**
     * ดึง URL โลโก้ปัจจุบัน — รองรับ SVG/PNG/JPG/WEBP/ICO
     * Admin อัปโหลดได้ → ทุกหน้าอัปเดตทันที (cache 1 ชม.)
     */
    function brand_logo(): string
    {
        $file = cache()->remember('brand_logo_file', 3600, function () {
            $exts = ['svg', 'png', 'jpg', 'jpeg', 'webp', 'ico'];
            foreach ($exts as $ext) {
                if (file_exists(public_path('brand/logo.' . $ext))) {
                    return 'logo.' . $ext;
                }
            }
            return 'logo.svg';
        });

        return asset('brand/' . $file . '?v=' . filemtime(public_path('brand/' . $file)));
    }
}

if (!function_exists('brand_logo_path')) {
    /**
     * ดึง file path จริงของโลโก้ (สำหรับ <link rel="icon">)
     */
    function brand_logo_path(): string
    {
        $exts = ['svg', 'png', 'jpg', 'jpeg', 'webp', 'ico'];
        foreach ($exts as $ext) {
            if (file_exists(public_path('brand/logo.' . $ext))) {
                return 'brand/logo.' . $ext;
            }
        }
        return 'brand/logo.svg';
    }
}
