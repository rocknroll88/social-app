<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class PostRepository
{
    /**
     * Создать новый пост
     *
     * @param string $authorUserId
     * @param string $text
     * @return string - ID созданного поста
     */
    public function create(string $authorUserId, string $text): string
    {
        $postId = Uuid::uuid4()->toString();

        DB::table('posts')->insert([
            'id' => $postId,
            'author_user_id' => $authorUserId,
            'text' => $text,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $postId;
    }

    /**
     * Обновить пост
     *
     * @param string $postId
     * @param string $authorUserId
     * @param string $text
     * @return bool
     */
    public function update(string $postId, string $authorUserId, string $text): bool
    {
        $updated = DB::table('posts')
            ->where('id', $postId)
            ->where('author_user_id', $authorUserId) // Проверяем, что пользователь - автор поста
            ->update([
                'text' => $text,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $updated > 0;
    }

    /**
     * Удалить пост
     *
     * @param string $postId
     * @param string $authorUserId
     * @return bool
     */
    public function delete(string $postId, string $authorUserId): bool
    {
        $deleted = DB::table('posts')
            ->where('id', $postId)
            ->where('author_user_id', $authorUserId) // Проверяем, что пользователь - автор поста
            ->delete();

        return $deleted > 0;
    }

    /**
     * Получить пост по ID
     *
     * @param string $postId
     * @return object|null
     */
    public function findById(string $postId): ?object
    {
        return DB::table('posts')
            ->select('id', 'text', 'author_user_id')
            ->where('id', $postId)
            ->first();
    }

    /**
     * Получить ленту постов друзей пользователя
     *
     * @param string $userId
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getFriendsFeed(string $userId, int $offset = 0, int $limit = 10): array
    {
        // Получаем посты друзей с сортировкой по дате (новые сверху)
        $posts = DB::table('posts')
            ->join('friends', 'posts.author_user_id', '=', 'friends.friend_id')
            ->where('friends.user_id', $userId)
            ->select('posts.id', 'posts.text', 'posts.author_user_id')
            ->orderBy('posts.created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $posts->toArray();
    }

    /**
     * Получить посты пользователя
     *
     * @param string $userId
     * @param int $limit
     * @return array
     */
    public function getUserPosts(string $userId, int $limit = 10): array
    {
        $posts = DB::table('posts')
            ->where('author_user_id', $userId)
            ->select('id', 'text', 'author_user_id')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $posts->toArray();
    }

    /**
     * Получить посты по массиву ID (сохраняя порядок)
     *
     * @param array $postIds
     * @return array
     */
    public function findByIds(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        // Получаем посты
        $posts = DB::table('posts')
            ->whereIn('id', $postIds)
            ->select('id', 'text', 'author_user_id')
            ->get()
            ->keyBy('id');

        // Возвращаем в том же порядке, что и входящий массив
        $result = [];
        foreach ($postIds as $id) {
            if (isset($posts[$id])) {
                $result[] = $posts[$id];
            }
        }

        return $result;
    }
}

