<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

final class OrderStateMachine
{
    /** @return list<OrderStatus> */
    public static function allowedTransitionsFrom(OrderStatus $status): array
    {
        return match ($status) {
            OrderStatus::New,
            OrderStatus::Imported => [
                OrderStatus::Accepted,
                OrderStatus::Cancelled,
                OrderStatus::ManualReview,
            ],
            OrderStatus::Accepted => [
                OrderStatus::WaitingAddress,
                OrderStatus::SentToSpace,
                OrderStatus::Cancelled,
                OrderStatus::ManualReview,
            ],
            OrderStatus::WaitingAddress => [
                OrderStatus::Accepted,
                OrderStatus::Cancelled,
                OrderStatus::ManualReview,
            ],
            OrderStatus::SentToSpace => [
                OrderStatus::WaitingGoods,
                OrderStatus::ManualReview,
            ],
            OrderStatus::WaitingGoods => [
                OrderStatus::GoodsAvailable,
                OrderStatus::PartialAvailable,
                OrderStatus::Cancelled,
                OrderStatus::ManualReview,
            ],
            OrderStatus::GoodsAvailable => [
                OrderStatus::WaitingGoods,
                OrderStatus::PartialAvailable,
                OrderStatus::Picking,
                OrderStatus::Cancelled,
                OrderStatus::ManualReview,
            ],
            OrderStatus::PartialAvailable => [
                OrderStatus::WaitingGoods,
                OrderStatus::GoodsAvailable,
                OrderStatus::PartialConfirmed,
                OrderStatus::Cancelled,
                OrderStatus::ManualReview,
            ],
            OrderStatus::PartialConfirmed => [
                OrderStatus::Picking,
                OrderStatus::Cancelled,
                OrderStatus::ManualReview,
            ],
            OrderStatus::Picking => [
                OrderStatus::ReadyForCarrier,
                OrderStatus::ManualReview,
            ],
            OrderStatus::ReadyForCarrier => [
                OrderStatus::LabelAvailable,
                OrderStatus::ManualReview,
            ],
            OrderStatus::LabelAvailable => [
                OrderStatus::TrackingSent,
                OrderStatus::ManualReview,
            ],
            OrderStatus::TrackingSent => [
                OrderStatus::FulfilmentCompleted,
                OrderStatus::CompletedPartial,
                OrderStatus::ManualReview,
            ],
            OrderStatus::ManualReview => [
                OrderStatus::New,
                OrderStatus::Imported,
                OrderStatus::Accepted,
                OrderStatus::WaitingAddress,
                OrderStatus::SentToSpace,
                OrderStatus::WaitingGoods,
                OrderStatus::GoodsAvailable,
                OrderStatus::PartialAvailable,
                OrderStatus::PartialConfirmed,
                OrderStatus::Picking,
                OrderStatus::ReadyForCarrier,
                OrderStatus::LabelAvailable,
                OrderStatus::TrackingSent,
            ],
            OrderStatus::FulfilmentCompleted,
            OrderStatus::CompletedPartial,
            OrderStatus::Cancelled => [],
        };
    }

    public static function assertCanTransition(OrderStatus $from, OrderStatus $to): void
    {
        if (!in_array($to, self::allowedTransitionsFrom($from), true)) {
            throw new InvalidOrderTransition($from, $to);
        }
    }

    public static function isTerminal(OrderStatus $status): bool
    {
        return self::allowedTransitionsFrom($status) === [];
    }
}
