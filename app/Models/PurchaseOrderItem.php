<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'product_id', 'description', 'qty', 'unit_price', 'line_total', 'received_qty'];
    protected $casts = [
        'qty'          => 'decimal:2',
        'unit_price'   => 'decimal:2',
        'line_total'   => 'decimal:2',
        'received_qty' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
