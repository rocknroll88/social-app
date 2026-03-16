<?php

namespace App\Http\Controllers;

use App\Exceptions\ChatServiceException;
use App\Services\ChatServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class DialogController extends Controller
{
    public function __construct(private readonly ChatServiceClient $chatServiceClient)
    {
    }

    /**
     * @OA\Post(
     *     path="/dialog/{user_id}/send",
     *     summary="Отправить сообщение пользователю",
     *     description="Отправка сообщения пользователю",
     *     tags={"Dialogs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         description="Идентификатор пользователя-получателя",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="e4d2e6b0-cde2-42c5-aac3-0b8316f21e58")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"text"},
     *             @OA\Property(property="text", type="string", example="Привет, как дела?", description="Текст сообщения")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешно отправлено сообщение"
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
     * @param Request $request
     * @param string $user_id ID пользователя-получателя
     * @return JsonResponse
     */
    public function send(Request $request, string $user_id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $text = $request->input('text');

        if (empty($text)) {
            return response()->json(['error' => 'Text is required'], 400);
        }

        // Нельзя отправлять сообщения самому себе
        if ($authUser->user_id === $user_id) {
            return response()->json(['error' => 'Cannot send message to yourself'], 400);
        }

        $requestId = (string) $request->attributes->get('request_id', '');

        try {
            $this->chatServiceClient->sendMessage($requestId, $authUser->user_id, $user_id, $text);
        } catch (ChatServiceException $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->statusCode());
        }

        return response()->json(['message' => 'Message sent successfully'], 200);
    }

    /**
     * @OA\Get(
     *     path="/dialog/{user_id}/list",
     *     summary="Получить диалог с пользователем",
     *     description="Диалог между двумя пользователями",
     *     tags={"Dialogs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         description="Идентификатор пользователя-собеседника",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="e4d2e6b0-cde2-42c5-aac3-0b8316f21e58")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Диалог между двумя пользователями",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="from", type="string", format="uuid", example="e4d2e6b0-cde2-42c5-aac3-0b8316f21e58"),
     *                 @OA\Property(property="to", type="string", format="uuid", example="1d535fd6-7521-4cb1-aa6d-031be7123c4d"),
     *                 @OA\Property(property="text", type="string", example="Привет, как дела?")
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
     *
     * @param Request $request
     * @param string $user_id ID пользователя-собеседника
     * @return JsonResponse
     */
    public function list(Request $request, string $user_id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $limit = max(1, min((int) $request->query('limit', 100), 500));
        $offset = max(0, (int) $request->query('offset', 0));
        $requestId = (string) $request->attributes->get('request_id', '');

        try {
            $messages = $this->chatServiceClient->getDialog($requestId, $authUser->user_id, $user_id, $limit, $offset);
        } catch (ChatServiceException $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->statusCode());
        }

        return response()->json($messages, 200);
    }
}
