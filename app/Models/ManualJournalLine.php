<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualJournalLine extends Model
{
    protected $fillable = ['manual_journal_id', 'account_id', 'debit', 'credit', 'description'];
    protected $casts = ['debit' => 'decimal:2', 'credit' => 'decimal:2'];

    public function journal(): BelongsTo { return $this->belongsTo(ManualJournal::class, 'manual_journal_id'); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
}
