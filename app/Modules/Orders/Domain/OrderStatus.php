<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

enum OrderStatus: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case WaitingAddress = 'waiting_address';
    case Imported = 'imported';
    case SentToSpace = 'sent_to_space';
    case WaitingGoods = 'waiting_goods';
    case Complete = 'complete';
    case PartialAvailable = 'partial_available';
    case Picking = 'picking';
    case PartialConfirmed = 'partial_confirmed';
    case ReadyForGls = 'ready_for_gls';
    case LabelAvailable = 'label_available';
    case TrackingSent = 'tracking_sent';
    case Completed = 'completed';
    case CompletedPartial = 'completed_partial';
    case Cancelled = 'cancelled';
    case ManualReview = 'manual_review';
}
