<?php

namespace App\Models;

use App\Enums\QrStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 1 แถว = สินค้า 1 ชิ้น (serialized) ใช้ทั้ง track & trace และรับคะแนน */
class ProductQrcode extends Model
{
    protected $fillable = [
        'product_id', 'lot_id', 'serial_no', 'qr_token', 'secret_hash',
        'points', 'current_node_id', 'status', 'activated_at',
        'redeemed_at', 'redeemed_by_customer_id', 'expires_at',
    ];

    protected $casts = [
        'status'       => QrStatus::class,
        'points'       => 'integer',
        'activated_at' => 'datetime',
        'redeemed_at'  => 'datetime',
        'expires_at'   => 'datetime',
    ];

    protected $hidden = ['secret_hash'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }

    public function currentNode(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'current_node_id');
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(QrScanLog::class, 'qrcode_id');
    }

    public function scanUrl(): string
    {
        return url('/s/' . $this->qr_token);
    }

    public function isRedeemable(): bool
    {
        return in_array($this->status, [QrStatus::Sold, QrStatus::InStock], true)
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    /** ตรวจรหัสใต้ฟิล์มขูด (กันคนถ่ายรูป QR บนชั้นวางไปสแกน) */
    public function verifySecret(?string $plain): bool
    {
        if (! $this->secret_hash) {
            return true; // สินค้าที่ไม่ได้ตั้งรหัสลับ
        }

        return hash_equals($this->secret_hash, hash('sha256', (string) $plain));
    }
}
