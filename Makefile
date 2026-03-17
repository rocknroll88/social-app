COMPOSE=docker-compose
COMPOSE_HA=docker-compose -f docker-compose-with-replicas.yml

# Полная сборка + запуск
init:
	$(COMPOSE) up -d --build

# Запуск (если всё уже собрано)
up:
	$(COMPOSE) up -d

ha-init:
	$(COMPOSE_HA) up -d --build

ha-up:
	$(COMPOSE_HA) up -d

# Остановка контейнеров
down:
	$(COMPOSE) down

ha-down:
	$(COMPOSE_HA) down

# Полная очистка + удаление volume'ов (осторожно!)
clean:
	$(COMPOSE) down -v

ha-reset-data:
	rm -rf ./volumes/ha

# Перезапуск без пересборки
restart:
	$(COMPOSE) restart

chat-restart:
	$(COMPOSE) restart chat-service app nginx

ha-restart:
	$(COMPOSE_HA) restart nginx app1 app2 app3 postgres-haproxy pgslave1 pgslave2

kill-slave1:
	$(COMPOSE_HA) kill -s KILL pgslave1

kill-app1:
	$(COMPOSE_HA) kill -s KILL app1

# Логи
logs:
	$(COMPOSE) logs -f

chat-logs:
	$(COMPOSE) logs -f chat-service

ha-logs:
	$(COMPOSE_HA) logs -f nginx app1 app2 app3 db pgslave1 pgslave2 postgres-haproxy

ha-ps:
	$(COMPOSE_HA) ps

# Подключение в Postgres
psql:
	$(COMPOSE) exec db psql -U postgres -d social

ha-psql:
	$(COMPOSE_HA) exec db psql -U postgres -d social

# Подключение в Grafana (bash внутрь)
grafana:
	$(COMPOSE) exec grafana sh

migrate:
	$(COMPOSE) exec app php artisan migrate --force

# Запуск WebSocket сервера
websocket:
	$(COMPOSE) exec app php artisan websocket:serve --host=0.0.0.0 --port=8090

# Запуск воркера очередей
queue-worker:
	$(COMPOSE) exec app php artisan queue:work --connection=database --tries=3 --timeout=90
