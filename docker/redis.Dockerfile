FROM redis:7.4-alpine

COPY --chown=redis:redis docker/redis-entrypoint.sh /usr/local/bin/hapa-redis-entrypoint
RUN chmod 0555 /usr/local/bin/hapa-redis-entrypoint \
    && mkdir -p /data \
    && chown -R redis:redis /data

USER redis
ENTRYPOINT ["/usr/local/bin/hapa-redis-entrypoint"]
