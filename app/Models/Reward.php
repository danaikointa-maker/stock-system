<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    protected $fillable = ['name', 'points_cost', 'stock_qty', 'image_url', 'status'];
    protected $casts = ['points_cost' => 'integer', 'stock_qty' => 'integer'];

    public function scopeActive($q)
    {
        return $q->where('status', 'active')->where('stock_qty', '>', 0);
    }
}
