<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Orders;

use Hapa\Modules\Orders\Domain\InvalidOrderTransition;
use Hapa\Modules\Orders\Domain\OrderStateMachine;
use Hapa\Modules\Orders\Domain\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderStateMachineTest extends TestCase
{
    /** @param list<OrderStatus> $expectedTransitions */
    #[DataProvider('transitionMatrix')]
    public function testEveryStatusHasAnExplicitTransitionDefinition(
        OrderStatus $status,
        array $expectedTransitions,
    ): void
    {
        self::assertSame($expectedTransitions, OrderStateMachine::allowedTransitionsFrom($status));

        foreach (OrderStatus::cases() as $target) {
            if (in_array($target, $expectedTransitions, true)) {
                OrderStateMachine::assertCanTransition($status, $target);

                continue;
            }

            try {
                OrderStateMachine::assertCanTransition($status, $target);
            } catch (InvalidOrderTransition) {
                continue;
            }

            self::fail(sprintf(
                'La transizione da %s a %s doveva essere rifiutata.',
                $status->value,
                $target->value,
            ));
        }
    }

    public function testItDefinesTerminalStates(): void
    {
        self::assertTrue(OrderStateMachine::isTerminal(OrderStatus::FulfilmentCompleted));
        self::assertTrue(OrderStateMachine::isTerminal(OrderStatus::CompletedPartial));
        self::assertTrue(OrderStateMachine::isTerminal(OrderStatus::Cancelled));
        self::assertFalse(OrderStateMachine::isTerminal(OrderStatus::Imported));
    }

    /** @return iterable<string, array{OrderStatus, list<OrderStatus>}> */
    public static function transitionMatrix(): iterable
    {
        yield 'new' => [OrderStatus::New, [
            OrderStatus::Accepted,
            OrderStatus::Cancelled,
            OrderStatus::ManualReview,
        ]];
        yield 'imported' => [OrderStatus::Imported, [
            OrderStatus::Accepted,
            OrderStatus::Cancelled,
            OrderStatus::ManualReview,
        ]];
        yield 'accepted' => [OrderStatus::Accepted, [
            OrderStatus::WaitingAddress,
            OrderStatus::SentToSpace,
            OrderStatus::Cancelled,
            OrderStatus::ManualReview,
        ]];
        yield 'waiting address' => [OrderStatus::WaitingAddress, [
            OrderStatus::Accepted,
            OrderStatus::Cancelled,
            OrderStatus::ManualReview,
        ]];
        yield 'sent to Space' => [OrderStatus::SentToSpace, [
            OrderStatus::WaitingGoods,
            OrderStatus::ManualReview,
        ]];
        yield 'waiting goods' => [OrderStatus::WaitingGoods, [
            OrderStatus::GoodsAvailable,
            OrderStatus::PartialAvailable,
            OrderStatus::Cancelled,
            OrderStatus::ManualReview,
        ]];
        yield 'goods available' => [OrderStatus::GoodsAvailable, [
            OrderStatus::WaitingGoods,
            OrderStatus::PartialAvailable,
            OrderStatus::Picking,
            OrderStatus::Cancelled,
            OrderStatus::ManualReview,
        ]];
        yield 'partial available' => [OrderStatus::PartialAvailable, [
            OrderStatus::WaitingGoods,
            OrderStatus::GoodsAvailable,
            OrderStatus::PartialConfirmed,
            OrderStatus::Cancelled,
            OrderStatus::ManualReview,
        ]];
        yield 'partial confirmed' => [OrderStatus::PartialConfirmed, [
            OrderStatus::Picking,
            OrderStatus::Cancelled,
            OrderStatus::ManualReview,
        ]];
        yield 'picking' => [OrderStatus::Picking, [
            OrderStatus::ReadyForCarrier,
            OrderStatus::ManualReview,
        ]];
        yield 'ready for carrier' => [OrderStatus::ReadyForCarrier, [
            OrderStatus::LabelAvailable,
            OrderStatus::ManualReview,
        ]];
        yield 'label available' => [OrderStatus::LabelAvailable, [
            OrderStatus::TrackingSent,
            OrderStatus::ManualReview,
        ]];
        yield 'tracking sent' => [OrderStatus::TrackingSent, [
            OrderStatus::FulfilmentCompleted,
            OrderStatus::CompletedPartial,
            OrderStatus::ManualReview,
        ]];
        yield 'manual review' => [OrderStatus::ManualReview, [
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
        ]];
        yield 'fulfilment completed' => [OrderStatus::FulfilmentCompleted, []];
        yield 'completed partial' => [OrderStatus::CompletedPartial, []];
        yield 'cancelled' => [OrderStatus::Cancelled, []];
    }
}
