<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function node(): BelongsTo { return $this->belongsTo(OrgNode::class, 'org_node_id'); }

    public function typeLabel(): string
    {
        return match($this->type) {
            'asset' => 'สินทรัพย์',
            'liability' => 'หนี้สิน',
            'equity' => 'ทุน',
            'revenue' => 'รายได้',
            'expense' => 'ค่าใช้จ่าย',
        };
    }

    public function typeColor(): string
    {
        return match($this->type) {
            'asset' => 'b-blue',
            'liability' => 'b-red',
            'equity' => 'b-green',
            'revenue' => 'b-green',
            'expense' => 'b-amber',
        };
    }
}
