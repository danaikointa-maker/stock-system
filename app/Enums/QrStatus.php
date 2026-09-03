<?php

namespace App\Enums;

enum QrStatus: string
{
    case Created  = 'created';
    case InStock  = 'in_stock';
    case Sold     = 'sold';
    case Redeemed = 'redeemed';
    case Void     = 'void';
}
