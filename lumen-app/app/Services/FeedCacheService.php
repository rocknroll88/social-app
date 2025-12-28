<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Predis\Client as PredisClient;

class FeedCacheService
{
    private const FEED_PREFIX = 'user:feed:';
    private const MAX_FEED_SIZE = 1000; // Максимум 1000 постов в ленте
    
    private PredisClient $redis;
    
    public function __construct()
    {
        $this->redis = new PredisClient([
            'scheme' => 'tcp',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_DB', 0),
        ]);
    }

    /**
     * Получить кеш-ключ для ленты пользователя
     */
    private function getFeedKey(string $userId): string
    {
        return self::FEED_PREFIX . $userId;
    }

    /**
     * Добавить пост в ленты всех подписчиков автора
     *
     * @param string $postId
     * @param string $authorUserId
     * @param int $timestamp
     * @return int Количество обновленных лент
     */
    public function addPostToFollowersFeeds(string $postId, string $authorUserId, int $timestamp): int
    {
        // Получаем всех, кто добавил автора в друзья (подписчики)
        $followers = DB::table('friends')
            ->where('friend_id', $authorUserId)
            ->pluck('user_id')
            ->toArray();

        if (empty($followers)) {
            return 0;
        }

        $redis = $this->redis;
        $updatedCount = 0;

        foreach ($followers as $followerId) {
            $feedKey = $this->getFeedKey($followerId);

            // Добавляем пост в sorted set (score = timestamp для сортировки)
            $redis->zadd($feedKey, $timestamp, $postId);

            // Обрезаем ленту до 1000 последних постов
            $redis->zremrangebyrank($feedKey, 0, -(self::MAX_FEED_SIZE + 1));

            $updatedCount++;
        }

        return $updatedCount;
    }

    /**
     * Получить ленту пользователя из кеша
     *
     * @param string $userId
     * @param int $offset
     * @param int $limit
     * @return array Массив ID постов
     */
    public function getFeed(string $userId, int $offset = 0, int $limit = 10): array
    {
        $feedKey = $this->getFeedKey($userId);
        $redis = $this->redis;

        // Получаем посты из sorted set в обратном порядке (новые сверху)
        // ZREVRANGE возвращает элементы от самого большого score к наименьшему
        $postIds = $redis->zrevrange($feedKey, $offset, $offset + $limit - 1);

        return $postIds ?: [];
    }

    /**
     * Предзаполнить кеш ленты для пользователя
     *
     * @param string $userId
     * @return int Количество добавленных постов
     */
    public function warmUpFeed(string $userId): int
    {
        $feedKey = $this->getFeedKey($userId);
        $redis = $this->redis;

        // Очищаем текущий кеш
        $redis->del($feedKey);

        // Получаем последние 1000 постов друзей
        $posts = DB::table('posts')
            ->join('friends', 'posts.author_user_id', '=', 'friends.friend_id')
            ->where('friends.user_id', $userId)
            ->select('posts.id', DB::raw('EXTRACT(EPOCH FROM posts.created_at)::integer as timestamp'))
            ->orderBy('posts.created_at', 'desc')
            ->limit(self::MAX_FEED_SIZE)
            ->get();

        if ($posts->isEmpty()) {
            return 0;
        }

        // Добавляем в sorted set батчами
        foreach ($posts as $post) {
            $redis->zadd($feedKey, $post->timestamp, $post->id);
        }

        return count($posts);
    }

    /**
     * Очистить ленту пользователя
     *
     * @param string $userId
     * @return bool
     */
    public function clearFeed(string $userId): bool
    {
        $feedKey = $this->getFeedKey($userId);
        $redis = $this->redis;

        return (bool) $redis->del($feedKey);
    }

    /**
     * Получить размер ленты пользователя
     *
     * @param string $userId
     * @return int
     */
    public function getFeedSize(string $userId): int
    {
        $feedKey = $this->getFeedKey($userId);
        $redis = $this->redis;

        return (int) $redis->zcard($feedKey);
    }

    /**
     * Удалить пост из всех лент
     *
     * @param string $postId
     * @param string $authorUserId
     * @return int Количество обновленных лент
     */
    public function removePostFromFollowersFeeds(string $postId, string $authorUserId): int
    {
        // Получаем всех подписчиков
        $followers = DB::table('friends')
            ->where('friend_id', $authorUserId)
            ->pluck('user_id')
            ->toArray();

        if (empty($followers)) {
            return 0;
        }

        $redis = $this->redis;
        $updatedCount = 0;

        foreach ($followers as $followerId) {
            $feedKey = $this->getFeedKey($followerId);
            $removed = $redis->zrem($feedKey, $postId);

            if ($removed) {
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    /**
     * Добавить пост в ленту конкретного пользователя
     *
     * @param string $userId
     * @param string $postId
     * @param int $timestamp
     * @return bool
     */
    public function addPostToUserFeed(string $userId, string $postId, int $timestamp): bool
    {
        $feedKey = $this->getFeedKey($userId);
        $redis = $this->redis;

        // Добавляем пост в sorted set (score = timestamp для сортировки)
        $redis->zadd($feedKey, $timestamp, $postId);

        // Обрезаем ленту до 1000 последних постов
        $redis->zremrangebyrank($feedKey, 0, -(self::MAX_FEED_SIZE + 1));

        return true;
    }
}