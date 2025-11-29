<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class UserController extends Controller
{
    /**
     * @param UserRepository $userRepository
     */
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /**
     * @OA\Post(
     *     path="/user/register",
     *     summary="Регистрация пользователя",
     *     description="Создание нового пользователя в системе",
     *     tags={"Users"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name", "second_name", "birthdate", "biography", "city", "password"},
     *             @OA\Property(property="first_name", type="string", example="Имя", description="Имя"),
     *             @OA\Property(property="second_name", type="string", example="Фамилия", description="Фамилия"),
     *             @OA\Property(property="birthdate", type="string", format="date", example="2017-02-01", description="Дата рождения"),
     *             @OA\Property(property="biography", type="string", example="Хобби, интересы и т.п.", description="Биография"),
     *             @OA\Property(property="city", type="string", example="Москва", description="Город"),
     *             @OA\Property(property="password", type="string", format="password", example="Секретная строка", description="Пароль")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешная регистрация",
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="string", example="e4d2e6b0-cde2-42c5-aac3-0b8316f21e58")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Невалидные данные"
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
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->only([
            'first_name', 'second_name', 'birthdate', 'biography', 'city', 'password'
        ]);

        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);

        $userId = $this->userRepository->create($data);

        return response()->json([
            'user_id' => $userId
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/user/get/{id}",
     *     summary="Получение анкеты пользователя",
     *     description="Получение информации о пользователе по его ID",
     *     tags={"Users"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Идентификатор пользователя",
     *         required=true,
     *         @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешное получение анкеты пользователя",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *             @OA\Property(property="first_name", type="string", example="Имя"),
     *             @OA\Property(property="second_name", type="string", example="Фамилия"),
     *             @OA\Property(property="birthdate", type="string", format="date", example="2017-02-01"),
     *             @OA\Property(property="biography", type="string", example="Хобби, интересы и т.п."),
     *             @OA\Property(property="city", type="string", example="Москва")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Невалидные данные"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Анкета не найдена"
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
     * @param string $id
     * @return JsonResponse
     */
    public function get(string $id): JsonResponse
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    /**
     * @OA\Get(
     *     path="/user/search",
     *     summary="Поиск анкет",
     *     description="Поиск пользователей по имени и фамилии с поддержкой пагинации",
     *     tags={"Users"},
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="Условие поиска по имени",
     *         required=true,
     *         @OA\Schema(type="string", description="Часть имени для поиска", example="Конст")
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="Условие поиска по фамилии",
     *         required=true,
     *         @OA\Schema(type="string", description="Часть фамилии для поиска", example="Оси")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Количество записей (от 1 до 100, по умолчанию 50)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50, example=10)
     *     ),
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         description="Смещение (от 0, по умолчанию 0)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=0, default=0, example=0)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешные поиск пользователя",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="first_name", type="string", example="Имя"),
     *                 @OA\Property(property="second_name", type="string", example="Фамилия"),
     *                 @OA\Property(property="birthdate", type="string", format="date", example="2017-02-01"),
     *                 @OA\Property(property="biography", type="string", example="Хобби, интересы и т.п."),
     *                 @OA\Property(property="city", type="string", example="Москва")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Невалидные данные"
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
     * GET /user/search — Поиск анкет
     *
     * Необязательные query-параметры:
     *  - limit (1..100, по умолчанию 50)
     *  - offset (>=0, по умолчанию 0)
     */
    public function search(Request $request): JsonResponse
    {
        $first = (string) $request->query('first_name', '');
        $last  = (string) $request->query('last_name', '');

        if ($first === '' || $last === '') {
            return response()->json([
                'error' => 'Невалидные данные',
                'details' => [
                    'first_name' => $first === '' ? 'Обязательное поле' : null,
                    'last_name'  => $last  === '' ? 'Обязательное поле' : null,
                ]
            ], 400);
        }

        $first = mb_substr(trim($first), 0, 100);
        $last  = mb_substr(trim($last),  0, 100);

        $limit  = (int) $request->query('limit', 50);
        $offset = (int) $request->query('offset', 0);
        $limit  = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $users = $this->userRepository->searchByName($first, $last, $limit, $offset);

        return response()->json($users, 200);
    }
}