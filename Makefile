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

# Логи
logs:
	$(COMPOSE) logs -f

# Подключение в Postgres
psql:
	$(COMPOSE) exec db psql -U postgres -d social

# Подключение в Grafana (bash внутрь)
grafana:
	$(COMPOSE) exec grafana sh

migrate:
	$(COMPOSE) exec app php artisan migrate --force