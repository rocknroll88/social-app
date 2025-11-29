<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class FriendRepository
{
    /**
     * Добавить пользователя в друзья
     *
     * @param string $userId - ID текущего пользователя
     * @param string $friendId - ID пользователя, которого добавляем в друзья
     * @return bool
     */
    public function addFriend(string $userId, string $friendId): bool
    {
        // Проверяем, что пользователь не добавляет сам себя
        if ($userId === $friendId) {
            return false;
        }

        // Проверяем, что такой пользователь существует
        $friendExists = DB::table('users')->where('user_id', $friendId)->exists();
        if (!$friendExists) {
            return false;
        }

        // Проверяем, что они уже не друзья
        $alreadyFriends = DB::table('friends')
            ->where('user_id', $userId)
            ->where('friend_id', $friendId)
            ->exists();

        if ($alreadyFriends) {
            return true; // Уже в друзьях, считаем успехом
        }

        // Добавляем в друзья
        try {
            DB::table('friends')->insert([
                'user_id' => $userId,
                'friend_id' => $friendId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Удалить пользователя из друзей
     *
     * @param string $userId - ID текущего пользователя
     * @param string $friendId - ID пользователя, которого удаляем из друзей
     * @return bool
     */
    public function deleteFriend(string $userId, string $friendId): bool
    {
        $deleted = DB::table('friends')
            ->where('user_id', $userId)
            ->where('friend_id', $friendId)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Проверить, являются ли пользователи друзьями
     *
     * @param string $userId
     * @param string $friendId
     * @return bool
     */
    public function areFriends(string $userId, string $friendId): bool
    {
        return DB::table('friends')
            ->where('user_id', $userId)
            ->where('friend_id', $friendId)
            ->exists();
    }

    /**
     * Получить список друзей пользователя
     *
     * @param string $userId
     * @return array
     */
    public function getFriends(string $userId): array
    {
        return DB::table('friends')
            ->join('users', 'friends.friend_id', '=', 'users.user_id')
            ->where('friends.user_id', $userId)
            ->select('users.user_id as id', 'users.first_name', 'users.second_name', 'users.city')
            ->get()
            ->toArray();
    }
}

