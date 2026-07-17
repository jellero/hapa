<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ManagePricingRules extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE pricing_rules
    ADD COLUMN version INTEGER NOT NULL DEFAULT 1,
    ADD COLUMN retired_at TIMESTAMPTZ NULL,
    ADD CONSTRAINT pricing_rules_version_check CHECK (version > 0)
SQL);
        $this->execute(<<<'SQL'
CREATE TABLE pricing_rule_history (
    id BIGSERIAL PRIMARY KEY,
    pricing_rule_id BIGINT NOT NULL,
    version INTEGER NOT NULL,
    action VARCHAR(32) NOT NULL,
    snapshot JSONB NOT NULL,
    actor_id CHAR(36) NULL,
    correlation_id VARCHAR(200) NULL,
    created_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT pricing_rule_history_rule_fk
        FOREIGN KEY (pricing_rule_id) REFERENCES pricing_rules (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT pricing_rule_history_actor_fk
        FOREIGN KEY (actor_id) REFERENCES app_users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT pricing_rule_history_version_unique UNIQUE (pricing_rule_id, version),
    CONSTRAINT pricing_rule_history_values_check CHECK (
        version > 0
        AND action IN ('created', 'updated', 'retired')
        AND jsonb_typeof(snapshot) = 'object'
    )
)
SQL);
        $this->execute('CREATE INDEX pricing_rule_history_timeline_idx ON pricing_rule_history (pricing_rule_id, created_at DESC, id DESC)');
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS pricing_rule_history');
        $this->execute('ALTER TABLE pricing_rules DROP CONSTRAINT IF EXISTS pricing_rules_version_check');
        $this->execute('ALTER TABLE pricing_rules DROP COLUMN IF EXISTS retired_at');
        $this->execute('ALTER TABLE pricing_rules DROP COLUMN IF EXISTS version');
    }
}
