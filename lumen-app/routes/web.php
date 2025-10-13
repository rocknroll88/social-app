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
$router->get('/user/get/{id}', 'UserController@get');
$router->get('/user/search', 'UserController@search');
