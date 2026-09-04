<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualJournal extends Model
{
    protected $fillable = [
        'org_node_id', 'doc_no', 'entry_date', 'description',
        'status', 'notes', 'reversed_by_id', 'created_by',
    ];

    protected $casts = ['entry_date' => 'date'];

    public function lines(): HasMany { return $this->hasMany(ManualJournalLine::class); }
    public function node(): BelongsTo { return $this->belongsTo(OrgNode::class, 'org_node_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reversedBy(): BelongsTo { return $this->belongsTo(self::class, 'reversed_by_id'); }

    public function isBalanced(): bool
    {
        $debit = $this->lines->sum('debit');
        $credit = $this->lines->sum('credit');
        return bccomp($debit, $credit, 2) === 0;
    }
}
