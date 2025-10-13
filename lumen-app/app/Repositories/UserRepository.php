<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use stdClass;
use Ramsey\Uuid\Uuid;


class UserRepository
{
    /**
     * @param array $data
     *
     * @return string
     */
    public function create(array $data): string
    {
        $uuid = Uuid::uuid4()->toString();

        DB::insert(
            'INSERT INTO users (user_id, first_name, second_name, birthdate, biography, city, password)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $uuid,
                $data['first_name'],
                $data['second_name'],
                $data['birthdate'],
                $data['biography'],
                $data['city'],
                $data['password'],
            ]
        );

        return $uuid;
    }

    /**
     * @param string $email
     *
     * @return stdClass|null
     */
    public function findByEmail(string $email): ?stdClass
    {
        return DB::selectOne('SELECT * FROM users WHERE email = ?', [$email]);
    }

    /**
     * @param string $userId
     *
     * @return stdClass|null
     */
    public function findById(string $userId): ?stdClass
    {
        return DB::table('users')
            ->select('user_id as id', 'first_name', 'second_name', 'birthdate', 'biography', 'city')
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * @param string $userId
     * @param string $password
     *
     * @return string|null
     */
    public function generateTokenByCredentials(string $userId, string $password): ?string
    {
        $user = DB::table('users')->where('user_id', $userId)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $token = Str::random(60);

        DB::table('users')
            ->where('user_id', $userId)
            ->update(['token' => $token]);

        return $token;
    }

    /**
     * Поиск анкет по частям имени и фамилии.
     *
     * @param string $firstNamePart  Часть имени (из запроса first_name)
     * @param string $lastNamePart   Часть фамилии (из запроса last_name)
     * @param int    $limit          Максимум записей на страницу
     * @param int    $offset         Смещение (для пагинации)
     *
     * @return array<int, stdClass>
     */
    public function searchByName(string $firstNamePart, string $lastNamePart, int $limit = 50, int $offset = 0): array
    {
        $escape = static function (string $s): string {
            return addcslashes(trim($s), '\\%_');
        };

        $first = $escape($firstNamePart) . '%';
        $last  = $escape($lastNamePart)  . '%';

        $rows = DB::table('users')
            ->select('user_id as id', 'first_name', 'second_name', 'birthdate', 'biography', 'city')
            ->where('first_name', 'LIKE', $first)
            ->where('second_name', 'LIKE', $last)
            ->orderBy('second_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return $rows->toArray();
    }
}