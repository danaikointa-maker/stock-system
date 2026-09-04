<?php

namespace App\Services;

use App\Models\DocSequence;

class DocSequenceService
{
    /**
     * สร้างเลขที่เอกสารอัตโนมัติ
     * รูปแบบ: {TYPE}{YY}{MM}{NNNN} เช่น INV2609-0001
     */
    public function next(string $type, int $orgNodeId, ?string $date = null): string
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();

        $seq = DocSequence::firstOrCreate(
            ['type' => $type, 'org_node_id' => $orgNodeId, 'year' => $date->year, 'month' => $date->month],
            ['last_number' => 0]
        );

        $seq->increment('last_number');
        $seq->refresh();

        $yy = str_pad($date->year % 100, 2, '0', STR_PAD_LEFT);
        $mm = str_pad($date->month, 2, '0', STR_PAD_LEFT);
        $nnnn = str_pad($seq->last_number, 4, '0', STR_PAD_LEFT);

        return "{$type}{$yy}{$mm}-{$nnnn}";
    }
}
