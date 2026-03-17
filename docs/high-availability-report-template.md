# High Availability Report

## Конфигурация

### PostgreSQL replicas + HAProxy

- Master: `db`
- Slaves: `pgslave1`, `pgslave2`
- Read balancer: `postgres-haproxy`
- Приложения читают из `DB_SLAVE1_HOST=postgres-haproxy`
- Запись идет напрямую в `DB_MASTER_HOST=db`
- Fallback read-трафика на master отключен через `DB_READ_FALLBACK_TO_MASTER=false`

Файл конфигурации HAProxy: [postgres.cfg](/Users/konstantin_savelev/PhpstormProjects/social-app/docker/haproxy/postgres.cfg)

Опишите:
- `frontend postgres_reads`
- `backend postgres_slaves`
- `roundrobin`
- `tcp-check`
- исключение недоступного слейва из пула

### Nginx + несколько приложений

- Backend instances: `app1`, `app2`, `app3`
- Балансировка через upstream `php_backend`

Файл конфигурации Nginx: [default-replicas.conf](/Users/konstantin_savelev/PhpstormProjects/social-app/docker/nginx/default-replicas.conf)

Опишите:
- `least_conn`
- `max_fails`
- `fail_timeout`
- `fastcgi_next_upstream`
- `fastcgi_next_upstream_tries`

## Условия эксперимента

- Compose: `docker-compose-with-replicas.yml`
- Нагрузка: [user-read-k6.js](/Users/konstantin_savelev/PhpstormProjects/social-app/load-tests/user-read-k6.js)
- Endpoint: `GET /user/get/{id}`
- Причина выбора: read-heavy запрос, идет через read connection PostgreSQL

Команда:

```bash
make ha-down
make ha-reset-data
make ha-init

docker-compose -f docker-compose-with-replicas.yml exec db \
  psql -U postgres -d social -c "SELECT user_id FROM users LIMIT 1;"

k6 run load-tests/user-read-k6.js \
  -e BASE_URL=http://localhost:8085 \
  -e USER_ID=<USER_ID> \
  -e VUS=40 \
  -e DURATION=120s
```

## Ход эксперимента

### Старт

```bash
make ha-init
make ha-ps
```

### Логи

```bash
make ha-logs
```

### Отключение PostgreSQL slave

```bash
make kill-slave1
```

Нужно зафиксировать:
- система продолжила отвечать
- HAProxy исключил `pgslave1`

### Отключение backend instance

```bash
make kill-app1
```

Нужно зафиксировать:
- система продолжила отвечать
- Nginx продолжил маршрутить запросы на `app2/app3`

## Логи для отчета

```bash
docker logs ha_postgres_haproxy
docker logs ha_nginx
docker logs ha_app1
docker logs ha_app2
docker logs ha_app3
docker logs ha_db
docker logs ha_pgslave1
docker logs ha_pgslave2
```

## Результаты

### До отказов

- `http_req_failed`:
- `http_req_duration p95`:
- `http_reqs`:

### После `kill -9 pgslave1`

- `http_req_failed`:
- `http_req_duration p95`:
- `http_reqs`:
- вывод по логам HAProxy:

### После `kill -9 app1`

- `http_req_failed`:
- `http_req_duration p95`:
- `http_reqs`:
- вывод по логам Nginx:

## Вывод

- система сохранила работоспособность после отключения PostgreSQL slave
- система сохранила работоспособность после отключения backend instance
- балансировщики корректно перераспределили трафик
