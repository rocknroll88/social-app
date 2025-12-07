FROM citusdata/citus:12.1

COPY docker/citus/init-worker.sh /docker-entrypoint-initdb.d/init-worker.sh

RUN chmod +x /docker-entrypoint-initdb.d/init-worker.sh