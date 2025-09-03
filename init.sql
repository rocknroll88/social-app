DROP TABLE IF EXISTS users;

CREATE TABLE users (
                       id SERIAL PRIMARY KEY,
                       user_id UUID NOT NULL UNIQUE,
                       first_name VARCHAR(100),
                       second_name VARCHAR(100),
                       birthdate DATE,
                       biography TEXT,
                       city VARCHAR(100),
                       password VARCHAR(255),
                       token VARCHAR(255)
);