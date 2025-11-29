<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Social Network API",
 *     description="API для социальной сети",
 *     @OA\Contact(
 *         email="support@social-network.com"
 *     )
 * )
 * @OA\Server(
 *     url="/",
 *     description="API Server"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class Controller extends BaseController
{
    //
}
