<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class HardenOrderLineAvailability extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE order_lines
    ADD CONSTRAINT order_lines_ship_not_above_available
    CHECK (quantity_to_ship <= quantity_available)
SQL);
    }

    public function down(): void
    {
        $this->execute(
            'ALTER TABLE order_lines DROP CONSTRAINT IF EXISTS order_lines_ship_not_above_available',
        );
    }
}
