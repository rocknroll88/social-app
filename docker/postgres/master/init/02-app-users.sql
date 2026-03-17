CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID UNIQUE NOT NULL,
    first_name VARCHAR(100),
    second_name VARCHAR(100),
    birthdate DATE,
    biography TEXT,
    city VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (
    user_id,
    first_name,
    second_name,
    birthdate,
    biography,
    city,
    password,
    token
) VALUES
    (
        '11111111-1111-4111-8111-111111111111',
        'User',
        'One',
        '1990-01-01',
        'HA test user 1',
        'Moscow',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'ha-token-user-1'
    ),
    (
        '22222222-2222-4222-8222-222222222222',
        'User',
        'Two',
        '1991-02-02',
        'HA test user 2',
        'Saint Petersburg',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'ha-token-user-2'
    ),
    (
        '33333333-3333-4333-8333-333333333333',
        'User',
        'Three',
        '1992-03-03',
        'HA test user 3',
        'Kazan',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'ha-token-user-3'
    )
ON CONFLICT (user_id) DO NOTHING;
