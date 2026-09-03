<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** รายการสินค้าที่แลก — ผูกกับล็อตจริง ตรวจย้อนกลับได้ */
class RedemptionItem extends Model
{
    protected $fillable = [
        'redemption_id', 'product_id', 'lot_id', 'qrcode_id', 'from_node_id',
        'qty', 'sku_snapshot', 'name_snapshot', 'lot_no_snapshot',
        'expiry_snapshot', 'unit_cost', 'points_each', 'points_total', 'movement_id',
    ];

    protected $casts = ['expiry_snapshot' => 'date'];

    public function redemption() { return $this->belongsTo(PointRedemption::class, 'redemption_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function lot() { return $this->belongsTo(ProductLot::class, 'lot_id'); }
}
