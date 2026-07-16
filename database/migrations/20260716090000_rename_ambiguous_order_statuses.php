<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameAmbiguousOrderStatuses extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE orders DROP CONSTRAINT orders_status_check');
        $this->execute("UPDATE orders SET status = 'goods_available' WHERE status = 'complete'");
        $this->execute("UPDATE orders SET status = 'fulfilment_completed' WHERE status = 'completed'");
        $this->addStatusConstraint();
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE orders DROP CONSTRAINT orders_status_check');
        $this->execute("UPDATE orders SET status = 'complete' WHERE status = 'goods_available'");
        $this->execute("UPDATE orders SET status = 'completed' WHERE status = 'fulfilment_completed'");
        $this->execute(<<<'SQL'
ALTER TABLE orders
    ADD CONSTRAINT orders_status_check CHECK (status IN (
        'new', 'accepted', 'waiting_address', 'imported', 'sent_to_space',
        'waiting_goods', 'complete', 'partial_available', 'picking',
        'partial_confirmed', 'ready_for_gls', 'label_available',
        'tracking_sent', 'completed', 'completed_partial', 'cancelled',
        'manual_review'
    ))
SQL);
    }

    private function addStatusConstraint(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE orders
    ADD CONSTRAINT orders_status_check CHECK (status IN (
        'new', 'accepted', 'waiting_address', 'imported', 'sent_to_space',
        'waiting_goods', 'goods_available', 'partial_available', 'picking',
        'partial_confirmed', 'ready_for_gls', 'label_available',
        'tracking_sent', 'fulfilment_completed', 'completed_partial', 'cancelled',
        'manual_review'
    ))
SQL);
    }
}
