# Контейнеры
APP_CONTAINER=lumen_app
DB_CONTAINER=lumen_postgres
NGINX_CONTAINER=lumen_nginx

# Docker image
APP_IMAGE=social-app-app

# Сборка + запуск
up: net
	docker build -t $(APP_IMAGE) -f docker/php/Dockerfile .
	docker run -d --name $(APP_CONTAINER) --network social-net -v $$PWD/lumen-app:/var/www $(APP_IMAGE)
	docker run -d --name $(DB_CONTAINER) --network social-net -e POSTGRES_DB=social -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=secret -p 5433:5432 postgres:16
	docker run -d --name $(NGINX_CONTAINER) --network social-net -v $$PWD/lumen-app:/var/www -v $$PWD/docker/nginx/default.conf:/etc/nginx/conf.d/default.conf -p 8080:80 nginx:stable-alpine

# Создание сети, если не существует
net:
	@docker network inspect social-net >/dev/null 2>&1 || docker network create social-net

# Перезапуск
stop:
	docker stop $(APP_CONTAINER) $(DB_CONTAINER) $(NGINX_CONTAINER) || true
	docker rm -f $(APP_CONTAINER) $(DB_CONTAINER) $(NGINX_CONTAINER) || true

restart: stop up

# Полная пересборка приложения
rebuild: prune
	docker rmi -f $(APP_IMAGE) || true
	make up

# Composer
composer-install:
	docker exec -it $(APP_CONTAINER) composer install

composer-dump:
	docker exec -it $(APP_CONTAINER) composer dump-autoload

# Artisan (если используешь)
migrate:
	docker exec -it $(APP_CONTAINER) php artisan migrate

# Войти в контейнер
sh:
	docker exec -it $(APP_CONTAINER) sh

bash:
	docker exec -it $(APP_CONTAINER) bash

# Инициализация БД
init-db:
	cat init.sql | docker exec -i $(DB_CONTAINER) psql -U postgres -d social

# Логи
logs-app:
	docker logs -f $(APP_CONTAINER)

logs-db:
	docker logs -f $(DB_CONTAINER)

logs-nginx:
	docker logs -f $(NGINX_CONTAINER)

# Очистка
prune:
	docker rm -f $(APP_CONTAINER) $(DB_CONTAINER) $(NGINX_CONTAINER) || true
	@docker network inspect social-net >/dev/null 2>&1 && docker network rm social-net || true