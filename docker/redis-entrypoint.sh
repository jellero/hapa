#!/bin/sh
set -eu

secret_file="${REDIS_PASSWORD_FILE:-/run/secrets/redis_password}"

if [ ! -r "$secret_file" ]; then
    echo "Redis secret file non leggibile: $secret_file" >&2
    exit 1
fi

password="$(cat "$secret_file")"
if [ -z "$password" ]; then
    echo "Redis secret vuoto" >&2
    exit 1
fi

escaped_password="$(printf '%s' "$password" | sed 's/\\/\\\\/g; s/"/\\"/g')"
umask 077
{
    printf '%s\n' 'appendonly yes'
    printf 'requirepass "%s"\n' "$escaped_password"
} > /tmp/hapa-redis.conf

unset password escaped_password
exec redis-server /tmp/hapa-redis.conf
