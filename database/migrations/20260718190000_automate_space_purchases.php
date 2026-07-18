<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AutomateSpacePurchases extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE supplier_purchase_orders
    ADD COLUMN integration_account_id BIGINT NULL,
    ADD COLUMN auto_generated BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN last_error VARCHAR(1000) NULL,
    ADD CONSTRAINT supplier_purchase_orders_integration_account_fk
        FOREIGN KEY (integration_account_id) REFERENCES integration_accounts (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT supplier_purchase_orders_generation_values_check CHECK (
        (last_error IS NULL OR btrim(last_error) <> '')
        AND (NOT auto_generated OR status <> 'draft')
    )
SQL);
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX supplier_purchase_orders_automatic_unique
    ON supplier_purchase_orders (order_id, supplier_id)
    WHERE auto_generated
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX supplier_purchase_orders_integration_account_idx
    ON supplier_purchase_orders (integration_account_id, status, updated_at DESC)
    WHERE integration_account_id IS NOT NULL
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP INDEX IF EXISTS supplier_purchase_orders_integration_account_idx');
        $this->execute('DROP INDEX IF EXISTS supplier_purchase_orders_automatic_unique');
        $this->execute('ALTER TABLE supplier_purchase_orders DROP CONSTRAINT IF EXISTS supplier_purchase_orders_generation_values_check');
        $this->execute('ALTER TABLE supplier_purchase_orders DROP CONSTRAINT IF EXISTS supplier_purchase_orders_integration_account_fk');
        $this->execute(<<<'SQL'
ALTER TABLE supplier_purchase_orders
    DROP COLUMN IF EXISTS last_error,
    DROP COLUMN IF EXISTS auto_generated,
    DROP COLUMN IF EXISTS integration_account_id
SQL);
    }
}
