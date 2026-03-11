# 📘 Социальная сеть на Lumen + PostgreSQL

Простейшее API-приложение на PHP (Lumen) с авторизацией и анкетами пользователей, обёрнутое в Docker.

---

## 🚀 Быстрый старт

### 1. Клонируй репозиторий и перейди в директорию проекта

```bash
git clone <your-repo-url>
cd social-app
```

---

### 2. Запусти сборку и инициализацию

Это выполнит:

- сборку контейнера с PHP/Lumen
- запуск PostgreSQL и Nginx
- установку зависимостей
- прогрузку начальной структуры БД (`init.sql`)

```bash
make init
```

- 📥 Импорт пользователей из CSV (опционально)

Если ты хочешь предварительно заполнить базу тестовыми пользователями — в проекте уже лежит SQL-скрипт load_users.sql.
Его нужно запустить из под контейнера lumen_postgres.

---

### 3. Повторный запуск (без сборки)

```bash
make up
```

---

## 📦 Структура make-команд

| Команда          | Описание                                                       |
|------------------|----------------------------------------------------------------|
| `make init`      | Сборка, запуск контейнеров и инициализация БД                 |
| `make up`        | Запуск контейнеров без пересборки                             |
| `make down`      | Остановка и удаление контейнеров и сети                       |
| `make init-db`   | Повторная инициализация БД из `init.sql`                      |
| `make websocket` | Запуск WebSocket сервера для real-time уведомлений            |
| `make queue-worker` | Запуск обработчика очереди для асинхронных задач          |

---

## 🔌 Доступ к приложению

После запуска доступ к API будет по адресу:

```
http://localhost:8080
```

---

## 📮 Доступные методы API

### POST `/user/register`

Регистрация нового пользователя.

```json
{
  "first_name": "Имя",
  "second_name": "Фамилия",
  "birthdate": "2017-02-01",
  "biography": "Хобби, интересы и т.п.",
  "city": "Москва",
  "password": "secret123"
}
```

**Ответ:**

```json
{
  "user_id": "uuid"
}
```

---

### POST `/login`

Вход по `user_id` и `password`.

```json
{
  "id": "uuid",
  "password": "secret123"
}
```

**Ответ:**

```json
{
  "token": "abc123..."
}
```

---

### GET `/user/get/{id}`

Получить информацию о пользователе.

```json
{
  "id": "uuid",
  "first_name": "Имя",
  "second_name": "Фамилия",
  "birthdate": "2017-02-01",
  "biography": "Хобби, интересы и т.п.",
  "city": "Москва"
}
```

---

## 🔔 Real-Time уведомления через WebSocket

Приложение поддерживает real-time уведомления о новых постах друзей через WebSocket соединение.

### Архитектура системы

```
┌─────────────────┐
│  HTTP Request   │ Создание поста
│ (POST /post)    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ PostController  │
└────────┬────────┘
         │
         ▼
┌──────────────────────┐
│ ProcessPostCreation  │ Job для асинхронной обработки
│      (Queue)         │
└────────┬─────────────┘
         │
         ├─────────────────────────┐
         │                         │
         ▼                         ▼
┌──────────────────┐      ┌──────────────────────┐
│ FeedCacheService │      │ WebSocketNotification│
│     (Redis)      │      │   Service (RabbitMQ) │
└──────────────────┘      └──────────┬───────────┘
                                     │
                                     ▼
                          ┌──────────────────────┐
                          │   RabbitMQ Exchange  │
                          │   (Topic, Routing)   │
                          └──────────┬───────────┘
                                     │
                          Routing Key: user.{userId}.notification
                                     │
                                     ▼
                          ┌──────────────────────┐
                          │   User Queue         │
                          │  (персональная)      │
                          └──────────┬───────────┘
                                     │
                                     ▼
                          ┌──────────────────────┐
                          │  WebSocket Server    │
                          │   (Ratchet/ReactPHP) │
                          └──────────┬───────────┘
                                     │
                                     ▼
                          ┌──────────────────────┐
                          │  WebSocket Client    │
                          │     (Browser)        │
                          └──────────────────────┘
```

### Компоненты системы

#### 1. **WebSocket Server** (Ratchet + ReactPHP)
- Работает на порту `8090`
- Обрабатывает WebSocket соединения от клиентов
- Аутентификация через Bearer токен
- Подписывается на RabbitMQ очереди пользователей
- Отправляет сообщения только подключенным клиентам

#### 2. **RabbitMQ с Routing Keys**
- **Exchange**: `websocket_notifications` (тип: `topic`)
- **Routing Key**: `user.{userId}.notification`
- **Очереди**: Создаются динамически для каждого подключенного пользователя
- **Преимущество**: Сообщения доставляются **только целевым пользователям**

#### 3. **Redis** (кеширование лент)
- Хранит ленты пользователей в виде sorted sets
- Ключ: `user:feed:{userId}`
- Score: timestamp для сортировки
- Максимум 1000 постов на ленту

