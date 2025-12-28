<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPostCreation;
use App\Repositories\PostRepository;
use App\Services\FeedCacheService;
use App\Services\WebSocketNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * @param PostRepository $postRepository
     * @param FeedCacheService $feedCacheService
     */
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly FeedCacheService $feedCacheService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/post/create",
     *     summary="Создание поста",
     *     description="Создание нового поста",
     *     tags={"Posts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"text"},
     *             @OA\Property(property="text", type="string", example="Lorem ipsum dolor sit amet, consectetur adipiscing elit...", description="Текст поста")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешно создан пост",
     *         @OA\JsonContent(
     *             @OA\Property(property="post_id", type="string", example="1d535fd6-7521-4cb1-aa6d-031be7123c4d")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Невалидные данные ввода"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Неавторизованный доступ"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Ошибка сервера"
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Ошибка сервера"
     *     )
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        
        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $text = $request->input('text');
        
        if (empty($text)) {
            return response()->json(['error' => 'Text is required'], 400);
        }

        $postId = $this->postRepository->create($authUser->user_id, $text);

        // Синхронная обработка для тестирования
        $timestamp = time();
        $job = new ProcessPostCreation($postId, $authUser->user_id, $text, $timestamp);
        $job->handle($this->feedCacheService, app(WebSocketNotificationService::class));

        return response()->json(['post_id' => $postId], 200);
    }

    /**
     * @OA\Put(
     *     path="/post/update",
     *     summary="Изменение поста",
     *     description="Обновление существующего поста",
     *     tags={"Posts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id", "text"},
     *             @OA\Property(property="id", type="string", example="1d535fd6-7521-4cb1-aa6d-031be7123c4d", description="Идентификатор поста"),
     *             @OA\Property(property="text", type="string", example="Lorem ipsum dolor sit amet...", description="Текст поста")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешно изменен пост"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Невалидные данные ввода"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Неавторизованный доступ"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Ошибка сервера"
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Ошибка сервера"
     *     )
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        
        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $postId = $request->input('id');
        $text = $request->input('text');
        
        if (empty($postId) || empty($text)) {
            return response()->json(['error' => 'ID and text are required'], 400);
        }

        $updated = $this->postRepository->update($postId, $authUser->user_id, $text);

        if (!$updated) {
            return response()->json(['error' => 'Post not found or you are not the author'], 400);
        }

        return response()->json(['message' => 'Post updated successfully'], 200);
    }

    /**
     * @OA\Put(
     *     path="/post/delete/{id}",
     *     summary="Удаление поста",
     *     description="Удаление поста по ID",
     *     tags={"Posts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Идентификатор поста",
     *         required=true,
     *         @OA\Schema(type="string", example="1d535fd6-7521-4cb1-aa6d-031be7123c4d")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешно удален пост"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Невалидные данные ввода"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Неавторизованный доступ"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Ошибка сервера"
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Ошибка сервера"
     *     )
     * )
     */
    public function delete(Request $request, string $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        
        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $deleted = $this->postRepository->delete($id, $authUser->user_id);

        if (!$deleted) {
            return response()->json(['error' => 'Post not found or you are not the author'], 400);
        }

        // Удаляем пост из кеша лент всех подписчиков
        $this->feedCacheService->removePostFromFollowersFeeds($id, $authUser->user_id);

        return response()->json(['message' => 'Post deleted successfully'], 200);
    }

    /**
     * @OA\Get(
     *     path="/post/get/{id}",
     *     summary="Получение поста",
     *     description="Получение поста по ID",
     *     tags={"Posts"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Идентификатор поста",
     *         required=true,
     *         @OA\Schema(type="string", example="1d535fd6-7521-4cb1-aa6d-031be7123c4d")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешно получен пост",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string", example="1d535fd6-7521-4cb1-aa6d-031be7123c4d"),
     *             @OA\Property(property="text", type="string", example="Lorem ipsum dolor sit amet..."),
     *             @OA\Property(property="author_user_id", type="string", example="e4d2e6b0-cde2-42c5-aac3-0b8316f21e58")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Невалидные данные ввода"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Неавторизованный доступ"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Ошибка сервера"
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Ошибка сервера"
     *     )
     * )
     */
    public function get(string $id): JsonResponse
    {
        $post = $this->postRepository->findById($id);

        if (!$post) {
            return response()->json(['error' => 'Post not found'], 404);
        }

        return response()->json($post, 200);
    }

    /**
     * @OA\Get(
     *     path="/post/feed",
     *     summary="Лента постов друзей",
     *     description="Получение ленты постов от друзей пользователя",
     *     tags={"Posts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         description="Оффсет с которого начинать выдачу",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=0, default=0, example=0)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Лимит, ограничивающий кол-во возвращенных сущностей",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=10, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешно получены посты друзей",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="string", example="1d535fd6-7521-4cb1-aa6d-031be7123c4d"),
     *                 @OA\Property(property="text", type="string", example="Lorem ipsum dolor sit amet..."),
     *                 @OA\Property(property="author_user_id", type="string", example="e4d2e6b0-cde2-42c5-aac3-0b8316f21e58")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Невалидные данные ввода"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Неавторизованный доступ"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Ошибка сервера"
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Ошибка сервера"
     *     )
     * )
     */
    public function feed(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        
        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $offset = max(0, (int) $request->query('offset', 0));
        $limit = max(1, min(100, (int) $request->query('limit', 10)));

        // Получаем ID постов из кеша
        $postIds = $this->feedCacheService->getFeed($authUser->user_id, $offset, $limit);

        if (empty($postIds)) {
            // Если кеш пуст, предзаполняем его
            $this->feedCacheService->warmUpFeed($authUser->user_id);
            $postIds = $this->feedCacheService->getFeed($authUser->user_id, $offset, $limit);
            
            if (empty($postIds)) {
                return response()->json([], 200);
            }
        }

        // Получаем полные данные постов из БД
        $posts = $this->postRepository->findByIds($postIds);

        return response()->json($posts, 200);
    }
}