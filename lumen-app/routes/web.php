<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/debug/db', function () {
    return response()->json([
        'default' => config('database.default'),
        'host' => config('database.connections.pgsql.host'),
        'user' => config('database.connections.pgsql.username'),
        'db' => config('database.connections.pgsql.database'),
    ]);
});

$router->post('/login', 'AuthController@login');
$router->post('/user/register', 'UserController@register');

// Публичные методы (не требуют авторизации согласно спецификации)
$router->get('/user/get/{id}', 'UserController@get');
$router->get('/user/search', 'UserController@search');

// Защищенные методы (требуют Bearer токен)
$router->group(['middleware' => 'auth'], function () use ($router) {
    // Друзья
    $router->put('/friend/set/{user_id}', 'FriendController@set');
    $router->put('/friend/delete/{user_id}', 'FriendController@delete');
    
    // Посты
    $router->post('/post/create', 'PostController@create');
    $router->put('/post/update', 'PostController@update');
    $router->put('/post/delete/{id}', 'PostController@delete');
    $router->get('/post/feed', 'PostController@feed');
    
    // Диалоги
    $router->post('/dialog/{user_id}/send', 'DialogController@send');
    $router->get('/dialog/{user_id}/list', 'DialogController@list');
    $router->post('/dialog/{user_id}/read', 'DialogController@read');
    $router->get('/dialog/unread', 'DialogController@unreadCounters');
});

// Публичные методы для постов (согласно спецификации /post/get не требует auth)
$router->get('/post/get/{id}', 'PostController@get');

// Swagger Documentation Routes
$router->get('/api/documentation', function () use ($router) {
    return redirect('/api/documentation/index.html');
});
