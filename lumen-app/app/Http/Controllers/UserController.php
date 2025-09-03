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
}