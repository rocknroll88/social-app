COMPOSE=docker-compose

# Полная сборка + запуск
init:
	$(COMPOSE) up -d --build

# Запуск (если всё уже собрано)
up:
	$(COMPOSE) up -d

# Остановка контейнеров
down:
	$(COMPOSE) down

# Полная очистка + удаление volume'ов (осторожно!)
clean:
	$(COMPOSE) down -v

# Перезапуск без пересборки
restart:
	$(COMPOSE) restart

chat-restart:
	$(COMPOSE) restart chat-service app nginx

# Логи
logs:
	$(COMPOSE) logs -f

chat-logs:
	$(COMPOSE) logs -f chat-service

# Подключение в Postgres
psql:
	$(COMPOSE) exec db psql -U postgres -d social

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
