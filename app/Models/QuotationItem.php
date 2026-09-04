<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $fillable = ['quotation_id', 'description', 'qty', 'unit_price', 'line_total'];
    protected $casts = ['qty' => 'decimal:2', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
}
