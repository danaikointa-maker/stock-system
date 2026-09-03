<?php

namespace App\Enums;

enum MovementType: string
{
    case Receipt     = 'receipt';
    case TransferOut = 'transfer_out';
    case TransferIn  = 'transfer_in';
    case Sale        = 'sale';
    case ReturnIn    = 'return_in';
    case ReturnOut   = 'return_out';
    case AdjustIn    = 'adjust_in';
    case AdjustOut   = 'adjust_out';
    case Damage      = 'damage';
    case Expired     = 'expired';

    public function direction(): string
    {
        return match ($this) {
            self::Receipt, self::TransferIn, self::ReturnIn, self::AdjustIn => 'in',
            default => 'out',
        };
    }
}
