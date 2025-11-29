<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{
    /**
     * @param UserRepository $userRepository
     */
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /**
     * @OA\Post(
     *     path="/login",
     *     summary="Упрощенный процесс аутентификации",
     *     description="Упрощенный процесс аутентификации путем передачи идентификатор пользователя и получения токена для дальнейшего прохождения авторизации",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id", "password"},
     *             @OA\Property(property="id", type="string", example="e4d2e6b0-cde2-42c5-aac3-0b8316f21e58", description="Идентификатор пользователя"),
     *             @OA\Property(property="password", type="string", example="Секретная строка", description="Пароль")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешная аутентификация",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="e4d2e6b0-cde2-42c5-aac3-0b8316f21e58")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Невалидные данные"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Пользователь не найден"
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
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $this->validate($request, [
            'id' => 'required|string|uuid',
            'password' => 'required|string',
        ]);

        $token = $this->userRepository->generateTokenByCredentials(
            $request->input('id'),
            $request->input('password')
        );

        if (!$token) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return response()->json(['token' => $token]);
    }
}