<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ReviewCatalogItems extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE catalog_item_history (
    id BIGSERIAL PRIMARY KEY,
    catalog_item_id BIGINT NOT NULL,
    version INTEGER NOT NULL,
    action VARCHAR(32) NOT NULL,
    snapshot JSONB NOT NULL,
    actor_id CHAR(36) NULL,
    correlation_id VARCHAR(200) NULL,
    created_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT catalog_item_history_item_fk
        FOREIGN KEY (catalog_item_id) REFERENCES catalog_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT catalog_item_history_actor_fk
        FOREIGN KEY (actor_id) REFERENCES app_users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT catalog_item_history_version_unique UNIQUE (catalog_item_id, version),
    CONSTRAINT catalog_item_history_values_check CHECK (
        version > 0
        AND action IN ('approved', 'rejected')
        AND jsonb_typeof(snapshot) = 'object'
    )
)
SQL);
        $this->execute('CREATE INDEX catalog_item_history_timeline_idx ON catalog_item_history (catalog_item_id, created_at DESC, id DESC)');
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS catalog_item_history');
    }
}
