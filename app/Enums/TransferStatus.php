<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Draft          = 'draft';
    case PendingApprove = 'pending_approve';
    case Approved       = 'approved';
    case Rejected       = 'rejected';
    case Shipped        = 'shipped';
    case Received       = 'received';
    case Cancelled      = 'cancelled';
}
