<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Random\RandomException;

class UserController extends Controller
{
    /**
     * @param UserRepository $userRepository
     */
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /**
     * @param Request $request
     *
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
        ], 201);
    }

    /**
     * @param string $id
     *
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