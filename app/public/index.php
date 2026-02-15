<?php

use App\Infrastructure\LeastSignificativeBitImageEncoder;
use App\Infrastructure\TransparentImageEncoder;
use App\UI\Http\DecodeImageApiController;
use App\UI\Http\EncodeImageApiController;
use App\UI\Http\FallbackApiController;
use App\UI\Http\TestApiController;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

require __DIR__.'/../vendor/autoload.php';

$app = AppFactory::create();
$app->get('/hello/{name}', TestApiController::class);

$app->group('/alpha', function (Group $group) {
    $group->post('/encode', function ($request, $response, $args) {
        $controller = new EncodeImageApiController(new TransparentImageEncoder);

        return $controller($request, $response, $args);
    });
    $group->post('/decode', function ($request, $response, $args) {
        $controller = new DecodeImageApiController(new TransparentImageEncoder);

        return $controller($request, $response, $args);
    });
});

$app->group('/bit', function (Group $group) {
    $group->post('/encode', function ($request, $response, $args) {
        $controller = new EncodeImageApiController(new LeastSignificativeBitImageEncoder);

        return $controller($request, $response, $args);
    });
    $group->post('/decode', function ($request, $response, $args) {
        $controller = new DecodeImageApiController(new LeastSignificativeBitImageEncoder);

        return $controller($request, $response, $args);
    });
});

$app->any('{route:.*}', FallbackApiController::class);

$app->run();
