<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BearerAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = substr($authHeader, 7); // Убираем "Bearer "

        if (empty($token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Проверяем токен в базе данных
        $user = DB::table('users')
            ->where('token', $token)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        // Сохраняем пользователя в request для дальнейшего использования
        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}

