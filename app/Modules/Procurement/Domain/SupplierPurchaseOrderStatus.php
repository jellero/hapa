<?php

declare(strict_types=1);

namespace Hapa\Modules\Procurement\Domain;

enum SupplierPurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Requested = 'requested';
    case Accepted = 'accepted';
    case PartiallyAvailable = 'partially_available';
    case Ready = 'ready';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case ManualReview = 'manual_review';
}
