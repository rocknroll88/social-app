-- расширение для bcrypt
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- временная таблица под CSV
CREATE TEMP TABLE tmp_people (
    full_name TEXT,
    birthdate DATE,
    city TEXT
);

-- загружаем CSV (замени ENCODING если надо)
COPY tmp_people(full_name, birthdate, city)
    FROM '/import/people.v2.csv'
    WITH (FORMAT csv, HEADER false, ENCODING 'UTF8');
;

-- вставляем данные в users
INSERT INTO users (
    user_id,
    first_name,
    second_name,
    birthdate,
    city,
    password,
    token
)
SELECT
    gen_random_uuid() AS user_id,
    split_part(full_name, ' ', 2) AS first_name,   -- имя
    split_part(full_name, ' ', 1) AS second_name,  -- фамилия
    birthdate,
    city,
    crypt('password', gen_salt('bf')) AS password, -- bcrypt("password")
    md5(random()::text || clock_timestamp()::text) AS token
FROM tmp_people;