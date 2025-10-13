#!/bin/bash

MASTER="lumen_postgres"
SLAVE1="pgslave"
SLAVE2="pgasyncslave"

TEST_UUID="aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"

echo "🔧 Останавливаю первую реплику ($SLAVE1)..."
docker stop $SLAVE1 > /dev/null

echo "🧪 Выполняю тестовую запись на MASTER через psql..."
docker exec -i $MASTER psql -U postgres -d social -c "
INSERT INTO users (user_id, first_name, second_name, birthdate, biography, city, password)
VALUES ('$TEST_UUID', 'Test', 'WriteCheck', NULL, 'FROM_MASTER', '', '')
ON CONFLICT (user_id) DO UPDATE SET biography='UPDATED_MASTER';
"

echo "⏳ Жду 1 секунду..."
sleep 1

echo "📍 Проверяю наличие записи на MASTER:"
docker exec -i $MASTER psql -U postgres -d social -c "
SELECT user_id, biography FROM users WHERE user_id = '$TEST_UUID';
"

echo "📍 Проверяю наличие записи на SLAVE2 ($SLAVE2):"
docker exec -i $SLAVE2 psql -U postgres -d social -c "
SELECT user_id, biography FROM users WHERE user_id = '$TEST_UUID';
"

echo "♻️ Запускаю обратно SLAVE1..."
docker start $SLAVE1 > /dev/null

echo "✅ Проверка завершена. Если запись появилась на MASTER, но не на SLAVE2 — значит Laravel пишет только в мастер Correct."