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
    case GoodsAvailable = 'goods_available';
    case PartialAvailable = 'partial_available';
    case Picking = 'picking';
    case PartialConfirmed = 'partial_confirmed';
    case ReadyForCarrier = 'ready_for_carrier';
    case LabelAvailable = 'label_available';
    case TrackingSent = 'tracking_sent';
    case FulfilmentCompleted = 'fulfilment_completed';
    case CompletedPartial = 'completed_partial';
    case Cancelled = 'cancelled';
    case ManualReview = 'manual_review';
}
