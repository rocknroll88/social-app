FROM postgres:16

COPY docker/postgres-slave/slave-entrypoint.sh /usr/local/bin/slave-entrypoint.sh

RUN chmod +x /usr/local/bin/slave-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/slave-entrypoint.sh"]
