<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateHapaCore extends AbstractMigration
{
    public function change(): void
    {
        $this->table('marketplaces')
            ->addColumn('code', 'string', ['limit' => 64])
            ->addColumn('name', 'string', ['limit' => 160])
            ->addColumn('adapter_key', 'string', ['limit' => 120])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addTimestamps()
            ->addIndex(['code'], ['unique' => true])
            ->create();

        $this->table('orders')
            ->addColumn('marketplace_id', 'integer')
            ->addColumn('external_order_id', 'string', ['limit' => 160])
            ->addColumn('status', 'string', ['limit' => 64])
            ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'EUR'])
            ->addColumn('shipping_address', 'json', ['null' => true])
            ->addColumn('accepted_at', 'datetime', ['null' => true])
            ->addColumn('completed_at', 'datetime', ['null' => true])
            ->addColumn('version', 'integer', ['default' => 1])
            ->addTimestamps()
            ->addIndex(['marketplace_id', 'external_order_id'], ['unique' => true])
            ->addIndex(['status', 'created_at'])
            ->addForeignKey('marketplace_id', 'marketplaces', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('order_lines')
            ->addColumn('order_id', 'integer')
            ->addColumn('sku', 'string', ['limit' => 160])
            ->addColumn('ean', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('quantity_ordered', 'integer')
            ->addColumn('quantity_available', 'integer', ['default' => 0])
            ->addColumn('quantity_to_ship', 'integer', ['default' => 0])
            ->addColumn('quantity_to_cancel', 'integer', ['default' => 0])
            ->addColumn('partial_reason', 'string', ['limit' => 255, 'null' => true])
            ->addTimestamps()
            ->addIndex(['order_id', 'sku'])
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('shipments')
            ->addColumn('order_id', 'integer')
            ->addColumn('provider', 'string', ['limit' => 64, 'default' => 'GLS'])
            ->addColumn('external_shipment_id', 'string', ['limit' => 160, 'null' => true])
            ->addColumn('tracking_number', 'string', ['limit' => 160, 'null' => true])
            ->addColumn('label_reference', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 64])
            ->addColumn('packages', 'integer', ['default' => 1])
            ->addColumn('weight_kg', 'decimal', ['precision' => 10, 'scale' => 3, 'null' => true])
            ->addTimestamps()
            ->addIndex(['order_id'])
            ->addIndex(['tracking_number'], ['unique' => true])
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('outbox_messages')
            ->addColumn('aggregate_type', 'string', ['limit' => 120])
            ->addColumn('aggregate_id', 'string', ['limit' => 160])
            ->addColumn('event_type', 'string', ['limit' => 160])
            ->addColumn('payload', 'json')
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'pending'])
            ->addColumn('idempotency_key', 'string', ['limit' => 160])
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('available_at', 'datetime')
            ->addColumn('locked_at', 'datetime', ['null' => true])
            ->addColumn('completed_at', 'datetime', ['null' => true])
            ->addColumn('last_error', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex(['idempotency_key'], ['unique' => true])
            ->addIndex(['status', 'available_at'])
            ->create();

        $this->table('external_deliveries')
            ->addColumn('order_id', 'integer', ['null' => true])
            ->addColumn('provider', 'string', ['limit' => 64])
            ->addColumn('operation', 'string', ['limit' => 160])
            ->addColumn('idempotency_key', 'string', ['limit' => 160])
            ->addColumn('request_payload', 'json', ['null' => true])
            ->addColumn('response_payload', 'json', ['null' => true])
            ->addColumn('http_status', 'integer', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 32])
            ->addColumn('attempt', 'integer', ['default' => 1])
            ->addColumn('correlation_id', 'string', ['limit' => 160])
            ->addColumn('error_code', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex(['idempotency_key', 'attempt'], ['unique' => true])
            ->addIndex(['provider', 'status', 'created_at'])
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();

        $this->table('audit_logs')
            ->addColumn('actor_id', 'string', ['limit' => 160, 'null' => true])
            ->addColumn('action', 'string', ['limit' => 160])
            ->addColumn('entity_type', 'string', ['limit' => 120])
            ->addColumn('entity_id', 'string', ['limit' => 160])
            ->addColumn('before_data', 'json', ['null' => true])
            ->addColumn('after_data', 'json', ['null' => true])
            ->addColumn('correlation_id', 'string', ['limit' => 160, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['entity_type', 'entity_id', 'created_at'])
            ->create();
    }
}
