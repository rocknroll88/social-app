<?php

namespace App\Jobs;

use App\Services\FeedCacheService;
use App\Services\WebSocketNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessPostCreation implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private string $postId;
    private string $authorUserId;
    private string $postText;
    private int $timestamp;

    /**
     * Create a new job instance.
     */
    public function __construct(string $postId, string $authorUserId, string $postText, int $timestamp)
    {
        $this->postId = $postId;
        $this->authorUserId = $authorUserId;
        $this->postText = $postText;
        $this->timestamp = $timestamp;
    }

    /**
     * Execute the job.
     */
    public function handle(FeedCacheService $feedCacheService, WebSocketNotificationService $notificationService): void
    {
        // Получаем список друзей автора поста
        $followers = $this->getFollowers($this->authorUserId);

        if (empty($followers)) {
            return;
        }

        // Применяем защиту от celebrity эффекта - ограничиваем количество одновременных обновлений
        $followers = $this->applyCelebrityProtection($followers);

        // Для каждого друга обновляем ленту и отправляем уведомление
        foreach ($followers as $followerId) {
            // Обновляем кеш ленты конкретного друга
            $feedCacheService->addPostToUserFeed(
                $followerId,
                $this->postId,
                $this->timestamp
            );

            // Отправляем WebSocket уведомление конкретному другу
            $notificationService->notifyUserAboutNewPost(
                $followerId,
                $this->postId,
                $this->postText,
                $this->authorUserId
            );
        }
    }

    /**
     * Применяет защиту от celebrity эффекта, ограничивая количество обрабатываемых друзей
     *
     * @param array $followers
     * @return array
     */
    private function applyCelebrityProtection(array $followers): array
    {
        $maxFollowers = env('MAX_POST_FOLLOWERS', 500); // Максимум друзей для обработки за раз

        if (count($followers) <= $maxFollowers) {
            return $followers;
        }

        // Если друзей слишком много, берем случайную выборку
        // Это предотвращает перегрузку системы при публикации популярного пользователя
        shuffle($followers);
        return array_slice($followers, 0, $maxFollowers);
    }

    /**
     * Получить список друзей (подписчиков) пользователя
     */
    private function getFollowers(string $userId): array
    {
        return DB::table('friends')
            ->where('friend_id', $userId)
            ->pluck('user_id')
            ->toArray();
    }
}
