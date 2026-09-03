<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrScanLog extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'qrcode_id', 'raw_token', 'customer_id', 'org_node_id', 'result',
        'points_awarded', 'ip_address', 'user_agent', 'lat', 'lng', 'scanned_at',
    ];
    protected $casts = ['scanned_at' => 'datetime', 'points_awarded' => 'integer'];

    public function qrcode(): BelongsTo
    {
        return $this->belongsTo(ProductQrcode::class, 'qrcode_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
