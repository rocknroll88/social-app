<?php

namespace App\Http\Controllers;

use App\Repositories\FriendRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class FriendController extends Controller
{
    /**
     * @param FriendRepository $friendRepository
     */
    public function __construct(private readonly FriendRepository $friendRepository)
    {
    }

    /**
     * @OA\Put(
     *     path="/friend/set/{user_id}",
     *     summary="Добавить пользователя в друзья",
     *     description="Пользователь успешно указал своего друга",
     *     tags={"Friends"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         description="Идентификатор пользователя",
     *         required=true,
     *         @OA\Schema(type="string", example="e4d2e6b0-cde2-42c5-aac3-0b8316f21e58")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Пользователь успешно указал своего друга"
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
     *
     * @param string $userId - ID пользователя, которого добавляем в друзья
     * @param Request $request
     * @return JsonResponse
     */
    public function set(Request $request, string $user_id): JsonResponse
    {
        // Получаем текущего авторизованного пользователя из middleware
        $authUser = $request->attributes->get('auth_user');
        
        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $currentUserId = $authUser->user_id;

        // Проверяем, что пользователь не добавляет сам себя
        if ($currentUserId === $user_id) {
            return response()->json(['error' => 'Cannot add yourself as a friend'], 400);
        }

        $success = $this->friendRepository->addFriend($currentUserId, $user_id);

        if (!$success) {
            return response()->json(['error' => 'User not found or already friends'], 400);
        }

        return response()->json(['message' => 'Friend added successfully'], 200);
    }

    /**
     * @OA\Put(
     *     path="/friend/delete/{user_id}",
     *     summary="Удалить пользователя из друзей",
     *     description="Пользователь успешно удалил из друзей пользователя",
     *     tags={"Friends"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         description="Идентификатор пользователя",
     *         required=true,
     *         @OA\Schema(type="string", example="e4d2e6b0-cde2-42c5-aac3-0b8316f21e58")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Пользователь успешно удалил из друзей пользователя"
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
     *
     * @param string $userId - ID пользователя, которого удаляем из друзей
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(Request $request, string $user_id): JsonResponse
    {
        // Получаем текущего авторизованного пользователя из middleware
        $authUser = $request->attributes->get('auth_user');
        
        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $currentUserId = $authUser->user_id;

        $success = $this->friendRepository->deleteFriend($currentUserId, $user_id);

        if (!$success) {
            return response()->json(['error' => 'Friend not found'], 400);
        }

        return response()->json(['message' => 'Friend deleted successfully'], 200);
    }
}