#### 4. **Queue Worker** (обработка задач)
- Обрабатывает задачи из таблицы `jobs`
- Материализует ленты асинхронно
- Отправляет WebSocket уведомления

### Запуск Real-Time системы

#### Шаг 1: Запустите WebSocket сервер

```bash
make websocket
```

Вы увидите:
```
Starting WebSocket server on 0.0.0.0:8090
WebSocket server started on ws://0.0.0.0:8090
Subscribe to /post/feed/posted for friend posts updates
RabbitMQ connection established
```

#### Шаг 2: Запустите Queue Worker (опционально)

Если нужна асинхронная обработка через очередь:

```bash
make queue-worker
```

#### Шаг 3: Подключитесь к WebSocket

Откройте тестовый клиент:
```
http://localhost:3002/websocket-client.html
```

Или используйте свой WebSocket клиент:

```javascript
const ws = new WebSocket('ws://localhost:8090');

ws.onopen = () => {
    // Аутентификация
    ws.send(JSON.stringify({
        action: 'auth',
        token: 'YOUR_BEARER_TOKEN'
    }));
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    
    if (data.action === 'authenticated') {
        // Подписка на канал
        ws.send(JSON.stringify({
            action: 'subscribe',
            channel: '/post/feed/posted'
        }));
    }
    
    if (data.channel === '/post/feed/posted') {
        console.log('New post from friend:', data.message);
    }
};
```

### Формат сообщений

#### Аутентификация
```json
{
  "action": "auth",
  "token": "your-bearer-token"
}
```

**Ответ:**
```json
{
  "action": "authenticated",
  "user_id": "uuid"
}
```

#### Подписка на канал
```json
{
  "action": "subscribe",
  "channel": "/post/feed/posted"
}
```

**Ответ:**
```json
{
  "action": "subscribed",
  "channel": "/post/feed/posted"
}
```

#### Уведомление о новом посте
```json
{
  "channel": "/post/feed/posted",
  "message": {
    "id": "post-uuid",
    "text": "Текст поста",
    "author_user_id": "author-uuid",
    "created_at": "2025-12-21T13:18:37+00:00"
  }
}
```

---

## 🛡️ Защита от "Celebrity Effect"

Система включает защиту от перегрузки при создании поста популярным пользователем (с большим количеством подписчиков).

### Механизм работы

1. **Ограничение на количество одновременных обновлений**
   - Настраивается через переменную окружения `MAX_POST_FOLLOWERS` (по умолчанию: 500)
   
2. **Случайная выборка подписчиков**
   - Если подписчиков больше лимита, выбирается случайная выборка
   - Остальные получат обновление позже (при следующем запросе ленты)

3. **Асинхронная обработка**
   - Все обновления происходят в фоне через очередь
   - Не блокирует HTTP-запрос создания поста

### Настройка в docker-compose.yml

```yaml
environment:
  - MAX_POST_FOLLOWERS=500  # Максимум подписчиков для обработки за раз
```

---

## 🧪 Тестирование WebSocket

### 1. Создайте пользователей и добавьте друга

```bash
# Создать токен для пользователя
docker exec lumen_postgres psql -U postgres -d social -c "
UPDATE users 
SET token = 'test-token-user1' 
WHERE user_id = (SELECT user_id FROM users LIMIT 1);
"

# Добавить связь дружбы
docker exec lumen_postgres psql -U postgres -d social -c "
INSERT INTO friends (user_id, friend_id)
SELECT 
  (SELECT user_id FROM users WHERE token = 'test-token-user1'),
  (SELECT user_id FROM users WHERE token IS NULL LIMIT 1)
ON CONFLICT DO NOTHING;
"
```

### 2. Откройте веб-клиент и подключитесь

```
http://localhost:3002/websocket-client.html
```

- Введите токен: `test-token-user1`
- Нажмите "Connect"
- Нажмите "Subscribe to /post/feed/posted"

### 3. Создайте пост от друга

```bash
curl -X POST http://localhost:8080/post/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer FRIEND_TOKEN" \
  -d '{
    "text": "Hello from friend!",
    "author_user_id": "friend-uuid"
  }'
```

### 4. Проверьте уведомление в браузере

Вы должны увидеть:
```
[16:18:37] Received: {
  "channel": "/post/feed/posted",
  "message": {
    "id": "...",
    "text": "Hello from friend!",
    "author_user_id": "...",
    "created_at": "..."
  }
}
```

---

## ⚡ ДЗ: In-Memory хранилище для диалогов

В проекте модуль `Dialogs` поддерживает 2 режима хранения:

- `DIALOG_STORAGE=sql` - baseline (PostgreSQL `dialog_messages`)
- `DIALOG_STORAGE=redis` - In-Memory режим через Redis + Lua UDF (`EVAL`)

В Redis-режиме приложение не делает SQL-запросов в `dialog_messages`, а использует Lua-процедуры:

- отправка сообщения: атомарный `HSET + ZADD` в Lua;
- чтение диалога: `ZRANGE + HMGET` через Lua.

