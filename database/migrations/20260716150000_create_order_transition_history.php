<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOrderTransitionHistory extends AbstractMigration
{
    private const STATUSES = <<<'SQL'
        'new', 'accepted', 'waiting_address', 'imported', 'sent_to_space',
        'waiting_goods', 'goods_available', 'partial_available', 'picking',
        'partial_confirmed', 'ready_for_carrier', 'label_available',
        'tracking_sent', 'fulfilment_completed', 'completed_partial', 'cancelled',
        'manual_review'
SQL;

    public function up(): void
    {
        $this->execute('ALTER TABLE order_lines ADD COLUMN line_number INTEGER NULL');
        $this->execute(<<<'SQL'
WITH numbered AS (
    SELECT id, row_number() OVER (PARTITION BY order_id ORDER BY id)::INTEGER AS line_number
    FROM order_lines
)
UPDATE order_lines
SET line_number = numbered.line_number
FROM numbered
WHERE order_lines.id = numbered.id
SQL);
        $this->execute(<<<'SQL'
ALTER TABLE order_lines
    ALTER COLUMN line_number SET NOT NULL,
    ADD CONSTRAINT order_lines_line_number_check CHECK (line_number > 0),
    ADD CONSTRAINT order_lines_order_line_number_unique UNIQUE (order_id, line_number)
SQL);

        $statuses = self::STATUSES;
        $this->execute(sprintf(<<<'SQL'
CREATE TABLE order_transitions (
    id BIGSERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    from_status VARCHAR(64) NOT NULL,
    to_status VARCHAR(64) NOT NULL,
    reason VARCHAR(255) NULL,
    version INTEGER NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT order_transitions_order_fk
        FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT order_transitions_from_status_check CHECK (from_status IN (%1$s)),
    CONSTRAINT order_transitions_to_status_check CHECK (to_status IN (%1$s)),
    CONSTRAINT order_transitions_state_change_check CHECK (from_status <> to_status),
    CONSTRAINT order_transitions_reason_check CHECK (reason IS NULL OR btrim(reason) <> ''),
    CONSTRAINT order_transitions_version_check CHECK (version > 1),
    CONSTRAINT order_transitions_order_version_unique UNIQUE (order_id, version)
)
SQL, $statuses));
        $this->execute(
            'CREATE INDEX order_transitions_order_occurred_idx
             ON order_transitions (order_id, occurred_at, id)',
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS order_transitions');
        $this->execute('ALTER TABLE order_lines DROP CONSTRAINT IF EXISTS order_lines_order_line_number_unique');
        $this->execute('ALTER TABLE order_lines DROP CONSTRAINT IF EXISTS order_lines_line_number_check');
        $this->execute('ALTER TABLE order_lines DROP COLUMN IF EXISTS line_number');
    }
}
