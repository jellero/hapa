<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RegisterShippingCarriers extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE orders DROP CONSTRAINT orders_status_check');
        $this->execute("UPDATE orders SET status = 'ready_for_carrier' WHERE status = 'ready_for_gls'");
        $this->addOrderStatusConstraint('ready_for_carrier');

        $this->execute(<<<'SQL'
ALTER TABLE shipments
    ADD CONSTRAINT shipments_provider_check CHECK (provider IN ('GLS', 'BRT'))
SQL);
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_provider_check');

        $this->execute('ALTER TABLE orders DROP CONSTRAINT orders_status_check');
        $this->execute("UPDATE orders SET status = 'ready_for_gls' WHERE status = 'ready_for_carrier'");
        $this->addOrderStatusConstraint('ready_for_gls');
    }

    private function addOrderStatusConstraint(string $readyStatus): void
    {
        $this->execute(sprintf(<<<'SQL'
ALTER TABLE orders
    ADD CONSTRAINT orders_status_check CHECK (status IN (
        'new', 'accepted', 'waiting_address', 'imported', 'sent_to_space',
        'waiting_goods', 'goods_available', 'partial_available', 'picking',
        'partial_confirmed', '%s', 'label_available',
        'tracking_sent', 'fulfilment_completed', 'completed_partial', 'cancelled',
        'manual_review'
    ))
SQL, $readyStatus));
    }
}
