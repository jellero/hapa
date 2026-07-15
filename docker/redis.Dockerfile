ARG REDIS_BASE_IMAGE=redis:7.4-alpine
FROM ${REDIS_BASE_IMAGE}

COPY docker/redis-entrypoint.sh /usr/local/bin/hapa-redis-entrypoint
RUN chmod 0755 /usr/local/bin/hapa-redis-entrypoint \
    && mkdir -p /data \
    && chown redis:redis /data

ENTRYPOINT ["/usr/local/bin/hapa-redis-entrypoint"]
