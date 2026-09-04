<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'org_node_id');
    }

    public function isBalanced(): bool
    {
        $debit  = $this->lines()->sum('debit');
        $credit = $this->lines()->sum('credit');
        return bccomp($debit, $credit, 2) === 0;
    }
}
