# Контейнеры
APP_CONTAINER=lumen_app
DB_CONTAINER=lumen_postgres
NGINX_CONTAINER=lumen_nginx

# Docker image
APP_IMAGE=social-app-app

# Полная сборка + запуск + инициализация БД
init: net
	docker build -t $(APP_IMAGE) -f docker/php/Dockerfile .
	docker run -d --name $(APP_CONTAINER) --network social-net -v $$PWD/lumen-app:/var/www $(APP_IMAGE)
	docker run -d --name $(DB_CONTAINER) --network social-net -e POSTGRES_DB=social -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=secret -p 5433:5432 postgres:16
	docker run -d --name $(NGINX_CONTAINER) --network social-net -v $$PWD/lumen-app:/var/www -v $$PWD/docker/nginx/default.conf:/etc/nginx/conf.d/default.conf -p 8080:80 nginx:stable-alpine
	# Ждём готовность БД перед выполнением init.sql
	sleep 3
	make init-db

up: net
	docker start $(APP_CONTAINER) || docker run -d --name $(APP_CONTAINER) --network social-net -v $$PWD/lumen-app:/var/www $(APP_IMAGE)
	docker start $(DB_CONTAINER) || docker run -d --name $(DB_CONTAINER) --network social-net -e POSTGRES_DB=social -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=secret -p 5433:5432 postgres:16
	docker start $(NGINX_CONTAINER) || docker run -d --name $(NGINX_CONTAINER) --network social-net -v $$PWD/lumen-app:/var/www -v $$PWD/docker/nginx/default.conf:/etc/nginx/conf.d/default.conf -p 8080:80 nginx:stable-alpine

down:
	docker rm -f $(APP_CONTAINER) $(DB_CONTAINER) $(NGINX_CONTAINER) || true
	@docker network inspect social-net >/dev/null 2>&1 && docker network rm social-net || true

# Создание сети, если не существует
net:
	@docker network inspect social-net >/dev/null 2>&1 || docker network create social-net

# Инициализация БД из init.sql
init-db:
	cat init.sql | docker exec -i $(DB_CONTAINER) psql -U postgres -d social