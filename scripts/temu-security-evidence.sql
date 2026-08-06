\pset pager off
\x on

-- Eseguire con psql direttamente sul database. Non impostare hapa.pii_key:
-- lo screenshot deve mostrare i valori cifrati realmente presenti a riposo.
SELECT
    customer_code,
    display_name,
    email,
    phone,
    tax_identifier,
    vat_number,
    email_normalized
FROM customers
ORDER BY id DESC
LIMIT 3;

SELECT
    id,
    label,
    recipient,
    address_line1,
    address_line2,
    postal_code,
    city,
    province,
    phone,
    country_code
FROM customer_addresses
ORDER BY id DESC
LIMIT 3;

SELECT
    order_number,
    shipping_address,
    billing_address
FROM orders
WHERE shipping_address IS NOT NULL OR billing_address IS NOT NULL
ORDER BY id DESC
LIMIT 3;

SELECT
    trigger_name,
    event_object_table,
    action_timing,
    event_manipulation
FROM information_schema.triggers
WHERE trigger_name IN (
    'customers_encrypt_pii',
    'customer_addresses_encrypt_pii',
    'orders_encrypt_addresses',
    'customer_history_encrypt_snapshot',
    'inbox_messages_encrypt_payload',
    'outbox_messages_encrypt_payload'
)
ORDER BY event_object_table, trigger_name;

SELECT extname, extversion
FROM pg_extension
WHERE extname = 'pgcrypto';