### 1. Подготовка пользователей для теста

```bash
# токен для первого пользователя (клиент нагрузки)
docker exec lumen_postgres psql -U postgres -d social -c "
UPDATE users SET token = 'dialog-load-token'
WHERE user_id = (SELECT user_id FROM users ORDER BY user_id LIMIT 1);
"

# получить пару user_id для теста
docker exec lumen_postgres psql -U postgres -d social -c "
SELECT user_id, token FROM users ORDER BY user_id LIMIT 2;
"
```

В `TOKEN` используйте `dialog-load-token`, в `PEER_ID` - `user_id` второго пользователя.

### 2. Baseline нагрузочный тест (SQL)

Убедитесь, что в `lumen-app/.env` стоит:

```env
DIALOG_STORAGE=sql
```

Запустите тест:

```bash
k6 run load-tests/dialogs-k6.js \
  -e BASE_URL=http://localhost:8080 \
  -e TOKEN=dialog-load-token \
  -e PEER_ID=005147b5-93a7-4b7f-b8e7-d21fd6561600 \
  -e VUS=30 \
  -e DURATION=60s \
  -e SEND_RATIO=0.3 \
  -e LIST_LIMIT=100
```

Сохраните метрики: `http_req_duration (avg/p95)`, `http_req_failed`, `http_reqs`.

### 3. Переключение на In-Memory (Redis + Lua UDF)

Поменяйте в `lumen-app/.env`:

```env
DIALOG_STORAGE=redis
```

Перезапустите приложение:

```bash
docker compose restart app nginx
```

### 4. Повторный нагрузочный тест

Перед каждым прогоном очищайте данные диалогов, чтобы стартовые условия были одинаковые:

```bash
# SQL-таблица сообщений
docker exec lumen_postgres psql -U postgres -d social -c "TRUNCATE TABLE dialog_messages;"

# Redis-ключи диалогов
docker exec lumen_redis sh -lc 'redis-cli --scan --pattern "dialog:*" | xargs -r redis-cli DEL'
docker exec lumen_redis sh -lc 'redis-cli --scan --pattern "dialog:message:*" | xargs -r redis-cli DEL'
docker exec lumen_redis sh -lc 'redis-cli --scan --pattern "user:exists:*" | xargs -r redis-cli DEL'
```

Запустите тот же `k6`-сценарий с теми же параметрами (`VUS`, `DURATION`, `SEND_RATIO`, `LIST_LIMIT`) и сравните результаты с baseline.

### 5. Шаблон сравнения результатов

| Конфигурация | avg latency | p95 latency | RPS | errors |
|--------------|-------------|-------------|-----|--------|
| SQL          |             |             |     |        |
| Redis Lua    |             |             |     |        |

---

## 🔍 Мониторинг и отладка

### RabbitMQ Management UI

```
http://localhost:15672
Логин: guest
Пароль: guest
```

Здесь можно увидеть:
- Exchanges
- Queues
- Connections
- Routing Keys

### Redis

Проверка лент пользователей:
```bash
# Все ленты
docker exec lumen_redis redis-cli KEYS "user:feed:*"

# Лента конкретного пользователя
docker exec lumen_redis redis-cli ZRANGE "user:feed:{userId}" 0 -1 WITHSCORES
```

### Логи

```bash
# Логи приложения
docker logs lumen_app -f

# Логи Nginx
docker logs lumen_nginx -f

# Логи RabbitMQ
docker logs lumen_rabbitmq -f
```

---

## 🐳 Docker Services

| Service        | Port  | Описание                           |
|----------------|-------|------------------------------------|
| `app`          | -     | PHP-FPM (Lumen)                   |
| `nginx`        | 8080  | HTTP сервер                        |
| `db`           | 5433  | PostgreSQL (master)               |
| `redis`        | 6379  | Redis (кеширование)               |
| `rabbitmq`     | 5672  | RabbitMQ (AMQP)                   |
| `rabbitmq`     | 15672 | RabbitMQ Management UI            |
| `grafana`      | 3000  | Grafana (мониторинг)              |
| `prometheus`   | 9090  | Prometheus                        |

---

## 📚 Технологии

- **Backend**: PHP 8.2 + Lumen 10
- **Database**: PostgreSQL 16
- **Cache**: Redis 7
- **Message Broker**: RabbitMQ 3
- **WebSocket**: Ratchet + ReactPHP
- **Queue**: Laravel Queue (Database driver)
- **HTTP Client**: php-amqplib/php-amqplib
- **Server**: Nginx + PHP-FPM
- **Containerization**: Docker + Docker Compose

---

## 🔧 Переменные окружения

```env
# Database
DB_HOST=db
DB_PORT=5432
DB_DATABASE=social
DB_USERNAME=postgres
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_VHOST=/
RABBITMQ_LOGIN=guest
RABBITMQ_PASSWORD=guest

# Queue
QUEUE_CONNECTION=database

# Celebrity Protection
MAX_POST_FOLLOWERS=500
```

---

## 📝 Лицензия

MIT
